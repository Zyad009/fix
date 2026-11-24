<?php

namespace Tests\Feature\Center;

use App\Models\Role;
use App\Models\User;
use Tests\CenterHttpTestCase;

/**
 * The technical inspection attached to an invoice: a fixed checklist, one
 * result per item, following the invoice's edit rules.
 */
class InspectionTest extends CenterHttpTestCase
{
    private function report(): array
    {
        return [
            'items' => [
                ['item' => 'engine_oil', 'status' => 'ok'],
                ['item' => 'front_pads', 'status' => 'urgent', 'note' => 'التيل خلصان'],
                ['item' => 'battery', 'status' => 'attention'],
            ],
            'diagnostic_result' => 'P0300 - Random misfire',
            'notes' => 'العربية محتاجة متابعة بعد شهر',
        ];
    }

    public function test_the_checklist_lists_every_section_with_labels(): void
    {
        $sections = $this->getJson($this->url('inspection/checklist'), $this->as($this->technician()))->assertOk()->json('data');

        $this->assertSame(['engine', 'transmission', 'brakes', 'suspension', 'electrical', 'tires', 'road_test', 'body'], array_column($sections, 'key'));
        $this->assertSame(['key' => 'engine_oil', 'label' => 'زيت المحرك'], $sections[0]['items'][1]);
    }

    public function test_a_technician_records_an_inspection_and_resending_replaces_it(): void
    {
        $order = $this->openOrder($this->vehicle(), [['name' => 'فحص فني', 'qty' => 1, 'unit_price' => 300]]);
        $technician = $this->technician();
        $headers = $this->as($technician);

        $this->getJson($this->url("orders/{$order['id']}/inspection"), $headers)->assertOk()->assertJsonPath('data', null);

        $saved = $this->putJson($this->url("orders/{$order['id']}/inspection"), $this->report(), $headers)
            ->assertOk()
            ->assertJsonPath('data.summary.ok', 1)
            ->assertJsonPath('data.summary.attention', 1)
            ->assertJsonPath('data.summary.urgent', 1)
            ->assertJsonPath('data.diagnostic_result', 'P0300 - Random misfire')
            ->assertJsonPath('data.inspected_by', $technician->name)
            ->json('data');

        $brakes = collect($saved['sections'])->firstWhere('key', 'brakes');
        $this->assertSame(['key' => 'front_pads', 'label' => 'تيل أمامي', 'status' => ['label' => 'لازم يتغير', 'value' => 'urgent'], 'note' => 'التيل خلصان'], $brakes['items'][1]);
        $this->assertNull($brakes['items'][0]['status']);
        $this->assertSame(55, $saved['summary']['not_checked']);
        $this->assertDatabaseHas('inspection_items', ['item' => 'front_pads', 'label' => 'تيل أمامي', 'section' => 'brakes']);

        $this->putJson($this->url("orders/{$order['id']}/inspection"), ['items' => [['item' => 'clutch', 'status' => 'ok']]], $headers)
            ->assertOk()
            ->assertJsonPath('data.summary', ['ok' => 1, 'attention' => 0, 'urgent' => 0, 'not_checked' => 57])
            ->assertJsonPath('data.diagnostic_result', null);
        $this->assertDatabaseCount('inspection_items', 1);

        $this->getJson($this->url("orders/{$order['id']}"), $headers)->assertOk()->assertJsonPath('data.inspection_summary.ok', 1);

        $this->deleteJson($this->url("orders/{$order['id']}/inspection"), [], $headers)->assertOk();
        $this->assertDatabaseCount('inspections', 0);
        $this->assertDatabaseCount('inspection_items', 0);
        $this->deleteJson($this->url("orders/{$order['id']}/inspection"), [], $headers)->assertNotFound();
    }

