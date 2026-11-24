<?php

namespace App\Models;

use App\Enums\ProductKind;
use App\Models\Concerns\BelongsToMaintenanceCenter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invoice line. Name, price and cost are snapshots taken at sale time, so
 * later catalog changes never alter past invoices. product_id NULL = free item,
 * unless routine_service_id is set (a routine service with a typed price).
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $routine_service_id
 * @property string $name
 * @property ProductKind $kind
 * @property string $qty
 * @property string $unit_price
 * @property string|null $unit_cost
 * @property string $line_total
 */
#[Fillable(['order_id', 'product_id', 'routine_service_id', 'name', 'kind', 'qty', 'unit_price', 'unit_cost', 'created_by'])]
class OrderItem extends Model
{
    use BelongsToMaintenanceCenter;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProductKind::class,
            'qty' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return BelongsTo<RoutineService, $this>
     */
    public function routineService(): BelongsTo
    {
        return $this->belongsTo(RoutineService::class)->withTrashed();
    }
}
