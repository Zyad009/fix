<?php

namespace Tests\Feature\Center;

use App\Models\Payment;
use Illuminate\Support\Facades\Schema;
use Tests\CenterHttpTestCase;

/**
 * brief §4.5 — payments, per-vehicle debt and lump-sum allocation.
 */
class PaymentTest extends CenterHttpTestCase
{
    public function test_deposit_on_open_order_and_overpayment_is_rejected(): void
    {
        $order = $this->openOrder($this->vehicle(), [['name' => 'x', 'qty' => 1, 'unit_price' => 300]]);

        $this->postJson($this->url("orders/{$order['id']}/payments"), ['amount' => 100, 'method' => 'wallet'], $this->as($this->cashier()))
            ->assertCreated()
            ->assertJsonPath('data.receipt_no', 1)
            ->assertJsonPath('data.payments.0.order.remaining', 200);

        $this->postJson($this->url("orders/{$order['id']}/payments"), ['amount' => 201, 'method' => 'cash'], $this->as($this->owner))
            ->assertStatus(422);

        $this->assertSame('100.00', $this->freshOrder($order['id'])->paid);
    }

    public function test_lump_sum_is_spread_oldest_first_across_vehicles(): void
    {
        $customer = $this->customer();
        $firstVehicle = $this->vehicle($customer);
        $secondVehicle = $this->vehicle($customer);

        $olderOrder = $this->invoice($firstVehicle, '300');
        $this->travel(1)->minutes();
        $newerOrder = $this->invoice($secondVehicle, '400');

        $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 500, 'method' => 'cash'], $this->as($this->cashier()))
            ->assertCreated()
            ->assertJsonCount(2, 'data.payments')
            ->assertJsonPath('data.amount', 500);

        $this->assertSame('300.00', $this->freshOrder($olderOrder)->paid);
        $this->assertSame('200.00', $this->freshOrder($newerOrder)->paid);

        $this->getJson($this->url("customers/{$customer->id}/balance"), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.total', 200)
            ->assertJsonCount(1, 'data.vehicles')
            ->assertJsonPath('data.vehicles.0.vehicle_id', $secondVehicle->id)
            ->assertJsonPath('data.vehicles.0.balance', 200);
    }

    public function test_debt_is_reported_per_vehicle_and_summed_per_customer(): void
    {
        $customer = $this->customer();
        $this->invoice($this->vehicle($customer), '300');
        $this->invoice($this->vehicle($customer), '400');
        $paidVehicle = $this->vehicle($customer);
        $paidOrder = $this->invoice($paidVehicle, '250');
        $this->postJson($this->url("orders/{$paidOrder}/payments"), ['amount' => 250, 'method' => 'card'], $this->as($this->owner))->assertCreated();

        $response = $this->getJson($this->url("customers/{$customer->id}"), $this->as($this->owner))->assertOk();

        $this->assertSame(700, $response->json('data.debts.total'));
        $this->assertEqualsCanonicalizing([300, 400], array_column($response->json('data.debts.vehicles'), 'balance'));
        $this->assertNotContains($paidVehicle->id, array_column($response->json('data.debts.vehicles'), 'vehicle_id'));
    }

    public function test_debt_is_computed_not_stored(): void
    {
        $this->assertFalse(Schema::hasColumn('customers', 'debt'));
        $this->assertFalse(Schema::hasColumn('customers', 'balance'));
        $this->assertFalse(Schema::hasColumn('vehicles', 'balance'));
    }

    public function test_collect_rejects_more_than_the_debt_or_no_debt(): void
    {
        $customer = $this->customer();
        $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 10, 'method' => 'cash'], $this->as($this->owner))
            ->assertStatus(422);

        $this->invoice($this->vehicle($customer), '100');
        $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 101, 'method' => 'cash'], $this->as($this->owner))
            ->assertStatus(422);
    }

    public function test_vehicle_collection_only_settles_that_vehicle(): void
    {
        $customer = $this->customer();
        $first = $this->vehicle($customer);
        $second = $this->vehicle($customer);
        $firstOrder = $this->invoice($first, '300');
        $secondOrder = $this->invoice($second, '300');

        $this->postJson($this->url("vehicles/{$second->id}/payments"), ['amount' => 300, 'method' => 'cash'], $this->as($this->owner))->assertCreated();

        $this->assertSame('0.00', $this->freshOrder($firstOrder)->paid);
        $this->assertSame('300.00', $this->freshOrder($secondOrder)->paid);
    }

    public function test_voiding_a_receipt_restores_the_debt_and_is_owner_only(): void
    {
        $customer = $this->customer();
        $orderId = $this->invoice($this->vehicle($customer), '300');
        $receiptId = $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 300, 'method' => 'cash'], $this->as($this->owner))
            ->json('data.id');

        $this->patchJson($this->url("receipts/{$receiptId}/void"), ['reason' => 'x'], $this->as($this->cashier()))->assertForbidden();
        $this->patchJson($this->url("receipts/{$receiptId}/void"), [], $this->as($this->owner))->assertStatus(422);

        $this->patchJson($this->url("receipts/{$receiptId}/void"), ['reason' => 'مبلغ مسجل بالغلط'], $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.is_voided', true);

        $this->assertSame('0.00', $this->freshOrder($orderId)->paid);
        $this->patchJson($this->url("receipts/{$receiptId}/void"), ['reason' => 'x'], $this->as($this->owner))->assertStatus(409);
        $this->getJson($this->url("customers/{$customer->id}"), $this->as($this->owner))->assertJsonPath('data.debts.total', 300);
    }

    public function test_cancelling_a_paid_invoice_refunds_per_method(): void
    {
        $order = $this->openOrder($this->vehicle(), [['name' => 'x', 'qty' => 1, 'unit_price' => 500]]);
        $this->postJson($this->url("orders/{$order['id']}/complete"), [
            'payments' => [['amount' => 300, 'method' => 'cash'], ['amount' => 100, 'method' => 'instapay']],
        ], $this->as($this->owner))->assertOk();

        $this->postJson($this->url("orders/{$order['id']}/cancel"), ['reason' => 'مرتجع'], $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.paid', 0);

        $this->inTenant($this->tenant);
        $refunds = Payment::query()->where('type', 'refund')->orderBy('method')->pluck('amount', 'method')->all();
        $this->assertSame(['cash' => '300.00', 'instapay' => '100.00'], $refunds);

        $receiptId = Payment::query()->where('type', 'payment')->value('receipt_id');
        $this->patchJson($this->url("receipts/{$receiptId}/void"), ['reason' => 'x'], $this->as($this->owner))->assertStatus(422);
    }

    public function test_opening_balance_counts_as_debt_but_not_as_sales(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson($this->url("vehicles/{$vehicle->id}/opening-balance"), ['amount' => 750, 'notes' => 'من الدفتر'], $this->as($this->cashier()))
            ->assertForbidden();

        $this->postJson($this->url("vehicles/{$vehicle->id}/opening-balance"), ['amount' => 750, 'notes' => 'من الدفتر'], $this->as($this->owner))
            ->assertCreated()
            ->assertJsonPath('data.is_opening_balance', true)
            ->assertJsonPath('data.total', 750);

        $this->getJson($this->url("vehicles/{$vehicle->id}"), $this->as($this->owner))->assertJsonPath('data.balance', 750);
        $this->getJson($this->url('reports/sales'), $this->as($this->owner))->assertJsonPath('data.total', 0);
        $this->getJson($this->url('orders'), $this->as($this->owner))->assertJsonCount(0, 'data');

        $this->postJson($this->url("vehicles/{$vehicle->id}/payments"), ['amount' => 750, 'method' => 'cash'], $this->as($this->owner))->assertCreated();
        $this->getJson($this->url("vehicles/{$vehicle->id}"), $this->as($this->owner))->assertJsonPath('data.balance', 0);
    }

    public function test_receipts_are_numbered_per_branch_and_listed(): void
    {
        $customer = $this->customer();
        $this->invoice($this->vehicle($customer), '500');

        foreach ([100, 150] as $amount) {
            $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => $amount, 'method' => 'cash'], $this->as($this->owner))->assertCreated();
        }

        $this->getJson($this->url('receipts'), $this->as($this->owner))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.receipt_no', 2)
            ->assertJsonPath('data.1.receipt_no', 1);
    }
}
