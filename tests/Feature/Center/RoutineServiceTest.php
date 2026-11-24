<?php

namespace Tests\Feature\Center;

use App\Models\RoutineService;
use Database\Seeders\DefaultRoutineServicesSeeder;
use Tests\CenterHttpTestCase;

/**
 * Routine services: name + active only, ordered by usage, with a suggested
 * price taken from the prices typed on past invoices.
 */
class RoutineServiceTest extends CenterHttpTestCase
{
    private function service(string $name = 'تغيير زيت المحرك'): RoutineService
    {
        $this->inMaintenanceCenter($this->maintenanceCenter);

        return RoutineService::query()->where('name', $name)->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    private function line(RoutineService $service, string $price, int $qty = 1): array
    {
        return ['routine_service_id' => $service->id, 'qty' => $qty, 'unit_price' => $price];
    }

    public function test_a_new_center_gets_the_default_routine_services(): void
    {
        $data = $this->getJson($this->url('routine-services'), $this->as($this->owner))->assertOk()->json('data');

        $this->assertCount(count(RoutineService::DEFAULTS), $data);
        $this->assertEqualsCanonicalizing(RoutineService::DEFAULTS, array_column($data, 'name'));
        $this->assertSame(0, $data[0]['usage_count']);
        $this->assertNull($data[0]['suggested_price']);
        $this->assertNull($data[0]['last_used_at']);
    }

    public function test_the_catalog_manager_creates_renames_and_toggles_services(): void
    {
        $headers = $this->as($this->owner);

        $id = $this->postJson($this->url('routine-services'), ['name' => 'غسيل موتور'], $headers)
            ->assertCreated()
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->postJson($this->url('routine-services'), ['name' => 'غسيل موتور'], $headers)->assertStatus(422);
        $this->putJson($this->url("routine-services/{$id}"), ['name' => 'غسيل المحرك'], $headers)->assertOk()->assertJsonPath('data.name', 'غسيل المحرك');
        $this->patchJson($this->url("routine-services/toggle/{$id}"), [], $headers)->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson($this->url('routine-services?is_active=0'), $headers)->assertOk()->assertJsonCount(1, 'data');

        $cashier = $this->as($this->cashier());
        $this->getJson($this->url('routine-services'), $cashier)->assertOk();
        $this->postJson($this->url('routine-services'), ['name' => 'خدمة'], $cashier)->assertForbidden();
        $this->putJson($this->url("routine-services/{$id}"), ['name' => 'خدمة'], $cashier)->assertForbidden();
    }

    public function test_an_invoice_line_snapshots_the_service_and_takes_a_typed_price(): void
    {
        $service = $this->service();
        $order = $this->openOrder($this->vehicle(), [$this->line($service, '450')], as: $this->cashier());

        $item = $order['items'][0];
        $this->assertSame($service->id, $item['routine_service_id']);
        $this->assertFalse($item['is_free_item']);
        $this->assertSame('تغيير زيت المحرك', $item['name']);
        $this->assertSame('service', $item['kind']['value']);
        $this->assertEquals(450, $item['unit_price']);

        // The name stays the service's; price and quantity can change.
        $headers = $this->as($this->owner);
        $this->putJson($this->url("orders/{$order['id']}/items/{$item['id']}"), ['name' => 'اسم تاني', 'unit_price' => 500], $headers)
            ->assertOk()
            ->assertJsonPath('data.order.items.0.name', 'تغيير زيت المحرك')
            ->assertJsonPath('data.order.items.0.unit_price', 500);

        $url = $this->url("orders/{$order['id']}/items");
        $this->postJson($url, ['routine_service_id' => $service->id, 'qty' => 1], $headers)->assertStatus(422);
        $this->postJson($url, [...$this->line($service, '100'), 'product_id' => $this->product()->id], $headers)->assertStatus(422);

        $this->patchJson($this->url("routine-services/toggle/{$service->id}"), [], $headers)->assertOk();
        $this->postJson($url, $this->line($service, '100'), $headers)->assertStatus(422);
    }

    public function test_services_are_ordered_by_usage_and_ignore_cancelled_invoices(): void
    {
        $oil = $this->service();
        $tires = $this->service('تركيب كاوتش');

        $this->openOrder($this->vehicle(), [$this->line($tires, '50')]);
        $this->openOrder($this->vehicle(), [$this->line($tires, '50'), $this->line($tires, '50')]);
        $this->openOrder($this->vehicle(), [$this->line($oil, '500')]);
        $cancelled = $this->openOrder($this->vehicle(), [$this->line($oil, '500'), $this->line($oil, '500')]);
        $this->postJson($this->url("orders/{$cancelled['id']}/cancel"), ['reason' => 'x'], $this->as($this->owner))->assertOk();

        $data = $this->getJson($this->url('routine-services'), $this->as($this->owner))->assertOk()->json('data');

        $this->assertSame(['تركيب كاوتش', 'تغيير زيت المحرك'], [$data[0]['name'], $data[1]['name']]);
        $this->assertSame([3, 1], [$data[0]['usage_count'], $data[1]['usage_count']]);
        $this->assertIsInt($data[0]['last_used_at']);
    }

    public function test_the_suggested_price_is_the_most_frequent_recent_price(): void
    {
        $oil = $this->service();
        $vehicle = $this->vehicle();

        // Old prices (outside the 3-month window) never beat recent ones.
        $this->travelTo(now()->subMonths(5));
        foreach (['400', '400', '400'] as $price) {
            $this->openOrder($vehicle, [$this->line($oil, $price)]);
        }
        $this->travelBack();

        $this->openOrder($vehicle, [$this->line($oil, '500')]);
        $this->openOrder($vehicle, [$this->line($oil, '450')]);
        $this->openOrder($vehicle, [$this->line($oil, '500')]);

        $headers = $this->as($this->owner);
        $this->getJson($this->url("routine-services/{$oil->id}"), $headers)
            ->assertOk()
            ->assertJsonPath('data.suggested_price', 500)
            ->assertJsonPath('data.usage_count', 6)
            ->assertJsonPath('data.prices', [['price' => 500, 'uses' => 2], ['price' => 450, 'uses' => 1]]);

        // A tie goes to the newest price.
        $this->travelTo(now()->addMinute());
        $this->openOrder($vehicle, [$this->line($oil, '450')]);
        $this->getJson($this->url("routine-services/{$oil->id}"), $headers)->assertJsonPath('data.suggested_price', 450);
    }

    public function test_without_recent_use_the_last_price_is_suggested(): void
    {
        $oil = $this->service();
        $this->travelTo(now()->subMonths(6));
        $this->openOrder($this->vehicle(), [$this->line($oil, '300')]);
        $this->travelTo(now()->addDay());
        $this->openOrder($this->vehicle(), [$this->line($oil, '350')]);
        $this->travelBack();

        $data = collect($this->getJson($this->url('routine-services'), $this->as($this->owner))->json('data'))->keyBy('name');

        $this->assertEquals(350, $data['تغيير زيت المحرك']['suggested_price']);
        $this->assertNull($data['تركيب كاوتش']['suggested_price']);
    }

    public function test_a_used_service_can_never_be_deleted(): void
    {
        $headers = $this->as($this->owner);
        $used = $this->service();
        $order = $this->openOrder($this->vehicle(), [$this->line($used, '500')]);
        $this->postJson($this->url("orders/{$order['id']}/cancel"), ['reason' => 'x'], $headers)->assertOk();

        $this->deleteJson($this->url("routine-services/{$used->id}"), [], $headers)->assertStatus(409);

        $unused = $this->service('تركيب كاوتش');
        $this->deleteJson($this->url("routine-services/{$unused->id}"), [], $headers)->assertOk();
        $this->assertSoftDeleted('routine_services', ['id' => $unused->id]);
        $this->getJson($this->url("routine-services/{$unused->id}"), $headers)->assertNotFound();

        // The name is free again once deleted.
        $this->postJson($this->url('routine-services'), ['name' => 'تركيب كاوتش'], $headers)->assertCreated();
    }

    public function test_the_seeder_adds_missing_defaults_without_restoring_deleted_ones(): void
    {
        $this->inMaintenanceCenter($this->maintenanceCenter);
        RoutineService::query()->where('name', 'تركيب كاوتش')->firstOrFail()->delete();
        RoutineService::query()->where('name', 'ظبط زوايا')->forceDelete();
        RoutineService::query()->where('name', 'فحص فني')->update(['name' => 'فحص شامل']);

        $this->seed(DefaultRoutineServicesSeeder::class);
        $this->seed(DefaultRoutineServicesSeeder::class);

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $names = RoutineService::query()->pluck('name')->all();
        $this->assertNotContains('تركيب كاوتش', $names);
        $this->assertContains('فحص شامل', $names);
        $this->assertSame(1, RoutineService::query()->where('name', 'ظبط زوايا')->count());
        $this->assertSame(1, RoutineService::query()->where('name', 'فحص فني')->count());
        $this->assertCount(count(RoutineService::DEFAULTS), $names);
    }

    public function test_the_history_lists_usage_across_branches(): void
    {
        $oil = $this->service();
        $branch = $this->createBranch();
        $first = $this->openOrder($this->vehicle(), [$this->line($oil, '500')]);
        $second = $this->postJson($this->url('orders'), ['vehicle_id' => $this->vehicle()->id, 'items' => [$this->line($oil, '450', 2)]], $this->as($this->owner, $branch))
            ->assertCreated()
            ->json('data.order');
        $cancelled = $this->openOrder($this->vehicle(), [$this->line($oil, '100')]);
        $this->postJson($this->url("orders/{$cancelled['id']}/cancel"), ['reason' => 'x'], $this->as($this->owner))->assertOk();

        $rows = $this->getJson($this->url("routine-services/{$oil->id}/history"), $this->as($this->owner))
            ->assertOk()
            ->assertJsonPath('pagination.total', 2)
            ->json('data');

        $this->assertSame([$second['id'], $first['id']], array_column($rows, 'order_id'));
        $this->assertSame('فرع 2', $rows[0]['branch']);
        $this->assertEquals([2, 450], [$rows[0]['qty'], $rows[0]['unit_price']]);
        $this->assertSame('open', $rows[0]['status']['value']);

        $this->getJson($this->url("routine-services/{$oil->id}/history"), $this->as($this->technician()))->assertForbidden();
    }

    public function test_routine_services_are_isolated_between_maintenance_centers(): void
    {
        [, $otherOwner] = $this->createMaintenanceCenter('مركز ب');
        $oil = $this->service();
        $this->openOrder($this->vehicle(), [$this->line($oil, '500')]);

        $headers = $this->as($otherOwner);
        $this->getJson($this->url("routine-services/{$oil->id}"), $headers)->assertNotFound();
        $this->getJson($this->url("routine-services/{$oil->id}/history"), $headers)->assertNotFound();
        $this->putJson($this->url("routine-services/{$oil->id}"), ['name' => 'x'], $headers)->assertNotFound();
        $this->deleteJson($this->url("routine-services/{$oil->id}"), [], $headers)->assertNotFound();
        $this->patchJson($this->url("routine-services/toggle/{$oil->id}"), [], $headers)->assertNotFound();

        // Another center's service id fails the scoped `exists` rule on an invoice line.
        $this->postJson($this->url('orders'), [
            'vehicle' => ['plate_number' => 'ب ب 9'],
            'customer' => ['name' => 'عميل', 'phone' => '01099999999'],
            'items' => [$this->line($oil, '500')],
        ], $headers)->assertStatus(422);

        $data = $this->getJson($this->url('routine-services'), $headers)->json('data');
        $this->assertSame(0, collect($data)->sum('usage_count'));
    }
}
