<?php

namespace Tests\Feature\Center;

use App\Models\CarMake;
use App\Models\Customer;
use App\Models\User;
use Database\Seeders\CarCatalogSeeder;
use Tests\CenterHttpTestCase;

class CustomerVehicleTest extends CenterHttpTestCase
{
    public function test_create_customer_with_inline_vehicles(): void
    {
        $this->seed(CarCatalogSeeder::class);
        $make = CarMake::query()->where('name_ar', 'هيونداي')->firstOrFail();
        $model = $make->models()->firstOrFail();

        $response = $this->postJson($this->url('customers'), [
            'name' => 'محمد علي',
            'phone' => '٠١٠ ١٢٣-٤٥٦٧٨',
            'vehicles' => [
                ['plate_number' => 'س ص ع 123', 'car_make_id' => $make->id, 'car_model_id' => $model->id, 'year' => 2018],
                ['plate_number' => 'ب ج 77'],
            ],
        ], $this->as($this->cashier()));

        $response->assertCreated()
            ->assertJsonPath('data.phone', '01012345678')
            ->assertJsonCount(2, 'data.vehicles')
            ->assertJsonPath('data.debts.total', 0);
    }

    public function test_phone_is_unique_per_tenant_only(): void
    {
        $this->customer('أ', '01011111111');
        [$other] = $this->createTenant('مركز ب');
        $otherOwner = tenantContext()->run($other, fn () => User::query()->firstOrFail());

        $this->postJson($this->url('customers'), ['name' => 'ب', 'phone' => '010 1111 1111'], $this->as($this->owner))->assertStatus(422);
        $this->postJson($this->url('customers'), ['name' => 'ب', 'phone' => '01011111111'], $this->as($otherOwner))->assertCreated();
    }

    public function test_plate_is_normalized_unique_and_searchable_in_any_format(): void
    {
        $vehicle = $this->vehicle(plate: 'س ص ع 123');

        $this->postJson($this->url('vehicles'), [
            'customer_id' => $this->customer()->id,
            'plate_number' => 'سصع-١٢٣',
        ], $this->as($this->owner))->assertStatus(422);

        foreach (['123 س ص ع', 'سصع ١٢٣', '١٢٣'] as $term) {
            $this->getJson($this->url('search?q='.urlencode($term)), $this->as($this->owner))
                ->assertOk()
                ->assertJsonPath('data.vehicles.0.id', $vehicle->id);
        }
    }

    public function test_search_finds_customers_by_phone_and_name(): void
    {
        $customer = $this->customer('أحمد سمير', '01099998888');

        $this->getJson($this->url('search?q=9999'), $this->as($this->owner))->assertJsonPath('data.customers.0.id', $customer->id);
        $this->getJson($this->url('search?q='.urlencode('سمير')), $this->as($this->owner))->assertJsonPath('data.customers.0.id', $customer->id);
    }

    public function test_model_must_belong_to_make(): void
    {
        $this->seed(CarCatalogSeeder::class);
        $kia = CarMake::query()->where('name_ar', 'كيا')->firstOrFail();
        $hyundaiModel = CarMake::query()->where('name_ar', 'هيونداي')->firstOrFail()->models()->firstOrFail();

        $this->postJson($this->url('vehicles'), [
            'customer_id' => $this->customer()->id,
            'plate_number' => 'ط ط 1',
            'car_make_id' => $kia->id,
            'car_model_id' => $hyundaiModel->id,
        ], $this->as($this->owner))->assertStatus(422);
    }

    public function test_vehicle_page_shows_last_visit_next_service_and_debt(): void
    {
        $vehicle = $this->vehicle();
        $order = $this->openOrder($vehicle, [['name' => 'تغيير زيت', 'qty' => 1, 'unit_price' => 300]], [
            'odometer' => 45000,
            'next_service_odometer' => 50000,
            'next_service_date' => '2027-01-01',
        ]);
        $this->postJson($this->url("orders/{$order['id']}/complete"), ['payments' => [['amount' => 100, 'method' => 'cash']]], $this->as($this->owner))->assertOk();

        $this->getJson($this->url("vehicles/{$vehicle->id}"), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.last_odometer', 45000)
            ->assertJsonPath('data.next_service_odometer', 50000)
            ->assertJsonPath('data.next_service_date', '2027-01-01')
            ->assertJsonPath('data.balance', 200);

        $this->getJson($this->url("vehicles/{$vehicle->id}/history"), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.0.odometer', 45000);
    }

    public function test_transferring_ownership_keeps_old_debt_with_old_owner(): void
    {
        $oldOwner = $this->customer('القديم');
        $newOwner = $this->customer('الجديد');
        $vehicle = $this->vehicle($oldOwner);
        $this->invoice($vehicle, '300');

        $this->patchJson($this->url("vehicles/{$vehicle->id}/transfer-owner"), ['customer_id' => $newOwner->id], $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.customer.id', $newOwner->id)
            ->assertJsonPath('data.balance', 0);

        $this->getJson($this->url("customers/{$oldOwner->id}"), $this->as($this->owner))->assertJsonPath('data.debts.total', 300);
        $this->getJson($this->url("customers/{$newOwner->id}"), $this->as($this->owner))->assertJsonPath('data.debts.total', 0);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'vehicle', 'event' => 'owner_transferred']);
    }

    public function test_customer_with_debt_cannot_be_deleted_and_deleted_phone_is_reusable(): void
    {
        $debtor = $this->customer('مدين', '01055555555');
        $this->invoice($this->vehicle($debtor), '150');

        $this->deleteJson($this->url("customers/{$debtor->id}"), [], $this->as($this->owner))->assertStatus(422);

        $clean = $this->customer('سليم', '01066666666');
        $this->vehicle($clean);
        $this->deleteJson($this->url("customers/{$clean->id}"), [], $this->as($this->owner))->assertOk();

        $this->inTenant($this->tenant);
        $this->assertSoftDeleted($clean);
        $this->assertSame(0, $clean->vehicles()->count());

        $this->postJson($this->url('customers'), ['name' => 'جديد', 'phone' => '01066666666'], $this->as($this->owner))->assertCreated();
    }

    public function test_customer_list_includes_balance_and_debt_filter(): void
    {
        $debtor = $this->customer('مدين');
        $this->invoice($this->vehicle($debtor), '250');
        $this->customer('خالص');

        $this->getJson($this->url('customers?has_debt=1'), $this->as($this->owner))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $debtor->id)
            ->assertJsonPath('data.0.balance', 250);
    }

    public function test_technician_can_view_but_not_manage_customers(): void
    {
        $technician = $this->technician();

        $this->getJson($this->url('customers'), $this->as($technician))->assertOk();
        $this->postJson($this->url('customers'), ['name' => 'x', 'phone' => '01012121212'], $this->as($technician))->assertForbidden();
    }

    public function test_customers_export_to_excel(): void
    {
        $this->customer();

        $this->get($this->url('customers?format=excel'), $this->as($this->owner))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->assertSame(1, Customer::query()->count());
    }
}
