<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\Voidable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money in (payment) or back out (refund) against one order.
 *
 * @property int $id
 * @property int|null $receipt_id
 * @property int $order_id
 * @property PaymentType $type
 * @property string $amount
 * @property PaymentMethod $method
 * @property Carbon $paid_at
 */
#[Fillable(['branch_id', 'receipt_id', 'order_id', 'type', 'amount', 'method', 'paid_at', 'received_by', 'notes'])]
class Payment extends Model
{
    use BelongsToBranch, BelongsToTenant, Voidable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<PaymentReceipt, $this>
     */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PaymentReceipt::class, 'receipt_id')->withoutGlobalScope('branch');
    }
}
