<?php

namespace App\Services\V1;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Exceptions\BusinessRuleException;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentReceipt;
use App\Models\Tenant;
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
    public function invoiceCompleted(Order $order): void
    {
        if ($order->is_opening_balance) {
            return;
        }

        $this->automatic(WhatsappMessageType::Invoice, tenantContext()->tenant()->whatsapp_invoice_enabled, $order->customer_id, orderId: $order->id);
    }

    public function paymentCollected(PaymentReceipt $receipt): void
    {
        $this->automatic(WhatsappMessageType::Payment, tenantContext()->tenant()->whatsapp_payment_enabled, $receipt->customer_id, receiptId: $receipt->id);
    }

    public function resendInvoice(Order $order): void
    {
        if (! $order->isCompleted() || $order->is_opening_balance) {
            throw new BusinessRuleException(__('messages.whatsapp_invoice_not_sendable'), 422);
        }

        $this->manual(WhatsappMessageType::Invoice, $this->customerPhone($order->customer_id), orderId: $order->id);
    }

    public function resendReceipt(PaymentReceipt $receipt): void
    {
        if ($receipt->isVoided()) {
            throw new BusinessRuleException(__('messages.whatsapp_receipt_voided'), 422);
        }

        $this->manual(WhatsappMessageType::Payment, $this->customerPhone($receipt->customer_id), receiptId: $receipt->id);
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
            ->with(['order:id,order_no', 'receipt:id,receipt_no'])
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when(isset($filters['from']) || isset($filters['to']), function (Builder $query) use ($filters): void {
                $period = Period::fromFilters($filters);
                $query->whereBetween('created_at', [$period->start, $period->end]);
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? config('paginate.default'));
    }

    private function automatic(WhatsappMessageType $type, bool $enabled, ?int $customerId, ?int $orderId = null, ?int $receiptId = null): void
    {
        $tenant = tenantContext()->tenant();
        $recipient = PhoneNormalizer::toWhatsapp($this->customerPhone($customerId));

        $skip = match (true) {
            ! $enabled => WhatsappMessageStatus::SkippedDisabled,
            blank($tenant->whatsapp_device_id) => WhatsappMessageStatus::SkippedNoDevice,
            $recipient === null => WhatsappMessageStatus::SkippedInvalidPhone,
            default => null,
        };

        if ($skip !== null) {
            WhatsappMessage::query()->create([
                'type' => $type,
                'order_id' => $orderId,
                'receipt_id' => $receiptId,
                'recipient' => $recipient === null ? null : PhoneNormalizer::mask($recipient),
                'status' => $skip,
            ]);

            return;
        }

        // After commit, and a queue outage is only reported: the sale is already saved.
        DB::afterCommit(fn () => rescue(fn () => $this->dispatch($tenant, $type, $recipient, $orderId, $receiptId)));
    }

    private function manual(WhatsappMessageType $type, ?string $phone, ?int $orderId = null, ?int $receiptId = null): void
    {
        $tenant = tenantContext()->tenant();

        if (blank($tenant->whatsapp_device_id)) {
            throw new BusinessRuleException(__('messages.whatsapp_not_connected'), 422);
        }

        $recipient = PhoneNormalizer::toWhatsapp($phone);

        if ($recipient === null) {
            throw new BusinessRuleException(__('messages.whatsapp_invalid_phone'), 422);
        }

        $this->dispatch($tenant, $type, $recipient, $orderId, $receiptId);
    }

    private function dispatch(Tenant $tenant, WhatsappMessageType $type, string $recipient, ?int $orderId, ?int $receiptId): void
    {
        SendWhatsappMessageJob::dispatch(
            $tenant->id,
            $type->value,
            $recipient,
            (string) $tenant->whatsapp_device_id,
            $orderId,
            $receiptId,
        );
    }

    /**
     * A deleted customer still gets the proof of what they paid.
     */
    private function customerPhone(?int $customerId): ?string
    {
        return $customerId === null ? null : Customer::query()->withTrashed()->whereKey($customerId)->value('phone');
    }
}
