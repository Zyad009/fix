<?php

namespace Tests\Feature\Center;

use App\Enums\WhatsappMessageStatus;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\Role;
use App\Models\User;
use App\Models\WhatsappMessage;
use App\Services\V1\BalanceService;
use App\Services\V1\DocumentImageService;
use App\Services\V1\GowaWhatsappService;
use App\Services\V1\InvoiceChangeService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\CenterHttpTestCase;

/**
 * The technical inspection report sent to the customer as a PDF on WhatsApp:
 * automatically with the completed invoice, or on demand.
 */
class InspectionWhatsappTest extends CenterHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->maintenanceCenter->update(['whatsapp_device_id' => 'device-1']);
    }

    /**
     * An open order of the customer 01012345678 with a recorded inspection.
     *
     * @return array<string, mixed>
     */
    private function inspectedOrder(): array
    {
        $order = $this->openOrder($this->vehicle($this->customer('عميل', '01012345678')), [['name' => 'فحص فني', 'qty' => 1, 'unit_price' => 300]]);

        $this->putJson($this->url("orders/{$order['id']}/inspection"), [
            'items' => [['item' => 'engine_oil', 'status' => 'ok'], ['item' => 'front_pads', 'status' => 'urgent', 'note' => 'التيل خلصان']],
            'diagnostic_result' => 'P0300',
        ], $this->as($this->owner, $this->branch))->assertOk();

        return $order;
    }

    /**
     * @return list<string>
     */
    private function jobTypes(): array
    {
        return Queue::pushed(SendWhatsappMessageJob::class)->map(fn (SendWhatsappMessageJob $job): string => $job->type)->values()->all();
    }

    private function inspectionJob(): SendWhatsappMessageJob
    {
        return Queue::pushed(SendWhatsappMessageJob::class)->first(fn (SendWhatsappMessageJob $job): bool => $job->type === 'inspection');
    }

    public function test_completing_an_inspected_invoice_also_queues_the_report(): void
    {
        $order = $this->inspectedOrder();
        $this->postJson($this->url("orders/{$order['id']}/complete"), [], $this->as($this->owner, $this->branch))->assertOk();

        $this->assertSame(['invoice', 'inspection'], $this->jobTypes());
        $this->assertSame('201012345678', $this->inspectionJob()->recipient);

        // An invoice without an inspection sends only the invoice.
        $this->invoice($this->vehicle($this->customer('عميل 2', '01112345678')), '100');
        $this->assertSame(['invoice', 'inspection', 'invoice'], $this->jobTypes());
    }

    public function test_the_invoice_switch_also_covers_the_report(): void
    {
        $this->maintenanceCenter->update(['whatsapp_invoice_enabled' => false]);
        $order = $this->inspectedOrder();
        $this->postJson($this->url("orders/{$order['id']}/complete"), [], $this->as($this->owner, $this->branch))->assertOk();

        $this->assertSame([], $this->jobTypes());
        $this->inMaintenanceCenter($this->maintenanceCenter);
        $this->assertSame(WhatsappMessageStatus::SkippedDisabled, WhatsappMessage::query()->where('type', 'inspection')->sole()->status);
    }

    public function test_the_report_is_sent_on_demand_and_its_status_shows_on_the_inspection(): void
    {
        $order = $this->inspectedOrder();
        $url = $this->url("orders/{$order['id']}/inspection/whatsapp");

        // Open invoices too, by anyone who records inspections.
        $this->postJson($url, [], $this->as($this->technician()))->assertOk();
        $this->assertSame(['inspection'], $this->jobTypes());

        $this->getJson($this->url("orders/{$order['id']}/inspection"), $this->as($this->owner, $this->branch))
            ->assertOk()
            ->assertJsonPath('data.whatsapp.status.value', 'queued')
            ->assertJsonPath('data.whatsapp.recipient', '201012****78');

        $role = Role::query()->create(['key' => 'viewer', 'name' => 'متابع', 'permissions' => ['orders.view']]);
        $viewer = User::factory()->create(['role_id' => $role->id, 'branch_id' => $this->branch->id]);
        $this->postJson($url, [], $this->as($viewer))->assertForbidden();

        $plain = $this->openOrder($this->vehicle($this->customer('عميل 3', '01212345678')));
        $this->postJson($this->url("orders/{$plain['id']}/inspection/whatsapp"), [], $this->as($this->owner, $this->branch))->assertNotFound();

        $this->maintenanceCenter->update(['whatsapp_device_id' => null]);
        $this->postJson($url, [], $this->as($this->owner, $this->branch))
            ->assertStatus(422)
            ->assertJsonPath('msg', __('messages.whatsapp_not_connected'));

        $this->maintenanceCenter->update(['whatsapp_device_id' => 'device-1']);
        $this->postJson($this->url("orders/{$order['id']}/cancel"), ['reason' => 'x'], $this->as($this->owner, $this->branch))->assertOk();
        $this->postJson($url, [], $this->as($this->owner, $this->branch))
            ->assertStatus(422)
            ->assertJsonPath('msg', __('messages.whatsapp_inspection_not_sendable'));
    }

    public function test_the_job_sends_the_pdf_as_a_file_and_logs_it(): void
    {
        $order = $this->inspectedOrder();
        $this->postJson($this->url("orders/{$order['id']}/inspection/whatsapp"), [], $this->as($this->owner, $this->branch))->assertOk();
        Http::fake(['*' => Http::response(['code' => 'SUCCESS', 'results' => ['message_id' => 'wa-7']])]);
        config(['services.gowa.url' => 'http://gowa.test']);

        $this->inspectionJob()->handle(app(GowaWhatsappService::class), app(DocumentImageService::class), app(BalanceService::class), app(InvoiceChangeService::class));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://gowa.test/send/file'
            && $request->header('X-Device-Id') === ['device-1']
            && str_contains($request->body(), '201012345678@s.whatsapp.net')
            && str_contains($request->body(), '%PDF'));

        $this->assertSame([], glob(storage_path('app/whatsapp-temp/inspection-*.pdf')) ?: []);

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $message = WhatsappMessage::query()->where('type', 'inspection')->sole();
        $this->assertSame(WhatsappMessageStatus::Sent, $message->status);
        $this->assertSame('wa-7', $message->provider_message_id);
        $this->assertSame($order['id'], $message->order_id);
    }

    public function test_a_report_queued_before_the_invoice_was_cancelled_is_skipped(): void
    {
        $order = $this->inspectedOrder();
        $this->postJson($this->url("orders/{$order['id']}/inspection/whatsapp"), [], $this->as($this->owner, $this->branch))->assertOk();
        $this->postJson($this->url("orders/{$order['id']}/cancel"), ['reason' => 'x'], $this->as($this->owner, $this->branch))->assertOk();
        Http::fake();

        $this->inspectionJob()->handle(app(GowaWhatsappService::class), app(DocumentImageService::class), app(BalanceService::class), app(InvoiceChangeService::class));

        Http::assertNothingSent();
        $this->inMaintenanceCenter($this->maintenanceCenter);
        $this->assertSame(WhatsappMessageStatus::SkippedOrderCancelled, WhatsappMessage::query()->where('type', 'inspection')->sole()->status);
    }

    public function test_another_center_cannot_send_the_report(): void
    {
        [$otherCenter, $otherOwner] = $this->createMaintenanceCenter('مركز ب');
        $otherCenter->update(['whatsapp_device_id' => 'device-2']);
        $order = $this->inspectedOrder();

        $this->postJson($this->url("orders/{$order['id']}/inspection/whatsapp"), [], $this->as($otherOwner))->assertNotFound();
        $this->assertSame([], $this->jobTypes());
    }
}