    public function test_inspection_input_is_validated(): void
    {
        $headers = $this->as($this->owner);
        $order = $this->openOrder($this->vehicle());
        $url = $this->url("orders/{$order['id']}/inspection");

        $this->putJson($url, ['items' => [['item' => 'flux_capacitor', 'status' => 'ok']]], $headers)->assertStatus(422);
        $this->putJson($url, ['items' => [['item' => 'clutch', 'status' => 'broken']]], $headers)->assertStatus(422);
        $this->putJson($url, ['items' => [['item' => 'clutch', 'status' => 'ok'], ['item' => 'clutch', 'status' => 'urgent']]], $headers)->assertStatus(422);
        $this->putJson($url, [], $headers)->assertStatus(422);
        $this->putJson($url, ['items' => []], $headers)->assertOk()->assertJsonPath('data.summary.not_checked', 58);
    }

    public function test_it_follows_the_invoice_edit_rules(): void
    {
        $order = $this->openOrder($this->vehicle(), [['name' => 'فحص فني', 'qty' => 1, 'unit_price' => 300]]);
        $this->postJson($this->url("orders/{$order['id']}/complete"), [], $this->as($this->owner))->assertOk();

        // A technician cannot even see closed invoices; a cashier sees it but may not edit it.
        $this->putJson($this->url("orders/{$order['id']}/inspection"), $this->report(), $this->as($this->technician()))->assertNotFound();
        $this->putJson($this->url("orders/{$order['id']}/inspection"), $this->report(), $this->as($this->cashier()))->assertForbidden();
        $this->putJson($this->url("orders/{$order['id']}/inspection"), $this->report(), $this->as($this->technicianCashier()))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => 'order', 'auditable_id' => $order['id'], 'event' => 'updated']);

        $cancelled = $this->openOrder($this->vehicle(plate: 'ب ب 2'));
        $this->postJson($this->url("orders/{$cancelled['id']}/cancel"), ['reason' => 'x'], $this->as($this->owner))->assertOk();
        $this->putJson($this->url("orders/{$cancelled['id']}/inspection"), $this->report(), $this->as($this->owner))->assertStatus(422);
    }

    public function test_recording_needs_the_inspect_permission(): void
    {
        $role = Role::query()->create(['key' => 'viewer', 'name' => 'متابع', 'permissions' => ['orders.view']]);
        $viewer = User::factory()->create(['role_id' => $role->id, 'branch_id' => $this->branch->id]);
        $order = $this->openOrder($this->vehicle());

        $this->getJson($this->url("orders/{$order['id']}/inspection"), $this->as($viewer))->assertOk();
        $this->putJson($this->url("orders/{$order['id']}/inspection"), $this->report(), $this->as($viewer))->assertForbidden();
    }

    public function test_inspections_are_isolated_between_maintenance_centers(): void
    {
        [, $otherOwner] = $this->createMaintenanceCenter('مركز ب');
        $order = $this->openOrder($this->vehicle());
        $this->putJson($this->url("orders/{$order['id']}/inspection"), $this->report(), $this->as($this->owner))->assertOk();

        $headers = $this->as($otherOwner);
        $this->getJson($this->url("orders/{$order['id']}/inspection"), $headers)->assertNotFound();
        $this->putJson($this->url("orders/{$order['id']}/inspection"), $this->report(), $headers)->assertNotFound();
        $this->getJson($this->url("orders/{$order['id']}/inspection/print"), $headers)->assertNotFound();
    }

    public function test_the_report_prints_as_json_and_a4_pdf(): void
    {
        $headers = $this->as($this->owner);
        $order = $this->openOrder($this->vehicle(), [], ['odometer' => 85000]);

        $this->getJson($this->url("orders/{$order['id']}/inspection/print"), $headers)->assertNotFound();
        $this->putJson($this->url("orders/{$order['id']}/inspection"), $this->report(), $headers)->assertOk();

        $data = $this->getJson($this->url("orders/{$order['id']}/inspection/print"), $headers)
            ->assertOk()
            ->assertJsonPath('data.invoice.odometer', 85000)
            ->assertJsonPath('data.inspection.summary.urgent', 1)
            ->json('data.inspection.sections');
        $this->assertSame(['المحرك', 'الفرامل', 'الكهرباء والتكييف'], array_column($data, 'label'));

        $response = $this->get($this->url("orders/{$order['id']}/inspection/print?output=pdf"), $headers)->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }
}
