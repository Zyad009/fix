<?php

namespace Tests\Feature\Center;

use App\Models\Order;
use App\Models\ProductBranchStock;
use App\Services\V1\StockService;
use Tests\CenterHttpTestCase;

/**
 * brief §4.4 — the POS flow and its money rules.
 */
class OrderTest extends CenterHttpTestCase
{
    public function test_full_pos_flow_computes_totals(): void
    {
        $vehicle = $this->vehicle();
        $oil = $this->product(['name' => 'زيت', 'sale_price' => '450', 'cost_price' => '380']);
        $filter = $this->product(['name' => 'فلتر', 'sale_price' => '120.50', 'track_stock' => false]);

        $order = $this->openOrder($vehicle, [
            ['product_id' => $oil->id, 'qty' => 1],
            ['product_id' => $filter->id, 'qty' => 2],
            ['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 100],
        ], ['odometer' => 52000, 'discount' => 41]);

        $this->assertSame(791, $order['subtotal']);
        $this->assertSame(41, $order['discount']);
        $this->assertSame(750, $order['total']);
        $this->assertSame(0, $order['paid']);
        $this->assertSame(1, $order['order_no']);
        $this->assertSame($vehicle->customer_id, $order['customer']['id']);

        $complete = $this->postJson($this->url("orders/{$order['id']}/complete"), [
            'payments' => [['amount' => 500, 'method' => 'cash'], ['amount' => 100, 'method' => 'instapay']],
        ], $this->as($this->owner))->assertOk();

        $complete->assertJsonPath('data.order.status.value', 'completed')
            ->assertJsonPath('data.order.paid', 600)
            ->assertJsonPath('data.order.remaining', 150);

        $this->inTenant($this->tenant);
        $vehicle->refresh();
        $this->assertSame(52000, $vehicle->last_odometer);
        $this->assertNotNull($vehicle->last_visit_at);
    }

    public function test_adding_updating_and_removing_items_recalculates_totals(): void
    {
        $order = $this->openOrder($this->vehicle(), [['name' => 'بند 1', 'qty' => 2, 'unit_price' => 50]]);
        $headers = $this->as($this->owner);

        $added = $this->postJson($this->url("orders/{$order['id']}/items"), ['name' => 'بند 2', 'qty' => 1.5, 'unit_price' => 20], $headers)
            ->assertCreated()
            ->assertJsonPath('data.order.subtotal', 130)
            ->json('data.order.items');

        $this->putJson($this->url("orders/{$order['id']}/items/{$added[1]['id']}"), ['qty' => 3], $headers)
            ->assertOk()
            ->assertJsonPath('data.order.subtotal', 160);

        $this->deleteJson($this->url("orders/{$order['id']}/items/{$added[0]['id']}"), [], $headers)
            ->assertOk()
            ->assertJsonPath('data.subtotal', 60)
            ->assertJsonPath('data.total', 60);
    }

    public function test_item_of_another_order_cannot_be_reached_through_this_order(): void
    {
        $first = $this->openOrder($this->vehicle(), [['name' => 'x', 'qty' => 1, 'unit_price' => 10]]);
        $second = $this->openOrder($this->vehicle(), [['name' => 'y', 'qty' => 1, 'unit_price' => 10]]);

        $this->deleteJson($this->url("orders/{$first['id']}/items/{$second['items'][0]['id']}"), [], $this->as($this->owner))->assertNotFound();
    }

    public function test_invoice_lines_are_snapshots(): void
    {
        $product = $this->product(['name' => 'زيت قديم', 'sale_price' => '400', 'cost_price' => '300']);
        $orderId = $this->invoice($this->vehicle(), '0');
        $this->postJson($this->url('orders'), ['vehicle_id' => $this->vehicle()->id, 'items' => [['product_id' => $product->id, 'qty' => 1]]], $this->as($this->owner));

        $this->putJson($this->url("products/{$product->id}"), ['name' => 'زيت جديد', 'sale_price' => 999, 'cost_price' => 800], $this->as($this->owner))->assertOk();

        $this->inTenant($this->tenant);
        $order = Order::query()->whereKeyNot($orderId)->with('items')->firstOrFail();

        $this->assertSame('زيت قديم', $order->items[0]->name);
        $this->assertSame('400.00', $order->items[0]->unit_price);
        $this->assertSame('300.00', $order->items[0]->unit_cost);
        $this->assertSame('400.00', $order->total);
    }

    public function test_discount_rules_by_role(): void
    {
        $this->tenant->update(['cashier_max_discount' => 50]);
        $cashier = $this->cashier();
        $technician = $this->technician();
        $vehicle = $this->vehicle();
        $line = [['name' => 'خدمة', 'qty' => 1, 'unit_price' => 1000]];

        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => $line, 'discount' => 60], $this->as($cashier))->assertStatus(422);
        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => $line, 'discount' => 50], $this->as($cashier))->assertCreated();
        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => $line, 'discount' => 10], $this->as($technician))->assertForbidden();
        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => $line, 'discount' => 500], $this->as($this->owner))->assertCreated();
        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => $line, 'discount' => 1001], $this->as($this->owner))->assertStatus(422);
    }

    public function test_only_permitted_roles_override_catalog_prices(): void
    {
        $product = $this->product(['sale_price' => '100']);
        $vehicle = $this->vehicle();

        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 80]]], $this->as($this->technician()))
            ->assertForbidden();

        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => [['name' => 'قطعة من بره', 'qty' => 1, 'unit_price' => 80]]], $this->as($this->technician()))
            ->assertCreated();

        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_price' => 80]]], $this->as($this->cashier()))
            ->assertCreated()
            ->assertJsonPath('data.order.total', 80);
    }

    public function test_lower_odometer_reading_warns_but_does_not_block(): void
    {
        $vehicle = $this->vehicle();
        $first = $this->openOrder($vehicle, [['name' => 'x', 'qty' => 1, 'unit_price' => 10]], ['odometer' => 80000]);
        $this->postJson($this->url("orders/{$first['id']}/complete"), [], $this->as($this->owner))->assertOk();

        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'odometer' => 1000], $this->as($this->owner))
            ->assertCreated()
            ->assertJsonCount(1, 'data.warnings');
    }

    public function test_order_numbers_are_sequential_per_branch(): void
    {
        $second = $this->createBranch();
        $vehicle = $this->vehicle();

        $numbers = [
            $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id], $this->as($this->owner, $this->branch))->json('data.order.order_no'),
            $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id], $this->as($this->owner, $this->branch))->json('data.order.order_no'),
            $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id], $this->as($this->owner, $second))->json('data.order.order_no'),
        ];

        $this->assertSame([1, 2, 1], $numbers);
    }

    public function test_owner_must_pick_a_branch_when_there_are_several(): void
    {
        $this->createBranch();

        $this->postJson($this->url('orders'), ['vehicle_id' => $this->vehicle()->id], $this->as($this->owner))
            ->assertStatus(422)
            ->assertJsonPath('code', 'branch_required');
    }

    public function test_empty_order_cannot_be_completed(): void
    {
        $order = $this->openOrder($this->vehicle());

        $this->postJson($this->url("orders/{$order['id']}/complete"), [], $this->as($this->owner))->assertStatus(422);
    }

    public function test_stock_leaves_on_completion_returns_on_cancel_and_warns_when_short(): void
    {
        $product = $this->product();
        app(StockService::class)->set($product, $this->branch->id, '1');

        $order = $this->openOrder($this->vehicle(), [['product_id' => $product->id, 'qty' => 3]]);
        $this->assertSame('1.00', $this->stockOf($product->id));

        $this->postJson($this->url("orders/{$order['id']}/complete"), [], $this->as($this->owner))
            ->assertOk()
            ->assertJsonCount(1, 'data.warnings');
        $this->assertSame('-2.00', $this->stockOf($product->id));

        $this->postJson($this->url("orders/{$order['id']}/cancel"), ['reason' => 'خطأ'], $this->as($this->owner))->assertOk();
        $this->assertSame('1.00', $this->stockOf($product->id));
    }

    public function test_completed_invoice_is_locked_for_cashier_and_editable_by_owner(): void
    {
        $product = $this->product();
        $cashier = $this->cashier();
        $order = $this->openOrder($this->vehicle(), [['product_id' => $product->id, 'qty' => 1]], as: $cashier);
        $this->postJson($this->url("orders/{$order['id']}/complete"), [], $this->as($cashier))->assertOk();
        $itemId = $order['items'][0]['id'];

        $this->putJson($this->url("orders/{$order['id']}/items/{$itemId}"), ['qty' => 2], $this->as($cashier))->assertForbidden();
        $this->putJson($this->url("orders/{$order['id']}"), ['notes' => 'x'], $this->as($cashier))->assertForbidden();
        $this->postJson($this->url("orders/{$order['id']}/cancel"), ['reason' => 'x'], $this->as($cashier))->assertForbidden();

        $this->putJson($this->url("orders/{$order['id']}/items/{$itemId}"), ['qty' => 3], $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.order.total', 300);

        $this->assertSame('-3.00', $this->stockOf($product->id));
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'order', 'auditable_id' => $order['id'], 'event' => 'item_updated']);
    }

    public function test_total_cannot_drop_below_what_was_paid(): void
    {
        $order = $this->openOrder($this->vehicle(), [['name' => 'x', 'qty' => 1, 'unit_price' => 500]]);
        $this->postJson($this->url("orders/{$order['id']}/payments"), ['amount' => 400, 'method' => 'cash'], $this->as($this->owner))->assertCreated();

        $this->putJson($this->url("orders/{$order['id']}"), ['discount' => 200], $this->as($this->owner))->assertStatus(422);
        $this->putJson($this->url("orders/{$order['id']}"), ['discount' => 100], $this->as($this->owner))->assertOk()->assertJsonPath('data.order.total', 400);
    }

    public function test_cancelling_a_completed_invoice_needs_a_reason(): void
    {
        $orderId = $this->invoice($this->vehicle(), '100');

        $this->postJson($this->url("orders/{$orderId}/cancel"), [], $this->as($this->owner))->assertStatus(422);
        $this->postJson($this->url("orders/{$orderId}/cancel"), ['reason' => 'العميل رجع'], $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled');

        $this->putJson($this->url("orders/{$orderId}"), ['notes' => 'x'], $this->as($this->owner))->assertStatus(422);
    }

    public function test_technician_sees_only_open_orders_of_their_branch_without_costs(): void
    {
        $technician = $this->technician();
        $product = $this->product();
        $open = $this->openOrder($this->vehicle(), [['product_id' => $product->id, 'qty' => 1]]);
        $closedId = $this->invoice($this->vehicle(), '100');

        $this->getJson($this->url('orders'), $this->as($technician))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $open['id']);

        $this->getJson($this->url("orders/{$closedId}"), $this->as($technician))->assertNotFound();
        $this->getJson($this->url("orders/{$open['id']}"), $this->as($technician))
            ->assertOk()
            ->assertJsonMissingPath('data.items.0.unit_cost');
    }

    public function test_cashier_only_sees_orders_of_their_branch(): void
    {
        $second = $this->createBranch();
        $this->openOrder($this->vehicle());
        $otherBranchOrder = $this->postJson($this->url('orders'), ['vehicle_id' => $this->vehicle()->id], $this->as($this->owner, $second))->json('data.order');

        $cashier = $this->cashier();

        $this->getJson($this->url('orders'), $this->as($cashier))->assertJsonCount(1, 'data');
        $this->getJson($this->url("orders/{$otherBranchOrder['id']}"), $this->as($cashier))->assertNotFound();
        $this->getJson($this->url('orders'), $this->as($this->owner))->assertJsonCount(2, 'data');
    }

    public function test_technician_must_be_an_active_employee_of_the_branch(): void
    {
        $employee = $this->employee();
        $otherBranchEmployee = $this->employee($this->createBranch());

        $this->postJson($this->url('orders'), ['vehicle_id' => $this->vehicle()->id, 'technician_id' => $otherBranchEmployee->id], $this->as($this->owner, $this->branch))
            ->assertStatus(422);

        $this->postJson($this->url('orders'), ['vehicle_id' => $this->vehicle()->id, 'technician_id' => $employee->id], $this->as($this->owner, $this->branch))
            ->assertCreated()
            ->assertJsonPath('data.order.technician.id', $employee->id);
    }

    private function stockOf(int $productId): string
    {
        $this->inTenant($this->tenant);

        return ProductBranchStock::query()->where('product_id', $productId)->value('qty');
    }
}
