<?php

namespace Tests\Feature\Center;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\ProductBranchStock;
use App\Models\Vehicle;
use App\Services\V1\StockService;
use Tests\CenterHttpTestCase;

/**
 * The "new invoice" screen: customer + vehicle typed inline, catalog search
 * by barcode, and a single save that completes and collects.
 */
class InvoiceScreenTest extends CenterHttpTestCase
{
    public function test_new_customer_and_vehicle_are_created_with_the_order(): void
    {
        $response = $this->postJson($this->url('orders'), [
            'customer' => ['name' => 'محمد علي', 'phone' => '٠١٠ ١٢٣٤ ٥٦٧٨'],
            'vehicle' => ['plate_number' => 'أ ب ج 1234', 'vin' => 'VIN123'],
            'odometer' => 45000,
            'items' => [['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 300]],
        ], $this->as($this->cashier()))->assertCreated();

        $this->inTenant($this->tenant);
        $customer = Customer::query()->where('phone', '01012345678')->firstOrFail();
        $vehicle = Vehicle::query()->where('plate_normalized', 'ابج1234')->firstOrFail();

        $this->assertSame($customer->id, $vehicle->customer_id);
        $response->assertJsonPath('data.order.customer.id', $customer->id)
            ->assertJsonPath('data.order.vehicle.id', $vehicle->id)
            ->assertJsonPath('data.order.status.value', 'open');
    }

    public function test_known_phone_and_plate_are_reused(): void
    {
        $customer = $this->customer('قديم', '01011112222');
        $vehicle = $this->vehicle($customer, 'س ص 55');

        $this->postJson($this->url('orders'), [
            'customer' => ['name' => 'اسم مختلف', 'phone' => '01011112222'],
            'vehicle' => ['plate_number' => 'س ص ٥٥'],
        ], $this->as($this->cashier()))
            ->assertCreated()
            ->assertJsonPath('data.order.vehicle.id', $vehicle->id)
            ->assertJsonPath('data.order.customer.name', 'قديم');

        $this->inTenant($this->tenant);
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(1, Vehicle::query()->count());
    }

    public function test_plate_of_another_customer_is_refused_and_nothing_is_created(): void
    {
        $this->vehicle($this->customer('مالك', '01033334444'), 'ك ل 77');

        $this->postJson($this->url('orders'), [
            'customer' => ['name' => 'جديد', 'phone' => '01055556666'],
            'vehicle' => ['plate_number' => 'ك ل 77'],
        ], $this->as($this->cashier()))->assertStatus(422);

        $this->inTenant($this->tenant);
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame(0, Order::query()->count());
    }

    public function test_technician_must_pick_a_registered_vehicle(): void
    {
        $this->postJson($this->url('orders'), [
            'customer' => ['name' => 'جديد', 'phone' => '01055556666'],
            'vehicle' => ['plate_number' => 'ك ل 77'],
        ], $this->as($this->technician()))->assertForbidden();
    }

    public function test_vehicle_id_and_inline_vehicle_are_validated(): void
    {
        $headers = $this->as($this->cashier());

        $this->postJson($this->url('orders'), [], $headers)->assertStatus(422);
        $this->postJson($this->url('orders'), ['vehicle' => ['plate_number' => 'ق 1']], $headers)->assertStatus(422);
        $this->postJson($this->url('orders'), ['vehicle_id' => $this->vehicle()->id, 'vehicle' => ['plate_number' => 'ق 1'], 'customer' => ['name' => 'x', 'phone' => '01000000001']], $headers)
            ->assertStatus(422);
    }

    public function test_save_completes_and_collects_in_one_call(): void
    {
        $oil = $this->product(['name' => 'زيت محرك', 'sale_price' => '450']);
        $filter = $this->product(['name' => 'فلتر زيت', 'sale_price' => '80']);
        $service = $this->product(['name' => 'خدمة تغيير زيت', 'kind' => 'service', 'sale_price' => '300', 'cost_price' => null, 'track_stock' => false]);
        app(StockService::class)->set($oil, $this->branch->id, '10');

        $this->postJson($this->url('orders'), [
            'customer' => ['name' => 'عميل', 'phone' => '01012345678'],
            'vehicle' => ['plate_number' => 'أ ب 1234'],
            'items' => [
                ['product_id' => $oil->id, 'qty' => 1],
                ['product_id' => $filter->id, 'qty' => 1],
                ['product_id' => $service->id, 'qty' => 1],
            ],
            'discount' => 50,
            'complete' => true,
            'payments' => [['amount' => 300, 'method' => 'cash']],
        ], $this->as($this->owner, $this->branch))
            ->assertCreated()
            ->assertJsonPath('msg', __('messages.order_completed'))
            ->assertJsonPath('data.order.status.value', 'completed')
            ->assertJsonPath('data.order.parts_total', 530)
            ->assertJsonPath('data.order.services_total', 300)
            ->assertJsonPath('data.order.total', 780)
            ->assertJsonPath('data.order.paid', 300)
            ->assertJsonPath('data.order.remaining', 480);

        $this->inTenant($this->tenant);
        $this->assertSame('9.00', ProductBranchStock::query()->where('product_id', $oil->id)->value('qty'));
    }

    public function test_failed_payment_rolls_the_whole_invoice_back(): void
    {
        $this->postJson($this->url('orders'), [
            'customer' => ['name' => 'عميل', 'phone' => '01012345678'],
            'vehicle' => ['plate_number' => 'أ ب 1234'],
            'items' => [['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 100]],
            'complete' => true,
            'payments' => [['amount' => 150, 'method' => 'cash']],
        ], $this->as($this->cashier()))->assertStatus(422);

        $this->inTenant($this->tenant);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(1, $this->branch->refresh()->next_order_no);
    }

    public function test_payments_need_complete_and_technician_cannot_complete(): void
    {
        $vehicle = $this->vehicle();

        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'payments' => [['amount' => 10, 'method' => 'cash']]], $this->as($this->cashier()))
            ->assertStatus(422);

        $this->postJson($this->url('orders'), ['vehicle_id' => $vehicle->id, 'items' => [['name' => 'x', 'qty' => 1, 'unit_price' => 10]], 'complete' => true], $this->as($this->technician()))
            ->assertForbidden();
    }

    public function test_pos_catalog_finds_products_by_barcode_or_name_and_shows_next_number(): void
    {
        $oil = Category::query()->where('name', 'زيوت')->firstOrFail();
        $product = $this->product(['category_id' => $oil->id, 'name' => 'زيت 5W30', 'barcode' => '6221234567890']);
        $this->product(['category_id' => $oil->id, 'name' => 'زيت 10W40']);
        $headers = $this->as($this->cashier());

        $this->getJson($this->url('pos/catalog?q=6221234567890'), $headers)
            ->assertOk()
            ->assertJsonPath('data.next_order_no', 1)
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonCount(1, 'data.categories.0.products')
            ->assertJsonPath('data.categories.0.products.0.id', $product->id)
            ->assertJsonPath('data.categories.0.products.0.barcode', '6221234567890');

        $this->getJson($this->url('pos/catalog?q=زيت'), $headers)->assertJsonCount(2, 'data.categories.0.products');

        $this->inTenant($this->tenant);
        $this->openOrder($this->vehicle());
        $this->getJson($this->url('pos/catalog'), $headers)->assertJsonPath('data.next_order_no', 2);
    }

    public function test_barcode_is_unique_within_a_center_only(): void
    {
        $headers = $this->as($this->owner);
        $payload = ['name' => 'فلتر', 'kind' => 'part', 'sale_price' => 80, 'barcode' => '111'];

        $id = $this->postJson($this->url('products'), $payload, $headers)->assertCreated()->json('data.id');
        $this->postJson($this->url('products'), $payload, $headers)->assertStatus(422);
        $this->putJson($this->url("products/{$id}"), ['barcode' => '111'], $headers)->assertOk();

        $this->getJson($this->url('products?q=111'), $headers)->assertJsonPath('data.0.id', $id);

        [, $otherOwner] = $this->createTenant('مركز ب');
        $this->postJson($this->url('products'), $payload, $this->as($otherOwner))->assertCreated();
    }

    public function test_inline_ids_of_another_tenant_are_rejected(): void
    {
        [$otherTenant] = $this->createTenant('مركز ب');
        $foreignCustomer = tenantContext()->run($otherTenant, fn () => $this->customer('غريب', '01077778888'));
        $this->inTenant($this->tenant);

        $this->postJson($this->url('orders'), [
            'customer' => ['id' => $foreignCustomer->id],
            'vehicle' => ['plate_number' => 'م ن 9'],
        ], $this->as($this->cashier()))->assertStatus(422);

        $this->inTenant($this->tenant);
        $this->assertSame(0, Vehicle::query()->count());
    }
}
