<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * On-hand quantity of a product in one branch. Written only by StockService.
 *
 * @property int $branch_id
 * @property int $product_id
 * @property string $qty
 */
#[Fillable(['branch_id', 'product_id', 'qty'])]
class ProductBranchStock extends Model
{
    use BelongsToBranch, BelongsToTenant;

    protected $table = 'product_branch_stock';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['qty' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
