<?php

namespace Tests\Feature\Center;

use App\Enums\WhatsappMessageStatus;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\Customer;
use App\Models\MaintenanceCenter;
use App\Models\WhatsappMessage;
use App\Services\V1\BalanceService;
use App\Services\V1\DocumentImageService;
use App\Services\V1\GowaWhatsappService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\CenterHttpTestCase;

/**
 * Invoice and receipt images sent to customers over WhatsApp.
 */
class WhatsappTest extends CenterHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        $this->maintenanceCenter->update(['whatsapp_device_id' => 'device-1']);
    }

    /**
     * @return list<SendWhatsappMessageJob>
     */
    private function jobs(): array
    {
        return Queue::pushed(SendWhatsappMessageJob::class)->all();
    }

    public function test_completing_an_invoice_queues_one_image_after_commit(): void
    {
        $orderId = $this->invoice($this->vehicle($this->customer('عميل', '01012345678')), '300');

        $jobs = $this->jobs();

        $this->assertCount(1, $jobs);
        $this->assertSame('invoice', $jobs[0]->type);
        $this->assertSame($orderId, $jobs[0]->orderId);
        $this->assertSame('201012345678', $jobs[0]->recipient);
        $this->assertSame('device-1', $jobs[0]->deviceId);
        $this->assertSame('notifications', $jobs[0]->queue);
    }

    public function test_completing_and_paying_in_one_save_sends_only_the_invoice(): void
    {
        $this->postJson($this->url('orders'), [
            'vehicle_id' => $this->vehicle()->id,
            'items' => [['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 300]],
            'complete' => true,
            'payments' => [['amount' => 100]],
        ], $this->as($this->owner, $this->branch))->assertCreated();

        $this->assertSame(['invoice'], array_map(fn (SendWhatsappMessageJob $job): string => $job->type, $this->jobs()));
    }

    public function test_every_way_of_collecting_money_queues_a_receipt(): void
    {
        $customer = $this->customer();
        $vehicle = $this->vehicle($customer);
        $first = $this->invoice($vehicle, '1000');
        $open = $this->openOrder($vehicle, [['name' => 'x', 'qty' => 1, 'unit_price' => 100]]);
        $headers = $this->as($this->cashier());

        $this->postJson($this->url("orders/{$first}/payments"), ['amount' => 10], $headers)->assertCreated();
        $this->postJson($this->url("orders/{$open['id']}/payments"), ['amount' => 10], $headers)->assertCreated();
        $this->postJson($this->url("vehicles/{$vehicle->id}/payments"), ['amount' => 10], $headers)->assertCreated();
        $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 10], $headers)->assertCreated();
        $this->postJson($this->url("customers/{$customer->id}/payments"), ['amount' => 10, 'allocation' => 'manual', 'orders' => [['order_id' => $first, 'amount' => 10]]], $headers)->assertCreated();

        $receipts = array_filter($this->jobs(), fn (SendWhatsappMessageJob $job): bool => $job->type === 'payment');

        $this->assertCount(5, $receipts);
        $this->assertNotNull(array_values($receipts)[0]->receiptId);
    }

    public function test_opening_balance_cancel_void_and_edit_send_nothing(): void
    {
        $vehicle = $this->vehicle();
        $headers = $this->as($this->owner, $this->branch);

        $this->postJson($this->url("vehicles/{$vehicle->id}/opening-balance"), ['amount' => 500], $headers)->assertCreated();
        $this->assertCount(0, $this->jobs());

        $order = $this->invoice($vehicle, '300');
        $receiptId = $this->postJson($this->url("orders/{$order}/payments"), ['amount' => 50], $headers)->json('data.id');
        $sent = count($this->jobs());

        $this->putJson($this->url("orders/{$order}"), ['notes' => 'تعديل'], $headers)->assertOk();
        $this->patchJson($this->url("receipts/{$receiptId}/void"), ['reason' => 'خطأ'], $headers)->assertOk();
        $this->postJson($this->url("orders/{$order}/cancel"), ['reason' => 'خطأ'], $headers)->assertOk();

        $this->assertCount($sent, $this->jobs());
    }

    public function test_a_failed_transaction_sends_nothing(): void
    {
        $this->postJson($this->url('orders'), [
            'vehicle_id' => $this->vehicle()->id,
            'items' => [['name' => 'مصنعية', 'qty' => 1, 'unit_price' => 100]],
            'complete' => true,
            'payments' => [['amount' => 150]],
        ], $this->as($this->owner, $this->branch))->assertStatus(422);

        $this->assertCount(0, $this->jobs());
    }

    public function test_what_cannot_be_sent_is_logged_as_skipped_and_never_blocks_the_sale(): void
    {
        $vehicle = $this->vehicle($this->customer('عميل', '01012345678'));

        $this->maintenanceCenter->update(['whatsapp_invoice_enabled' => false]);
        $this->invoice($vehicle, '100');

        $this->maintenanceCenter->update(['whatsapp_invoice_enabled' => true, 'whatsapp_device_id' => null]);
        $this->invoice($vehicle, '100');

        $this->maintenanceCenter->update(['whatsapp_device_id' => 'device-1']);
        $this->invoice($this->vehicle($this->customer('رقم قصير', '0101')), '100');

        $this->assertCount(0, $this->jobs());

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $this->assertSame(
            [WhatsappMessageStatus::SkippedDisabled, WhatsappMessageStatus::SkippedNoDevice, WhatsappMessageStatus::SkippedInvalidPhone],
            WhatsappMessage::query()->orderBy('id')->pluck('status')->all(),
        );
    }

    public function test_the_job_renders_sends_logs_and_removes_the_image(): void
    {
        $orderId = $this->invoice($this->vehicle($this->customer('عميل', '01012345678')), '300');
        $image = $this->fakeImage();
        Http::fake(['*' => Http::response(['code' => 'SUCCESS', 'results' => ['message_id' => 'wa-1']])]);
        config(['services.gowa.url' => 'http://gowa.test']);

        $this->jobs()[0]->handle(app(GowaWhatsappService::class), app(DocumentImageService::class), app(BalanceService::class));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://gowa.test/send/image'
            && $request->header('X-Device-Id') === ['device-1']
            && str_contains($request->body(), '201012345678@s.whatsapp.net'));

        $this->assertFileDoesNotExist($image);

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $message = WhatsappMessage::query()->sole();
        $this->assertSame(WhatsappMessageStatus::Sent, $message->status);
        $this->assertSame('wa-1', $message->provider_message_id);
        $this->assertSame($orderId, $message->order_id);
        $this->assertSame('201012****78', $message->recipient);
    }

    public function test_a_gateway_failure_retries_cleans_up_and_finally_logs_failed(): void
    {
        $this->invoice($this->vehicle($this->customer('عميل', '01012345678')), '300');
        $image = $this->fakeImage();
        Http::fake(['*' => Http::response(['code' => 'ERROR', 'message' => 'device offline'], 500)]);

        $job = $this->jobs()[0];

        try {
            $job->handle(app(GowaWhatsappService::class), app(DocumentImageService::class), app(BalanceService::class));
            $this->fail('The job must throw so the queue retries it.');
        } catch (RuntimeException $exception) {
            $this->assertSame('device offline', $exception->getMessage());
        }

        $this->assertFileDoesNotExist($image);

        $job->failed($exception);

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $message = WhatsappMessage::query()->sole();
        $this->assertSame(WhatsappMessageStatus::Failed, $message->status);
        $this->assertSame('device offline', $message->error);
    }

    public function test_manual_resend_and_its_rules(): void
    {
        $vehicle = $this->vehicle($this->customer('عميل', '01012345678'));
        $order = $this->invoice($vehicle, '300');
        $open = $this->openOrder($vehicle, [['name' => 'x', 'qty' => 1, 'unit_price' => 100]]);
        $receipt = $this->postJson($this->url("orders/{$order}/payments"), ['amount' => 50], $this->as($this->owner, $this->branch))->json('data.id');
        $before = count($this->jobs());

        $this->maintenanceCenter->update(['whatsapp_invoice_enabled' => false]);

        $this->postJson($this->url("orders/{$order}/whatsapp"), [], $this->as($this->cashier()))->assertOk();
        $this->postJson($this->url("receipts/{$receipt}/whatsapp"), [], $this->as($this->cashier()))->assertOk();
        $this->assertCount($before + 2, $this->jobs());

        $this->postJson($this->url("orders/{$open['id']}/whatsapp"), [], $this->as($this->owner, $this->branch))->assertStatus(422);
        $this->postJson($this->url("orders/{$order}/whatsapp"), [], $this->as($this->technician()))->assertForbidden();

        $this->patchJson($this->url("receipts/{$receipt}/void"), ['reason' => 'خطأ'], $this->as($this->owner, $this->branch))->assertOk();
        $this->postJson($this->url("receipts/{$receipt}/whatsapp"), [], $this->as($this->owner, $this->branch))->assertStatus(422);

        $this->maintenanceCenter->update(['whatsapp_device_id' => null]);
        $this->postJson($this->url("orders/{$order}/whatsapp"), [], $this->as($this->owner, $this->branch))
            ->assertStatus(422)
            ->assertJsonPath('msg', __('messages.whatsapp_not_connected'));
    }

    public function test_test_message_and_settings_are_for_the_owner_who_cannot_set_the_device(): void
    {
        $this->postJson($this->url('whatsapp/test'), ['phone' => '01012345678'], $this->as($this->owner, $this->branch))->assertOk();
        $this->assertSame('test', $this->jobs()[0]->type);
        $this->postJson($this->url('whatsapp/test'), ['phone' => '123'], $this->as($this->owner, $this->branch))->assertStatus(422);
        $this->postJson($this->url('whatsapp/test'), ['phone' => '01012345678'], $this->as($this->cashier()))->assertForbidden();

        $this->putJson($this->url('settings'), ['whatsapp_payment_enabled' => false, 'whatsapp_device_id' => 'hacked'], $this->as($this->owner, $this->branch))
            ->assertOk()
            ->assertJsonPath('data.whatsapp_payment_enabled', false)
            ->assertJsonPath('data.whatsapp_connected', true)
            ->assertJsonMissingPath('data.whatsapp_device_id');

        $this->assertSame('device-1', MaintenanceCenter::query()->findOrFail($this->maintenanceCenter->id)->whatsapp_device_id);
    }

    public function test_admin_sets_the_device(): void
    {
        $this->putJson("/api/v1/admin/maintenance-centers/{$this->maintenanceCenter->id}", ['whatsapp_device_id' => 'device-9'], $this->asAdmin())
            ->assertOk()
            ->assertJsonPath('data.whatsapp_device_id', 'device-9');
    }

    public function test_log_and_maintenance_center_isolation(): void
    {
        $this->maintenanceCenter->update(['whatsapp_device_id' => null]);
        $order = $this->invoice($this->vehicle(), '100');

        [, $otherOwner] = $this->createMaintenanceCenter('مركز ب');
        $other = $this->as($otherOwner);

        $this->postJson($this->url("orders/{$order}/whatsapp"), [], $other)->assertNotFound();
        $this->getJson($this->url('whatsapp/messages'), $other)->assertOk()->assertJsonCount(0, 'data');

        $this->getJson($this->url('whatsapp/messages?status=skipped_no_device'), $this->as($this->owner))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type.value', 'invoice')
            ->assertJsonPath('data.0.order.id', $order);

        $this->getJson($this->url('whatsapp/messages'), $this->as($this->cashier()))->assertForbidden();
    }

    public function test_a_queued_message_is_logged_at_once_and_settled_by_its_job(): void
    {
        $orderId = $this->invoice($this->vehicle($this->customer('عميل', '01012345678')), '300');

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $queued = WhatsappMessage::query()->sole();
        $this->assertSame(WhatsappMessageStatus::Queued, $queued->status);
        $this->assertSame($orderId, $queued->order_id);
        $this->assertSame('201012****78', $queued->recipient);
        $this->assertSame($queued->id, $this->jobs()[0]->messageId);

        $this->fakeImage();
        Http::fake(['*' => Http::response(['code' => 'SUCCESS', 'results' => ['message_id' => 'wa-1']])]);
        $this->jobs()[0]->handle(app(GowaWhatsappService::class), app(DocumentImageService::class), app(BalanceService::class));

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $this->assertSame($queued->id, WhatsappMessage::query()->sole()->id);
        $this->assertSame(WhatsappMessageStatus::Sent, WhatsappMessage::query()->sole()->status);
    }

    public function test_the_invoice_shows_its_latest_whatsapp_status(): void
    {
        $vehicle = $this->vehicle($this->customer('عميل', '01012345678'));
        $orderId = $this->invoice($vehicle, '300');
        $open = $this->openOrder($vehicle, [['name' => 'x', 'qty' => 1, 'unit_price' => 100]]);
        $headers = $this->as($this->owner, $this->branch);

        $this->getJson($this->url("orders/{$orderId}"), $headers)
            ->assertOk()
            ->assertJsonPath('data.whatsapp.status.value', 'queued')
            ->assertJsonPath('data.whatsapp.recipient', '201012****78');
        $this->getJson($this->url("orders/{$open['id']}"), $headers)->assertOk()->assertJsonPath('data.whatsapp', null);

        $this->inMaintenanceCenter($this->maintenanceCenter);
        WhatsappMessage::query()->sole()->update(['status' => WhatsappMessageStatus::Failed, 'error' => 'device offline']);
        WhatsappMessage::query()->create(['type' => 'service_reminder', 'order_id' => $orderId, 'status' => WhatsappMessageStatus::Sent]);

        $this->getJson($this->url('orders?status=completed'), $headers)
            ->assertOk()
            ->assertJsonPath('data.0.id', $orderId)
            ->assertJsonPath('data.0.whatsapp.status.value', 'failed')
            ->assertJsonPath('data.0.whatsapp.error', 'device offline');
    }

    public function test_resend_corrects_the_customer_phone_and_sends_the_invoice_again(): void
    {
        $customer = $this->customer('عميل', '01012345678');
        $orderId = $this->invoice($this->vehicle($customer), '300');

        $this->postJson($this->url("orders/{$orderId}/whatsapp/resend"), ['phone' => '٠١١ ٢٢٢٣-٣٣٤٤'], $this->as($this->cashier()))
            ->assertOk()
            ->assertJsonPath('msg', __('messages.whatsapp_queued'));

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $this->assertSame('01122233344', Customer::query()->findOrFail($customer->id)->phone);
        $jobs = $this->jobs();
        $this->assertCount(2, $jobs);
        $this->assertSame('invoice', $jobs[1]->type);
        $this->assertSame($orderId, $jobs[1]->orderId);
        $this->assertSame('201122233344', $jobs[1]->recipient);

        $this->getJson($this->url("orders/{$orderId}"), $this->as($this->owner, $this->branch))
            ->assertJsonPath('data.whatsapp.id', $jobs[1]->messageId)
            ->assertJsonPath('data.whatsapp.recipient', '201122****44');
    }

    public function test_resend_rules_leave_the_phone_unchanged(): void
    {
        $customer = $this->customer('عميل', '01012345678');
        $this->customer('تاني', '01022222222');
        $vehicle = $this->vehicle($customer);
        $orderId = $this->invoice($vehicle, '300');
        $open = $this->openOrder($vehicle, [['name' => 'x', 'qty' => 1, 'unit_price' => 100]]);
        $headers = $this->as($this->owner, $this->branch);
        $url = $this->url("orders/{$orderId}/whatsapp/resend");

        foreach (['1012345678', '0101234567', '010123456789', '01412345678', '0221234567', ''] as $wrong) {
            $this->postJson($url, ['phone' => $wrong], $headers)->assertStatus(422);
        }

        $this->postJson($url, ['phone' => '01022222222'], $headers)
            ->assertStatus(422)
            ->assertJsonPath('msg', __('messages.whatsapp_phone_taken'));
        $this->postJson($this->url("orders/{$open['id']}/whatsapp/resend"), ['phone' => '01099999999'], $headers)
            ->assertStatus(422)
            ->assertJsonPath('msg', __('messages.whatsapp_invoice_not_sendable'));
        $this->postJson($url, ['phone' => '01099999999'], $this->as($this->technician()))->assertForbidden();

        $this->maintenanceCenter->update(['whatsapp_device_id' => null]);
        $this->postJson($url, ['phone' => '01099999999'], $this->as($this->owner, $this->branch))
            ->assertStatus(422)
            ->assertJsonPath('msg', __('messages.whatsapp_not_connected'));

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $this->assertSame('01012345678', Customer::query()->findOrFail($customer->id)->phone);
        $this->assertCount(1, $this->jobs());
    }

    public function test_resend_is_maintenance_center_isolated(): void
    {
        $orderId = $this->invoice($this->vehicle($this->customer('عميل', '01012345678')), '300');
        [, $otherOwner] = $this->createMaintenanceCenter('مركز ب');

        $this->postJson($this->url("orders/{$orderId}/whatsapp/resend"), ['phone' => '01099999999'], $this->as($otherOwner))->assertNotFound();

        $this->inMaintenanceCenter($this->maintenanceCenter);
        $this->assertSame(1, Customer::query()->where('phone', '01012345678')->count());
    }

    /**
     * Swaps headless Chrome for a temp file, returning that file's path.
     */
    private function fakeImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'wa');
        file_put_contents($path, 'png');

        $images = $this->createMock(DocumentImageService::class);
        $images->method('invoice')->willReturn($path);
        $this->app->instance(DocumentImageService::class, $images);

        return $path;
    }
}
