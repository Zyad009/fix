<?php

namespace App\Services\V1;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Exceptions\BusinessRuleException;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\Customer;
use App\Models\MaintenanceCenter;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\WhatsappMessage;
use App\Support\Period;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The single door to customer WhatsApp messages. Automatic sends never get in
 * the way of a sale: what cannot be sent is logged as skipped, and the job is
 * queued only after the surrounding transaction commits. Manual sends report
 * the problem to the user instead.
 */
class WhatsappNotifier
{
    public function __construct(protected CustomerService $customers) {}

    public function invoiceCompleted(Order $order): void
    {
        if ($order->is_opening_balance) {
            return;
        }

        $this->automatic(WhatsappMessageType::Invoice, maintenanceCenterContext()->maintenanceCenter()->whatsapp_invoice_enabled, $order->customer_id, orderId: $order->id);
    }

    /**
     * A completed invoice was edited. Several edits in a row (line by line)
     * become one message: the send waits a short while, renders the invoice
     * as it is then, and later edits join a message that has not started yet.
     */
    public function invoiceUpdated(Order $order): void
    {
        if (! $order->isCompleted() || $order->is_opening_balance || $this->hasPendingUpdate($order)) {
            return;
        }

        $this->automatic(
            WhatsappMessageType::InvoiceUpdated,
            maintenanceCenterContext()->maintenanceCenter()->whatsapp_invoice_enabled,
            $order->customer_id,
            orderId: $order->id,
            delay: $this->invoiceUpdateDelay(),
        );
    }

    /**
     * The invoice was completed with a technical inspection: the report PDF
     * follows the invoice image, under the same invoice switch.
     */
    public function inspectionCompleted(Order $order): void
    {
        if ($order->is_opening_balance || ! $order->inspection()->exists()) {
            return;
        }

        $this->automatic(WhatsappMessageType::Inspection, maintenanceCenterContext()->maintenanceCenter()->whatsapp_invoice_enabled, $order->customer_id, orderId: $order->id);
    }

    /**
     * The inspection report sent on demand, open invoices included.
     */
    public function sendInspection(Order $order): void
    {
        if ($order->isCancelled()) {
            throw new BusinessRuleException(__('messages.whatsapp_inspection_not_sendable'), 422);
        }

        if (! $order->inspection()->exists()) {
            throw new BusinessRuleException(__('messages.inspection_not_found'), 404);
        }

        $this->manual(WhatsappMessageType::Inspection, $this->customerPhone($order->customer_id), orderId: $order->id, customerId: $order->customer_id);
    }

    public function paymentCollected(PaymentReceipt $receipt): void
    {
        $this->automatic(WhatsappMessageType::Payment, maintenanceCenterContext()->maintenanceCenter()->whatsapp_payment_enabled, $receipt->customer_id, receiptId: $receipt->id);
    }

    /**
     * The day before the next service date of the vehicle's latest invoice,
     * sent to whoever owns the vehicle now.
     */
    public function serviceReminder(Order $order, int $ownerId): void
    {
        $this->automatic(WhatsappMessageType::ServiceReminder, maintenanceCenterContext()->maintenanceCenter()->whatsapp_service_reminder_enabled, $ownerId, orderId: $order->id);
    }

    /**
     * The amount is read again when the job runs, so a payment made in between
     * is never ignored.
     */
    public function debtReminder(Customer $customer): void
    {
        $this->automatic(WhatsappMessageType::DebtReminder, maintenanceCenterContext()->maintenanceCenter()->whatsapp_debt_reminder_enabled, $customer->id);
    }

    public function resendInvoice(Order $order): void
    {
        $this->ensureInvoiceSendable($order);

        $this->manual(WhatsappMessageType::Invoice, $this->customerPhone($order->customer_id), orderId: $order->id, customerId: $order->customer_id);
    }

    /**
     * The customer typed a wrong number: the number is corrected on the
     * customer (so later messages reach the right phone) and the invoice is
     * sent again. Nothing is changed when the invoice cannot be sent.
     */
    public function correctPhoneAndResendInvoice(Order $order, string $phone): void
    {
        $this->ensureInvoiceSendable($order);

        if ($order->customer_id === null) {
            throw new BusinessRuleException(__('messages.whatsapp_order_has_no_customer'), 422);
        }

        DB::transaction(function () use ($order, $phone): void {
            $customer = Customer::query()->withTrashed()->lockForUpdate()->findOrFail($order->customer_id);

            if (Customer::query()->where('phone', PhoneNormalizer::normalize($phone))->whereKeyNot($customer->id)->exists()) {
                throw new BusinessRuleException(__('messages.whatsapp_phone_taken'), 422);
            }

            $this->customers->update($customer, ['phone' => $phone]);
        });

        $this->resendInvoice($order);
    }

    public function resendReceipt(PaymentReceipt $receipt): void
    {
        if ($receipt->isVoided()) {
            throw new BusinessRuleException(__('messages.whatsapp_receipt_voided'), 422);
        }

        $this->manual(WhatsappMessageType::Payment, $this->customerPhone($receipt->customer_id), receiptId: $receipt->id, customerId: $receipt->customer_id);
    }

