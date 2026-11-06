<?php

namespace App\Models;

use App\Enums\WhatsappMessageStatus;
use App\Enums\WhatsappMessageType;
use App\Models\Concerns\BelongsToMaintenanceCenter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One row per message: sent, failed for good, or skipped before the gateway.
 *
 * @property int $id
 * @property WhatsappMessageType $type
 * @property int|null $order_id
 * @property int|null $receipt_id
 * @property int|null $customer_id
 * @property string|null $recipient
 * @property WhatsappMessageStatus $status
 * @property string|null $provider_message_id
 * @property string|null $error
 * @property Carbon|null $sent_at
 */
#[Fillable(['type', 'order_id', 'receipt_id', 'customer_id', 'recipient', 'status', 'provider_message_id', 'error', 'sent_at'])]
class WhatsappMessage extends Model
{
    use BelongsToMaintenanceCenter;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => WhatsappMessageType::class,
            'status' => WhatsappMessageStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScope('branch');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<PaymentReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'receipt_id')->withoutGlobalScope('branch');
    }
}
