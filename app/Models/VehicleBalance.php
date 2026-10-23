<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Read-only `vehicle_balances` view: open debt per (customer, vehicle).
 *
 * @property int $customer_id
 * @property int $vehicle_id
 * @property string $balance
 * @property int $orders_count
 * @property string $oldest_due_at
 */
class VehicleBalance extends Model
{
    use BelongsToTenant;

    protected $table = 'vehicle_balances';

    protected $primaryKey = null;

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'orders_count' => 'integer',
            'oldest_due_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }
}
