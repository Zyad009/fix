<?php

namespace Tests\Feature\Center;

use App\Models\CarMake;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\V1\ImageOptimizer;
use Database\Seeders\CarCatalogSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_phone_is_unique_per_maintenance_center_only(): void
    {
        $this->customer('أ', '01011111111');
        [$other] = $this->createMaintenanceCenter('مركز ب');
        $otherOwner = maintenanceCenterContext()->run($other, fn () => User::query()->firstOrFail());

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

        $this->inMaintenanceCenter($this->maintenanceCenter);
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

    public function test_vehicle_is_created_with_a_chassis_image(): void
    {
        Storage::fake('public');

        $response = $this->post($this->url('vehicles'), [
            'customer_id' => $this->customer()->id,
            'plate_number' => 'ب ج 456',
            'vin_image' => UploadedFile::fake()->image('vin.jpg'),
        ], $this->as($this->owner) + ['Accept' => 'application/json']);

        $response->assertCreated();
        $this->assertNotNull($response->json('data.vin_image_url'));

        $path = Vehicle::query()->findOrFail($response->json('data.id'))->vin_image_path;
        $this->assertStringStartsWith('maintenance-centers/'.$this->maintenanceCenter->id.'/vehicles/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_chassis_image_stored_under_the_old_path_still_works_and_is_replaced(): void
    {
        Storage::fake('public');
        $oldPath = 'tenants/'.$this->maintenanceCenter->id.'/vehicles/old.jpg';
        Storage::disk('public')->put($oldPath, 'image');
        $vehicle = $this->vehicle();
        $vehicle->update(['vin_image_path' => $oldPath]);
        $headers = $this->as($this->owner) + ['Accept' => 'application/json'];

        $this->getJson($this->url("vehicles/{$vehicle->id}"), $headers)
            ->assertOk()
            ->assertJsonPath('data.vin_image_url', Storage::disk('public')->url($oldPath));

        $this->post($this->url("vehicles/{$vehicle->id}"), [
            '_method' => 'PUT',
            'vin_image' => UploadedFile::fake()->image('new.jpg'),
        ], $headers)->assertOk();

        Storage::disk('public')->assertMissing($oldPath);
        $this->assertStringStartsWith('maintenance-centers/', $vehicle->fresh()->vin_image_path);
    }

    public function test_vehicle_without_a_chassis_image_stays_valid(): void
    {
        $this->postJson($this->url('vehicles'), [
            'customer_id' => $this->customer()->id,
            'plate_number' => 'ب ج 457',
        ], $this->as($this->owner))
            ->assertCreated()
            ->assertJsonPath('data.vin_image_url', null);
    }

    public function test_updating_replaces_the_chassis_image_and_deletes_the_old_file(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle();
        $headers = fn (): array => $this->as($this->owner) + ['Accept' => 'application/json'];

        $this->post($this->url("vehicles/{$vehicle->id}"), ['_method' => 'PUT', 'vin_image' => UploadedFile::fake()->image('first.jpg')], $headers())->assertOk();
        $first = $vehicle->fresh()->vin_image_path;

        $this->post($this->url("vehicles/{$vehicle->id}"), ['_method' => 'PUT', 'vin_image' => UploadedFile::fake()->image('second.jpg')], $headers())->assertOk();
        $second = $vehicle->fresh()->vin_image_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_updating_other_fields_keeps_the_chassis_image_and_null_removes_it(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle();

        $this->post($this->url("vehicles/{$vehicle->id}"), ['_method' => 'PUT', 'vin_image' => UploadedFile::fake()->image('vin.jpg')], $this->as($this->owner) + ['Accept' => 'application/json'])->assertOk();
        $path = $vehicle->fresh()->vin_image_path;

        $this->putJson($this->url("vehicles/{$vehicle->id}"), ['color' => 'أسود'], $this->as($this->owner))->assertOk();
        Storage::disk('public')->assertExists($path);

        $this->putJson($this->url("vehicles/{$vehicle->id}"), ['vin_image' => null], $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('data.vin_image_url', null);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_large_chassis_image_is_shrunk_and_stored_as_jpeg(): void
    {
        Storage::fake('public');

        $response = $this->post($this->url('vehicles'), [
            'customer_id' => $this->customer()->id,
            'plate_number' => 'ب ج 459',
            'vin_image' => UploadedFile::fake()->image('vin.png', 4000, 3000)->size(11000),
        ], $this->as($this->owner) + ['Accept' => 'application/json'])->assertCreated();

        $path = Vehicle::query()->findOrFail($response->json('data.id'))->vin_image_path;
        $this->assertStringEndsWith('.jpg', $path);

        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame([ImageOptimizer::MAX_SIDE, 1500, IMAGETYPE_JPEG], [$width, $height, $type]);
    }

    public function test_chassis_image_over_12_mb_is_rejected(): void
    {
        Storage::fake('public');

        $this->post($this->url('vehicles'), [
            'customer_id' => $this->customer()->id,
            'plate_number' => 'ب ج 460',
            'vin_image' => UploadedFile::fake()->image('vin.jpg')->size(12289),
        ], $this->as($this->owner) + ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('code', 422);
    }

    public function test_chassis_image_must_be_an_image(): void
    {
        Storage::fake('public');

        $this->post($this->url('vehicles'), [
            'customer_id' => $this->customer()->id,
            'plate_number' => 'ب ج 458',
            'vin_image' => UploadedFile::fake()->create('vin.pdf', 10, 'application/pdf'),
        ], $this->as($this->owner) + ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_chassis_image_of_another_maintenance_centers_vehicle_cannot_be_changed(): void
    {
        Storage::fake('public');
        $vehicle = $this->vehicle();
        [$other] = $this->createMaintenanceCenter('مركز ب');
        $otherOwner = maintenanceCenterContext()->run($other, fn () => User::query()->firstOrFail());

        $this->post($this->url("vehicles/{$vehicle->id}"), ['_method' => 'PUT', 'vin_image' => UploadedFile::fake()->image('vin.jpg')], $this->as($otherOwner) + ['Accept' => 'application/json'])
            ->assertNotFound();

        $this->assertNull($vehicle->fresh()->vin_image_path);
    }
}
