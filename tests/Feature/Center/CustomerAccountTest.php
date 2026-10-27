<?php

namespace Tests\Feature\Center;

use App\Models\Branch;
use App\Models\PaymentReceipt;
use App\Models\Vehicle;
use App\Services\V1\BalanceService;
use Carbon\CarbonImmutable;
use Tests\CenterHttpTestCase;

/**
 * The customer page: account statement, unpaid invoices and collection
 * (oldest first, or split by hand over chosen invoices).
 */
class CustomerAccountTest extends CenterHttpTestCase
{
    public function test_statement_lists_invoices_and_collections_with_a_running_balance(): void
    {
        $customer = $this->customer();
        $vehicle = $this->vehicle($customer);

        $this->travelTo(CarbonImmutable::parse('2026-10-01 10:00', 'Africa/Cairo'));
        $this->postJson($this->url("vehicles/{$vehicle->id}/opening-balance"), ['amount' => 1000], $this->as($this->owner, $this->branch))->assertCreated();
        $this->travelTo(CarbonImmutable::parse('2026-10-05 10:00', 'Africa/Cairo'));
        $this->invoice($vehicle, '300');
        $this->travelTo(CarbonImmutable::parse('2026-10-10 10:00', 'Africa/Cairo'));
        $this->invoice($vehicle, '400');
        $this->travelTo(CarbonImmutable::parse('2026-10-12 10:00', 'Africa/Cairo'));
        $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 1100], $this->as($this->cashier()))->assertCreated();

        $response = $this->getJson($this->url("customers/{$customer->id}/statement"), $this->as($this->cashier()))
            ->assertOk()
            ->assertJsonPath('data.opening_balance', 0)
            ->assertJsonPath('data.closing_balance', 600)
            ->assertJsonPath('data.summary.total_invoiced', 1700)
            ->assertJsonPath('data.summary.total_paid', 1100)
            ->assertJsonPath('data.summary.balance', 600);

        $this->assertSame(['opening_balance', 'invoice', 'invoice', 'payment'], array_column(array_column($response->json('data.entries'), 'type'), 'value'));
        $this->assertSame([1000, 1300, 1700, 600], array_column($response->json('data.entries'), 'balance'));
        $this->assertSame('cash', $response->json('data.entries.3.method.value'));

        $this->inTenant($this->tenant);
        $this->assertSame(600.0, app(BalanceService::class)->customerBalance($customer->id)->toFloat());
    }

    public function test_statement_period_carries_the_opening_balance_in_local_days(): void
    {
        $customer = $this->customer();
        $vehicle = $this->vehicle($customer);

        $this->travelTo(CarbonImmutable::parse('2026-10-14 23:30', 'Africa/Cairo'));
        $this->invoice($vehicle, '300');
        $this->travelTo(CarbonImmutable::parse('2026-10-15 00:30', 'Africa/Cairo'));
        $this->invoice($vehicle, '400');
        $this->travelTo(CarbonImmutable::parse('2026-10-20 12:00', 'Africa/Cairo'));
        $this->invoice($vehicle, '500');

        $this->getJson($this->url("customers/{$customer->id}/statement?from=2026-10-15&to=2026-10-15"), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.opening_balance', 300)
            ->assertJsonCount(1, 'data.entries')
            ->assertJsonPath('data.entries.0.debit', 400)
            ->assertJsonPath('data.closing_balance', 700)
            ->assertJsonPath('data.summary.balance', 1200);
    }

    public function test_cancelled_orders_stay_out_and_open_deposits_are_reported_apart(): void
    {
        $customer = $this->customer();
        $vehicle = $this->vehicle($customer);

        $cancelled = $this->invoice($vehicle, '300');
        $this->postJson($this->url("orders/{$cancelled}/payments"), ['amount' => 300], $this->as($this->owner, $this->branch))->assertCreated();
        $this->postJson($this->url("orders/{$cancelled}/cancel"), ['reason' => 'غلط'], $this->as($this->owner, $this->branch))->assertOk();

        $open = $this->openOrder($vehicle, [['name' => 'x', 'qty' => 1, 'unit_price' => 500]]);
        $this->postJson($this->url("orders/{$open['id']}/payments"), ['amount' => 150], $this->as($this->owner, $this->branch))->assertCreated();

        $this->getJson($this->url("customers/{$customer->id}/statement"), $this->as($this->owner))
            ->assertOk()
            ->assertJsonCount(0, 'data.entries')
            ->assertJsonPath('data.closing_balance', 0)
            ->assertJsonPath('data.summary.open_orders_deposits', 150);
    }

    public function test_sold_vehicle_debt_stays_on_the_old_owner_statement(): void
    {
        $oldOwner = $this->customer('قديم');
        $newOwner = $this->customer('جديد');
        $vehicle = $this->vehicle($oldOwner);
        $this->invoice($vehicle, '250');

        $this->patchJson($this->url("vehicles/{$vehicle->id}/transfer-owner"), ['customer_id' => $newOwner->id], $this->as($this->owner))->assertOk();

        $this->getJson($this->url("customers/{$oldOwner->id}/statement"), $this->as($this->owner))->assertJsonPath('data.closing_balance', 250);
        $this->getJson($this->url("customers/{$newOwner->id}/statement"), $this->as($this->owner))->assertJsonCount(0, 'data.entries');
    }

    public function test_unpaid_orders_cover_every_branch_oldest_first(): void
    {
        $customer = $this->customer();
        $vehicle = $this->vehicle($customer);
        $paid = $this->invoice($vehicle, '100');
        $this->postJson($this->url("orders/{$paid}/payments"), ['amount' => 100], $this->as($this->owner, $this->branch))->assertCreated();

        $this->travel(1)->minutes();
        $older = $this->invoice($vehicle, '300');
        $this->travel(1)->minutes();
        $otherBranch = $this->createBranch('فرع 2');
        $newer = $this->invoiceIn($otherBranch, $vehicle, '400');

        $this->getJson($this->url("customers/{$customer->id}/unpaid-orders"), $this->as($this->cashier()))
            ->assertOk()
            ->assertJsonPath('data.total_due', 700)
            ->assertJsonCount(2, 'data.orders')
            ->assertJsonPath('data.orders.0.id', $older)
            ->assertJsonPath('data.orders.1.id', $newer)
            ->assertJsonPath('data.orders.1.branch.id', $otherBranch->id)
            ->assertJsonPath('data.orders.1.remaining', 400);
    }

    public function test_manual_split_pays_each_chosen_invoice_its_amount_on_one_receipt(): void
    {
        $customer = $this->customer();
        $vehicle = $this->vehicle($customer);
        $first = $this->invoice($vehicle, '300');
        $this->travel(1)->minutes();
        $second = $this->invoice($this->vehicle($customer), '400');
        $this->travel(1)->minutes();
        $third = $this->invoiceIn($this->createBranch('فرع 2'), $vehicle, '200');

        $this->postJson($this->url("customers/{$customer->id}/payments"), [
            'amount' => 350,
            'method' => 'instapay',
            'allocation' => 'manual',
            'orders' => [['order_id' => $second, 'amount' => 150], ['order_id' => $third, 'amount' => 200]],
        ], $this->as($this->cashier()))
            ->assertCreated()
            ->assertJsonPath('data.amount', 350)
            ->assertJsonPath('data.method.value', 'instapay')
            ->assertJsonCount(2, 'data.payments');

        $this->assertSame('0.00', $this->freshOrder($first)->paid);
        $this->assertSame('150.00', $this->freshOrder($second)->paid);
        $this->assertSame('200.00', $this->freshOrder($third)->paid);
        $this->assertSame($this->branch->id, PaymentReceipt::query()->withoutGlobalScope('branch')->sole()->branch_id);
    }

    public function test_paying_one_specific_invoice_defaults_to_cash(): void
    {
        $customer = $this->customer();
        $this->invoice($this->vehicle($customer), '300');
        $target = $this->invoice($this->vehicle($customer), '400');

        $this->postJson($this->url("customers/{$customer->id}/payments"), [
            'amount' => 400, 'allocation' => 'manual', 'orders' => [['order_id' => $target, 'amount' => 400]],
        ], $this->as($this->cashier()))
            ->assertCreated()
            ->assertJsonPath('data.method.value', 'cash');

        $this->assertSame('400.00', $this->freshOrder($target)->paid);
    }

    public function test_manual_split_is_validated_and_nothing_is_recorded_on_error(): void
    {
        $customer = $this->customer();
        $vehicle = $this->vehicle($customer);
        $invoice = $this->invoice($vehicle, '300');
        $open = $this->openOrder($vehicle, [['name' => 'x', 'qty' => 1, 'unit_price' => 100]]);
        $foreign = $this->invoice($this->vehicle(), '500');
        $headers = $this->as($this->cashier());
        $manual = fn (int $amount, array $orders): array => ['amount' => $amount, 'allocation' => 'manual', 'orders' => $orders];

        $this->postJson($this->url("customers/{$customer->id}/payments"), $manual(200, [['order_id' => $invoice, 'amount' => 100]]), $headers)
            ->assertStatus(422)->assertJsonPath('msg', __('messages.allocation_sum_mismatch', ['amount' => '200.00']));
        $this->postJson($this->url("customers/{$customer->id}/payments"), $manual(301, [['order_id' => $invoice, 'amount' => 301]]), $headers)
            ->assertStatus(422);
        $this->postJson($this->url("customers/{$customer->id}/payments"), $manual(50, [['order_id' => $open['id'], 'amount' => 50]]), $headers)
            ->assertStatus(422)->assertJsonPath('msg', __('messages.order_not_payable'));
        $this->postJson($this->url("customers/{$customer->id}/payments"), $manual(150, [['order_id' => $invoice, 'amount' => 100], ['order_id' => $foreign, 'amount' => 50]]), $headers)
            ->assertStatus(422);
        $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 100, 'allocation' => 'manual'], $headers)
            ->assertStatus(422);

        $this->inTenant($this->tenant);
        $this->assertSame(0, PaymentReceipt::query()->withoutGlobalScope('branch')->count());
        $this->assertSame('0.00', $this->freshOrder($invoice)->paid);
    }

    public function test_cash_is_the_default_method_everywhere(): void
    {
        $customer = $this->customer();
        $vehicle = $this->vehicle($customer);
        $headers = $this->as($this->cashier());

        $order = $this->openOrder($vehicle, [['name' => 'x', 'qty' => 1, 'unit_price' => 1000]]);
        $this->postJson($this->url("orders/{$order['id']}/payments"), ['amount' => 100], $headers)->assertJsonPath('data.method.value', 'cash');
        $this->postJson($this->url("orders/{$order['id']}/complete"), ['payments' => [['amount' => 100]]], $headers)->assertOk();
        $this->postJson($this->url("vehicles/{$vehicle->id}/payments"), ['amount' => 100], $headers)->assertJsonPath('data.method.value', 'cash');
        $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 100], $headers)->assertJsonPath('data.method.value', 'cash');

        $this->inTenant($this->tenant);
        $this->assertSame(['cash'], PaymentReceipt::query()->withoutGlobalScope('branch')->pluck('method')->map->value->unique()->values()->all());
    }

    public function test_customer_invoice_is_visible_from_any_branch_but_only_through_its_customer(): void
    {
        $customer = $this->customer();
        $order = $this->invoiceIn($this->createBranch('فرع 2'), $this->vehicle($customer), '300');
        $headers = $this->as($this->cashier());

        $this->getJson($this->url("orders/{$order}"), $headers)->assertNotFound();

        $this->getJson($this->url("customers/{$customer->id}/orders/{$order}"), $headers)
            ->assertOk()
            ->assertJsonPath('data.id', $order)
            ->assertJsonCount(1, 'data.items');

        $this->getJson($this->url("customers/{$this->customer()->id}/orders/{$order}"), $headers)->assertNotFound();
    }

    public function test_permissions_and_tenant_isolation(): void
    {
        $customer = $this->customer();
        $technician = $this->as($this->technician());

        $this->getJson($this->url("customers/{$customer->id}/statement"), $technician)->assertForbidden();
        $this->getJson($this->url("customers/{$customer->id}/unpaid-orders"), $technician)->assertForbidden();
        $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 1], $technician)->assertForbidden();

        [, $otherOwner] = $this->createTenant('مركز ب');
        $this->getJson($this->url("customers/{$customer->id}/statement"), $this->as($otherOwner))->assertNotFound();
        $this->getJson($this->url("customers/{$customer->id}/unpaid-orders"), $this->as($otherOwner))->assertNotFound();
    }

    /**
     * A completed single-line invoice issued by another branch (owner acting there).
     */
    private function invoiceIn(Branch $branch, Vehicle $vehicle, string $amount): int
    {
        $headers = $this->as($this->owner, $branch);

        $id = $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => [['name' => 'خدمة', 'qty' => 1, 'unit_price' => $amount]]], $headers)
            ->assertCreated()
            ->json('data.order.id');

        $this->postJson($this->url("orders/{$id}/complete"), [], $headers)->assertOk();
        $this->inTenant($this->tenant);

        return $id;
    }
}
