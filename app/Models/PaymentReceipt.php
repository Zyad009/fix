<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\Voidable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One collection handed to the customer; its amount is spread over one or
 * more orders as payment rows.
 *
 * @property int $id
 * @property int $receipt_no
 * @property int $customer_id
 * @property int|null $vehicle_id
 * @property string $amount
 * @property PaymentMethod $method
 * @property Carbon $received_at
 * @property string|null $notes
 */
#[Fillable(['branch_id', 'receipt_no', 'customer_id', 'vehicle_id', 'amount', 'method', 'received_by', 'received_at', 'notes'])]
class PaymentReceipt extends Model
{
    use BelongsToBranch, BelongsToTenant, Voidable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'method' => PaymentMethod::class,
            'received_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'receipt_id')->withoutGlobalScope('branch');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
