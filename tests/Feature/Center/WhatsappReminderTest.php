<?php

namespace Tests\Feature\Center;

use App\Enums\WhatsappMessageStatus;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Vehicle;
use App\Models\WhatsappMessage;
use App\Services\V1\BalanceService;
use App\Services\V1\DocumentImageService;
use App\Services\V1\GowaWhatsappService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\CenterHttpTestCase;

/**
 * Daily WhatsApp reminders: service date tomorrow, and overdue debt.
 */
class WhatsappReminderTest extends CenterHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-03 10:00', 'Africa/Cairo'));
        $this->tenant->update([
            'whatsapp_device_id' => 'device-1',
            'whatsapp_service_reminder_enabled' => true,
            'whatsapp_debt_reminder_enabled' => true,
            'debt_reminder_days' => 30,
        ]);
        Queue::fake();
    }

    /**
     * @return list<SendWhatsappMessageJob>
     */
    private function reminders(string $type): array
    {
        return array_values(array_filter(
            Queue::pushed(SendWhatsappMessageJob::class)->all(),
            fn (SendWhatsappMessageJob $job): bool => $job->type === $type,
        ));
    }

    private function runReminders(): void
    {
        $this->artisan('whatsapp:reminders')->assertSuccessful();
        $this->inTenant($this->tenant);
    }

    /**
     * A completed, fully paid invoice carrying a next service date.
     */
    private function serviceInvoice(string $nextServiceDate, ?int $vehicleId = null): int
    {
        $vehicleId ??= $this->vehicle($this->customer())->id;
        $order = $this->openOrder(Vehicle::query()->findOrFail($vehicleId), [['name' => 'خدمة', 'qty' => 1, 'unit_price' => 100]], ['next_service_date' => $nextServiceDate]);

        $this->postJson($this->url("orders/{$order['id']}/complete"), ['payments' => [['amount' => 100]]], $this->as($this->owner, $this->branch))->assertOk();
        $this->inTenant($this->tenant);

        return $order['id'];
    }

    /**
     * An unpaid invoice completed `$daysAgo` days ago.
     */
    private function debt(Customer $customer, int $daysAgo, string $amount = '500'): int
    {
        $orderId = $this->invoice($this->vehicle($customer), $amount);
        Order::query()->withoutGlobalScope('branch')->whereKey($orderId)->toBase()->update(['completed_at' => now()->subDays($daysAgo)]);

        return $orderId;
    }

    private function pay(int $orderId, string $amount, int $daysAgo): int
    {
        $receiptId = $this->postJson($this->url("orders/{$orderId}/payments"), ['amount' => $amount], $this->as($this->owner, $this->branch))->json('data.id');
        $this->inTenant($this->tenant);
        PaymentReceipt::query()->withoutGlobalScope('branch')->whereKey($receiptId)->toBase()->update(['received_at' => now()->subDays($daysAgo)]);

        return $receiptId;
    }

    // ---------------------------------------------------------------- service

    public function test_service_due_tomorrow_is_reminded_once(): void
    {
        $orderId = $this->serviceInvoice('2026-11-04', $this->vehicle($this->customer('عميل', '01012345678'))->id);
        Queue::fake();

        $this->runReminders();
        $this->runReminders();

        $jobs = $this->reminders('service_reminder');
        $this->assertCount(1, $jobs);
        $this->assertSame($orderId, $jobs[0]->orderId);
        $this->assertSame('201012345678', $jobs[0]->recipient);
        $this->assertNotNull(Order::query()->withoutGlobalScope('branch')->findOrFail($orderId)->service_reminder_sent_at);
    }

    public function test_service_not_due_tomorrow_or_superseded_is_not_reminded(): void
    {
        $this->serviceInvoice('2026-11-05');
        $this->serviceInvoice('2026-11-03');

        $vehicle = $this->vehicle($this->customer('تاني', '01022222222'));
        $this->serviceInvoice('2026-11-04', $vehicle->id);
        $this->serviceInvoice('2026-12-01', $vehicle->id);
        Queue::fake();

        $this->runReminders();

        $this->assertCount(0, $this->reminders('service_reminder'));
    }

    public function test_service_reminder_goes_to_the_current_owner(): void
    {
        $vehicle = $this->vehicle($this->customer('قديم', '01011111111'));
        $this->serviceInvoice('2026-11-04', $vehicle->id);
        $newOwner = $this->customer('جديد', '01033333333');
        $this->patchJson($this->url("vehicles/{$vehicle->id}/transfer-owner"), ['customer_id' => $newOwner->id], $this->as($this->owner))->assertOk();
        Queue::fake();

        $this->runReminders();

        $this->assertSame('201033333333', $this->reminders('service_reminder')[0]->recipient);
    }

    public function test_switched_off_or_unconnected_centers_get_no_reminders(): void
    {
        $this->serviceInvoice('2026-11-04');
        $this->debt($this->customer('مديون', '01044444444'), 40);
        Queue::fake();

        $this->tenant->update(['whatsapp_service_reminder_enabled' => false, 'whatsapp_debt_reminder_enabled' => false]);
        $this->runReminders();

        $this->tenant->update(['whatsapp_service_reminder_enabled' => true, 'whatsapp_debt_reminder_enabled' => true, 'whatsapp_device_id' => null]);
        $this->runReminders();

        Queue::assertNothingPushed();
        $this->assertSame(0, WhatsappMessage::query()->count());
    }

    // ------------------------------------------------------------------- debt

    public function test_debt_is_reminded_once_the_period_has_passed(): void
    {
        $late = $this->customer('متأخر', '01044444444');
        $recent = $this->customer('جديد', '01055555555');
        $this->debt($late, 30);
        $this->debt($recent, 29);

        $this->runReminders();

        $jobs = $this->reminders('debt_reminder');
        $this->assertCount(1, $jobs);
        $this->assertSame($late->id, $jobs[0]->customerId);
        $this->assertNotNull($late->fresh()->debt_reminded_at);
    }

    public function test_a_partial_payment_restarts_the_count_but_a_voided_one_does_not(): void
    {
        $paid = $this->customer('دفع جزء', '01044444444');
        $this->pay($this->debt($paid, 60), '100', 5);

        $voided = $this->customer('دفعة ملغية', '01055555555');
        $receipt = $this->pay($this->debt($voided, 60), '100', 5);
        $this->patchJson($this->url("receipts/{$receipt}/void"), ['reason' => 'خطأ'], $this->as($this->owner, $this->branch))->assertOk();

        $this->runReminders();

        $this->assertSame([$voided->id], array_map(fn (SendWhatsappMessageJob $job): ?int => $job->customerId, $this->reminders('debt_reminder')));
    }

    public function test_a_new_unpaid_invoice_restarts_the_count(): void
    {
        $customer = $this->customer('عميل', '01044444444');
        $this->debt($customer, 90);
        $this->debt($customer, 2);

        $this->runReminders();

        $this->assertCount(0, $this->reminders('debt_reminder'));
    }

    public function test_debt_is_reminded_again_every_period_until_paid(): void
    {
        $customer = $this->customer('عميل', '01044444444');
        $orderId = $this->debt($customer, 90);

        Customer::query()->whereKey($customer->id)->toBase()->update(['debt_reminded_at' => now()->subDays(10)]);
        $this->runReminders();
        $this->assertCount(0, $this->reminders('debt_reminder'));

        Customer::query()->whereKey($customer->id)->toBase()->update(['debt_reminded_at' => now()->subDays(31)]);
        $this->runReminders();
        $this->assertCount(1, $this->reminders('debt_reminder'));

        $this->pay($orderId, '500', 40);
        Customer::query()->whereKey($customer->id)->toBase()->update(['debt_reminded_at' => now()->subDays(31)]);
        $this->runReminders();
        $this->assertCount(1, $this->reminders('debt_reminder'));
    }

    public function test_an_invalid_phone_is_logged_once_per_period(): void
    {
        $customer = $this->customer('رقم غلط', '0101');
        $this->debt($customer, 40);

        $this->runReminders();
        $this->runReminders();

        Queue::assertNothingPushed();
        $logged = WhatsappMessage::query()->where('type', 'debt_reminder')->get();
        $this->assertSame([WhatsappMessageStatus::SkippedInvalidPhone], $logged->pluck('status')->all());
        $this->assertSame($customer->id, $logged->first()->customer_id);
    }

    // -------------------------------------------------------------------- job

    public function test_the_debt_job_sends_the_amount_owed_when_it_runs(): void
    {
        $this->tenant->update(['phone' => '01000000000']);
        $customer = $this->customer('عميل', '01044444444');
        $orderId = $this->debt($customer, 40, '1500');
        $this->runReminders();
        $this->pay($orderId, '300', 0);

        Http::fake(['*' => Http::response(['code' => 'SUCCESS', 'results' => ['message_id' => 'wa-9']])]);
        config(['services.gowa.url' => 'http://gowa.test']);

        $this->reminders('debt_reminder')[0]->handle(app(GowaWhatsappService::class), app(DocumentImageService::class), app(BalanceService::class));

        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://gowa.test/send/message'
            && str_contains($request['message'], '1,200.00')
            && str_contains($request['message'], '01000000000'));

        $this->inTenant($this->tenant);
        $message = WhatsappMessage::query()->sole();
        $this->assertSame(WhatsappMessageStatus::Sent, $message->status);
        $this->assertSame($customer->id, $message->customer_id);
    }

    public function test_the_debt_job_skips_a_debt_settled_before_it_runs(): void
    {
        $customer = $this->customer('عميل', '01044444444');
        $orderId = $this->debt($customer, 40);
        $this->runReminders();
        $this->pay($orderId, '500', 0);
        Http::fake();

        $this->reminders('debt_reminder')[0]->handle(app(GowaWhatsappService::class), app(DocumentImageService::class), app(BalanceService::class));

        Http::assertNothingSent();
        $this->inTenant($this->tenant);
        $this->assertSame(WhatsappMessageStatus::SkippedNoDebt, WhatsappMessage::query()->sole()->status);
    }

    public function test_the_service_job_sends_the_plate_and_date(): void
    {
        $vehicle = $this->vehicle($this->customer('عميل', '01012345678'), 'س ص 123');
        $this->serviceInvoice('2026-11-04', $vehicle->id);
        Queue::fake();
        $this->runReminders();

        Http::fake(['*' => Http::response(['code' => 'SUCCESS', 'results' => ['message_id' => 'wa-1']])]);
        config(['services.gowa.url' => 'http://gowa.test']);

        $this->reminders('service_reminder')[0]->handle(app(GowaWhatsappService::class), app(DocumentImageService::class), app(BalanceService::class));

        Http::assertSent(fn (Request $request): bool => str_contains($request['message'], 'س ص 123')
            && str_contains($request['message'], '2026-11-04'));
    }

    // -------------------------------------------------------- isolation, settings

    public function test_each_center_follows_its_own_settings_and_device(): void
    {
        [$other] = $this->createTenant('مركز ب');
        $other->update(['whatsapp_device_id' => 'device-2', 'whatsapp_debt_reminder_enabled' => false]);
        $this->debt($this->customer('عميل أ', '01044444444'), 40);
        $this->runReminders();

        $jobs = $this->reminders('debt_reminder');
        $this->assertCount(1, $jobs);
        $this->assertSame($this->tenant->id, $jobs[0]->tenantId);
        $this->assertSame('device-1', $jobs[0]->deviceId);
    }

    public function test_owner_manages_the_reminder_settings(): void
    {
        $this->putJson($this->url('settings'), ['whatsapp_service_reminder_enabled' => false, 'debt_reminder_days' => 45], $this->as($this->owner, $this->branch))
            ->assertOk()
            ->assertJsonPath('data.whatsapp_service_reminder_enabled', false)
            ->assertJsonPath('data.whatsapp_debt_reminder_enabled', true)
            ->assertJsonPath('data.debt_reminder_days', 45);

        $this->putJson($this->url('settings'), ['debt_reminder_days' => 0], $this->as($this->owner, $this->branch))->assertStatus(422);
        $this->putJson($this->url('settings'), ['debt_reminder_days' => 10], $this->as($this->cashier()))->assertForbidden();
    }

    public function test_reminders_are_off_for_a_new_center(): void
    {
        [$tenant] = $this->createTenant('مركز جديد');

        $this->assertFalse($tenant->fresh()->whatsapp_service_reminder_enabled);
        $this->assertFalse($tenant->fresh()->whatsapp_debt_reminder_enabled);
        $this->assertSame(30, $tenant->fresh()->debt_reminder_days);
    }
}
