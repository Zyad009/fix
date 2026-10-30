<?php

namespace App\Jobs;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Tenant;
use App\Models\WhatsappMessage;
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
    ) {
        $this->onQueue('notifications');
    }

    public function handle(GowaWhatsappService $gowa, DocumentImageService $images): void
    {
        app()->setLocale('ar');

        $this->inTenant(function () use ($gowa, $images): void {
            $messageId = match (WhatsappMessageType::from($this->type)) {
                WhatsappMessageType::Test => $gowa->sendText($this->recipient, __('messages.whatsapp_test_body', ['center' => tenantContext()->tenant()->name]), $this->deviceId),
                WhatsappMessageType::Invoice => $this->sendInvoice($gowa, $images),
                WhatsappMessageType::Payment => $this->sendReceipt($gowa, $images),
            };

            $this->record(WhatsappMessageStatus::Sent, ['provider_message_id' => $messageId, 'sent_at' => now()]);
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
     * @param  array<string, mixed>  $attributes
     */
    private function record(WhatsappMessageStatus $status, array $attributes): void
    {
        WhatsappMessage::query()->create([
            'type' => $this->type,
            'order_id' => $this->orderId,
            'receipt_id' => $this->receiptId,
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
