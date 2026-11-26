<?php

namespace App\Jobs;

use App\Enums\WhatsappFailureReason;
use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Exceptions\DocumentImageException;
use App\Models\Inspection;
use App\Models\MaintenanceCenter;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\WhatsappMessage;
use App\Services\V1\BalanceService;
use App\Services\V1\DocumentImageService;
use App\Services\V1\GowaWhatsappService;
use App\Services\V1\InvoiceChangeService;
use App\Services\V1\PrintService;
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
 * scalars; the maintenance center context is rebuilt here because a worker has no request.
 */
class SendWhatsappMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 120;

    public function __construct(
        public readonly int $maintenanceCenterId,
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

    public function handle(GowaWhatsappService $gowa, DocumentImageService $images, BalanceService $balances, InvoiceChangeService $changes): void
    {
        app()->setLocale('ar');

        $this->inMaintenanceCenter(function () use ($gowa, $images, $balances, $changes): void {
            $type = WhatsappMessageType::from($this->type);
            $debt = $type === WhatsappMessageType::DebtReminder ? $balances->customerBalance((int) $this->customerId) : null;

            if ($debt !== null && ! $debt->isPositive()) {
                $this->record(WhatsappMessageStatus::SkippedNoDebt, []);

                return;
            }

            if (in_array($type, [WhatsappMessageType::InvoiceUpdated, WhatsappMessageType::Inspection], true) && $this->order()->isCancelled()) {
                $this->record(WhatsappMessageStatus::SkippedOrderCancelled, []);

                return;
            }

            $providerMessageId = match ($type) {
                WhatsappMessageType::Test => $gowa->sendText($this->recipient, __('messages.whatsapp_test_body', ['center' => maintenanceCenterContext()->maintenanceCenter()->name]), $this->deviceId),
                WhatsappMessageType::Invoice => $this->sendInvoice($gowa, $images),
                WhatsappMessageType::InvoiceUpdated => $this->sendInvoiceUpdate($gowa, $images, $changes),
                WhatsappMessageType::Inspection => $this->sendInspection($gowa),
                WhatsappMessageType::Payment => $this->sendReceipt($gowa, $images),
                WhatsappMessageType::ServiceReminder => $gowa->sendText($this->recipient, $this->serviceReminderText(), $this->deviceId),
                WhatsappMessageType::DebtReminder => $gowa->sendText($this->recipient, __('messages.whatsapp_debt_reminder_body', [
                    'center' => maintenanceCenterContext()->maintenanceCenter()->name,
                    'amount' => number_format($debt->toFloat(), 2),
                    'contact' => $this->contactLine(),
                ]), $this->deviceId),
            };

            $this->record(WhatsappMessageStatus::Sent, ['provider_message_id' => $providerMessageId, 'sent_at' => now()]);
        });
    }

    public function failed(Throwable $exception): void
    {
        $reason = WhatsappFailureReason::fromException($exception);

        $this->inMaintenanceCenter(fn () => $this->record(WhatsappMessageStatus::Failed, ['error' => $reason->value]));

        Log::channel('whatsapp')->error('WhatsApp message failed permanently', [
            'maintenance_center_id' => $this->maintenanceCenterId,
            'message_id' => $this->messageId,
            'type' => $this->type,
            'recipient' => PhoneNormalizer::mask($this->recipient),
            'reason' => $reason->value,
            'error' => $exception->getMessage(),
        ]);
    }

    private function sendInvoice(GowaWhatsappService $gowa, DocumentImageService $images): string
    {
        $order = $this->order();

        return $this->sendImage($gowa, $images->invoice($order), __('messages.whatsapp_invoice_caption', [
            'center' => maintenanceCenterContext()->maintenanceCenter()->name,
            'number' => $order->order_no,
            'total' => formatNumber($order->total),
            'paid' => formatNumber($order->paid),
            'remaining' => $order->remaining()->toFloat(),
        ]));
    }

    /**
     * The invoice as it is now, marked as an edit, with what changed since the
     * customer last received it.
     */
    private function sendInvoiceUpdate(GowaWhatsappService $gowa, DocumentImageService $images, InvoiceChangeService $changes): string
    {
        $order = $this->order();
        $summary = $changes->unsent($order, $this->messageId);

        return $this->sendImage($gowa, $images->invoiceUpdate($order, $summary), __('messages.whatsapp_invoice_updated_caption', [
            'center' => maintenanceCenterContext()->maintenanceCenter()->name,
            'number' => $order->order_no,
            'previous' => $summary['previous_total'],
            'total' => formatNumber($order->total),
            'paid' => formatNumber($order->paid),
            'remaining' => $order->remaining()->toFloat(),
        ]));
    }

    /**
     * The inspection report as it is when sent, as the same A4 PDF that prints.
     */
    private function sendInspection(GowaWhatsappService $gowa): string
    {
        $order = $this->order();
        $inspection = Inspection::query()->where('order_id', $order->id)->with(['items', 'inspector:id,name'])->firstOrFail();
        $print = app(PrintService::class);

        try {
            $pdf = $print->pdf('pdf.inspection', $print->inspectionData($order, $inspection), PrintService::SIZE_A4);
        } catch (Throwable $exception) {
            throw new DocumentImageException($exception);
        }

        $path = storage_path('app/whatsapp-temp/inspection-'.$order->order_no.'-'.uniqid().'.pdf');

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, $pdf);

        try {
            return $gowa->sendFile($this->recipient, $path, __('messages.whatsapp_inspection_caption', [
                'center' => maintenanceCenterContext()->maintenanceCenter()->name,
                'number' => $order->order_no,
            ]), $this->deviceId);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function order(): Order
    {
        return Order::query()->withoutGlobalScope('branch')->findOrFail($this->orderId);
    }

    private function sendReceipt(GowaWhatsappService $gowa, DocumentImageService $images): string
    {
        $receipt = PaymentReceipt::query()->withoutGlobalScope('branch')->findOrFail($this->receiptId);

        return $this->sendImage($gowa, $images->receipt($receipt), __('messages.whatsapp_payment_caption', [
            'center' => maintenanceCenterContext()->maintenanceCenter()->name,
            'number' => $receipt->receipt_no,
            'amount' => formatNumber($receipt->amount),
            'debt' => $images->customerDebt($receipt),
        ]));
    }

    private function serviceReminderText(): string
    {
        $order = Order::query()->withoutGlobalScope('branch')->with('vehicle:id,plate_number')->findOrFail($this->orderId);

        return __('messages.whatsapp_service_reminder_body', [
            'center' => maintenanceCenterContext()->maintenanceCenter()->name,
            'plate' => $order->vehicle?->plate_number,
            'date' => $order->next_service_date?->toDateString(),
            'contact' => $this->contactLine(),
        ]);
    }

    private function contactLine(): string
    {
        $phone = maintenanceCenterContext()->maintenanceCenter()->phone;

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

    private function inMaintenanceCenter(Closure $callback): void
    {
        maintenanceCenterContext()->run(MaintenanceCenter::query()->findOrFail($this->maintenanceCenterId), $callback);
    }
}
