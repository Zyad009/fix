<?php

namespace App\Jobs;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Tenant;
use App\Models\WhatsappMessage;
use App\Services\V1\BalanceService;
use App\Services\V1\DocumentImageService;
use App\Services\V1\GowaWhatsappService;
use App\Support\PhoneNormalizer;
use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Renders the invoice/receipt image at send time and delivers it. Carries only
 * scalars; the tenant context is rebuilt here because a worker has no request.
 */
class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 120;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $type,
        public readonly string $recipient,
        public readonly string $deviceId,
        public readonly ?int $orderId = null,
        public readonly ?int $receiptId = null,
        public readonly ?int $customerId = null,
        public readonly ?int $messageId = null,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(GowaWhatsappService $gowa, DocumentImageService $images, BalanceService $balances): void
    {
        app()->setLocale('ar');

        $this->inTenant(function () use ($gowa, $images, $balances): void {
            $type = WhatsappMessageType::from($this->type);
            $debt = $type === WhatsappMessageType::DebtReminder ? $balances->customerBalance((int) $this->customerId) : null;

            if ($debt !== null && ! $debt->isPositive()) {
                $this->record(WhatsappMessageStatus::SkippedNoDebt, []);

                return;
            }

            $providerMessageId = match ($type) {
                WhatsappMessageType::Test => $gowa->sendText($this->recipient, __('messages.whatsapp_test_body', ['center' => tenantContext()->tenant()->name]), $this->deviceId),
                WhatsappMessageType::Invoice => $this->sendInvoice($gowa, $images),
                WhatsappMessageType::Payment => $this->sendReceipt($gowa, $images),
                WhatsappMessageType::ServiceReminder => $gowa->sendText($this->recipient, $this->serviceReminderText(), $this->deviceId),
                WhatsappMessageType::DebtReminder => $gowa->sendText($this->recipient, __('messages.whatsapp_debt_reminder_body', [
                    'center' => tenantContext()->tenant()->name,
                    'amount' => number_format($debt->toFloat(), 2),
                    'contact' => $this->contactLine(),
                ]), $this->deviceId),
            };

            $this->record(WhatsappMessageStatus::Sent, ['provider_message_id' => $providerMessageId, 'sent_at' => now()]);
        });
    }

    public function failed(Throwable $exception): void
    {
        $this->inTenant(fn () => $this->record(WhatsappMessageStatus::Failed, ['error' => mb_substr($exception->getMessage(), 0, 500)]));

        Log::channel('whatsapp')->error('WhatsApp message failed permanently', [
            'tenant_id' => $this->tenantId,
            'type' => $this->type,
            'recipient' => PhoneNormalizer::mask($this->recipient),
            'error' => $exception->getMessage(),
        ]);
    }

    private function sendInvoice(GowaWhatsappService $gowa, DocumentImageService $images): string
    {
        $order = Order::query()->withoutGlobalScope('branch')->findOrFail($this->orderId);

        return $this->sendImage($gowa, $images->invoice($order), __('messages.whatsapp_invoice_caption', [
            'center' => tenantContext()->tenant()->name,
            'number' => $order->order_no,
            'total' => formatNumber($order->total),
            'paid' => formatNumber($order->paid),
            'remaining' => $order->remaining()->toFloat(),
        ]));
    }

    private function sendReceipt(GowaWhatsappService $gowa, DocumentImageService $images): string
    {
        $receipt = PaymentReceipt::query()->withoutGlobalScope('branch')->findOrFail($this->receiptId);

        return $this->sendImage($gowa, $images->receipt($receipt), __('messages.whatsapp_payment_caption', [
            'center' => tenantContext()->tenant()->name,
            'number' => $receipt->receipt_no,
            'amount' => formatNumber($receipt->amount),
            'debt' => $images->customerDebt($receipt),
        ]));
    }

    private function serviceReminderText(): string
    {
        $order = Order::query()->withoutGlobalScope('branch')->with('vehicle:id,plate_number')->findOrFail($this->orderId);

        return __('messages.whatsapp_service_reminder_body', [
            'center' => tenantContext()->tenant()->name,
            'plate' => $order->vehicle?->plate_number,
            'date' => $order->next_service_date?->toDateString(),
            'contact' => $this->contactLine(),
        ]);
    }

    private function contactLine(): string
    {
        $phone = tenantContext()->tenant()->phone;

        return filled($phone) ? __('messages.whatsapp_contact_line', ['phone' => $phone]) : '';
    }

    /**
     * The image is removed on success and on failure alike.
     */
    private function sendImage(GowaWhatsappService $gowa, string $path, string $caption): string
    {
        try {
            return $gowa->sendImage($this->recipient, $path, $caption, $this->deviceId);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    /**
     * Settles the row logged as queued at dispatch; a job queued before that
     * row existed (no id) logs a new one.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function record(WhatsappMessageStatus $status, array $attributes): void
    {
        $queued = $this->messageId === null ? null : WhatsappMessage::query()->find($this->messageId);

        if ($queued !== null) {
            $queued->update(['status' => $status, ...$attributes]);

            return;
        }

        WhatsappMessage::query()->create([
            'type' => $this->type,
            'order_id' => $this->orderId,
            'receipt_id' => $this->receiptId,
            'customer_id' => $this->customerId,
            'recipient' => PhoneNormalizer::mask($this->recipient),
            'status' => $status,
            ...$attributes,
        ]);
    }

    private function inTenant(Closure $callback): void
    {
        tenantContext()->run(Tenant::query()->findOrFail($this->tenantId), $callback);
    }
}