    public function sendTest(string $phone): void
    {
        $this->manual(WhatsappMessageType::Test, $phone);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, WhatsappMessage>
     */
    public function log(array $filters): LengthAwarePaginator
    {
        return WhatsappMessage::query()
            ->with(['order:id,order_no', 'receipt:id,receipt_no', 'customer:id,name'])
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(isset($filters['from']) || isset($filters['to']), function (Builder $query) use ($filters): void {
                $period = Period::fromFilters($filters);
                $query->whereBetween('created_at', [$period->start, $period->end]);
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? config('paginate.default'));
    }

    private function automatic(WhatsappMessageType $type, bool $enabled, ?int $customerId, ?int $orderId = null, ?int $receiptId = null, int $delay = 0): void
    {
        $maintenanceCenter = maintenanceCenterContext()->maintenanceCenter();
        $recipient = PhoneNormalizer::toWhatsapp($this->customerPhone($customerId));

        $skip = match (true) {
            ! $enabled => WhatsappMessageStatus::SkippedDisabled,
            blank($maintenanceCenter->whatsapp_device_id) => WhatsappMessageStatus::SkippedNoDevice,
            $recipient === null => WhatsappMessageStatus::SkippedInvalidPhone,
            default => null,
        };

        if ($skip !== null) {
            WhatsappMessage::query()->create([
                'type' => $type,
                'order_id' => $orderId,
                'receipt_id' => $receiptId,
                'customer_id' => $customerId,
                'recipient' => $recipient === null ? null : PhoneNormalizer::mask($recipient),
                'status' => $skip,
            ]);

            return;
        }

        // After commit, and a queue outage is only reported: the sale is already saved.
        DB::afterCommit(fn () => rescue(fn () => $this->dispatch($maintenanceCenter, $type, $recipient, $orderId, $receiptId, $customerId, $delay)));
    }

    private function manual(WhatsappMessageType $type, ?string $phone, ?int $orderId = null, ?int $receiptId = null, ?int $customerId = null): void
    {
        $maintenanceCenter = maintenanceCenterContext()->maintenanceCenter();

        $this->ensureConnected($maintenanceCenter);

        $recipient = PhoneNormalizer::toWhatsapp($phone);

        if ($recipient === null) {
            throw new BusinessRuleException(__('messages.whatsapp_invalid_phone'), 422);
        }

        $this->dispatch($maintenanceCenter, $type, $recipient, $orderId, $receiptId, $customerId);
    }

    private function ensureInvoiceSendable(Order $order): void
    {
        if (! $order->isCompleted() || $order->is_opening_balance) {
            throw new BusinessRuleException(__('messages.whatsapp_invoice_not_sendable'), 422);
        }

        $this->ensureConnected(maintenanceCenterContext()->maintenanceCenter());
    }

    private function ensureConnected(MaintenanceCenter $maintenanceCenter): void
    {
        if (blank($maintenanceCenter->whatsapp_device_id)) {
            throw new BusinessRuleException(__('messages.whatsapp_not_connected'), 422);
        }
    }

    /**
     * The row is logged as queued right away, so the invoice shows the message
     * while it waits; the job settles the same row (sent/failed/skipped).
     */
    private function dispatch(MaintenanceCenter $maintenanceCenter, WhatsappMessageType $type, string $recipient, ?int $orderId, ?int $receiptId, ?int $customerId = null, int $delay = 0): void
    {
        $message = WhatsappMessage::query()->create([
            'type' => $type,
            'order_id' => $orderId,
            'receipt_id' => $receiptId,
            'customer_id' => $customerId,
            'recipient' => PhoneNormalizer::mask($recipient),
            'status' => WhatsappMessageStatus::Queued,
        ]);

        SendWhatsappMessageJob::dispatch(
            $maintenanceCenter->id,
            $type->value,
            $recipient,
            (string) $maintenanceCenter->whatsapp_device_id,
            $orderId,
            $receiptId,
            $customerId,
            $message->id,
        )->delay($delay);
    }

    /**
     * An update message still waiting for its delay already covers this edit,
     * because the invoice is rendered when it is sent. One that is due (or
     * running) may have rendered already, so a new edit gets its own message.
     */
    private function hasPendingUpdate(Order $order): bool
    {
        return WhatsappMessage::query()
            ->where('order_id', $order->id)
            ->where('type', WhatsappMessageType::InvoiceUpdated)
            ->where('status', WhatsappMessageStatus::Queued)
            ->where('created_at', '>', now()->subSeconds(max($this->invoiceUpdateDelay() - 10, 0)))
            ->exists();
    }

    private function invoiceUpdateDelay(): int
    {
        return (int) config('services.gowa.invoice_update_delay');
    }

    /**
     * A deleted customer still gets the proof of what they paid.
     */
    private function customerPhone(?int $customerId): ?string
    {
        return $customerId === null ? null : Customer::query()->withTrashed()->whereKey($customerId)->value('phone');
    }
}
