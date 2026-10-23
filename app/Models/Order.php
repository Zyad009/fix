<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A visit; once completed it is the invoice.
 *
 * @property int $id
 * @property int $branch_id
 * @property int $order_no
 * @property int $customer_id
 * @property int $vehicle_id
 * @property int|null $technician_id
 * @property OrderStatus $status
 * @property int|null $odometer
 * @property int|null $next_service_odometer
 * @property Carbon|null $next_service_date
 * @property string $subtotal
 * @property string $discount
 * @property string $total
 * @property string $paid
 * @property bool $is_opening_balance
 * @property string|null $notes
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 */
#[Fillable([
    'branch_id', 'order_no', 'customer_id', 'vehicle_id', 'technician_id', 'status', 'odometer',
    'next_service_odometer', 'next_service_date', 'discount', 'is_opening_balance', 'notes', 'created_by',
])]
class Order extends Model
{
    use BelongsToBranch, BelongsToTenant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => 'open',
        'subtotal' => '0.00',
        'discount' => '0.00',
        'total' => '0.00',
        'paid' => '0.00',
        'is_opening_balance' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'odometer' => 'integer',
            'next_service_odometer' => 'integer',
            'next_service_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'is_opening_balance' => 'boolean',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === OrderStatus::Open;
    }

    public function isCompleted(): bool
    {
        return $this->status === OrderStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === OrderStatus::Cancelled;
    }

    public function remaining(): Money
    {
        return Money::of($this->total)->minus($this->paid);
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
     * @return BelongsTo<Employee, $this>
     */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'technician_id')->withoutGlobalScope('branch');
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->withoutGlobalScope('branch');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
