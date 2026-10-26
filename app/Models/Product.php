<?php

namespace App\Models;

use App\Enums\ProductKind;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Catalog item: a part (stockable) or a service (labour).
 *
 * @property int $id
 * @property int|null $category_id
 * @property string $name
 * @property string|null $barcode
 * @property ProductKind $kind
 * @property string $sale_price
 * @property string|null $cost_price
 * @property string|null $image_path
 * @property bool $track_stock
 * @property bool $is_active
 */
#[Fillable(['category_id', 'name', 'barcode', 'kind', 'sale_price', 'cost_price', 'image_path', 'track_stock', 'is_active', 'created_by'])]
class Product extends Model
{
    use BelongsToTenant, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'kind' => 'part',
        'sale_price' => '0.00',
        'track_stock' => false,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ProductKind::class,
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductBranchStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(ProductBranchStock::class);
    }

    public function imageUrl(): ?string
    {
        return publicFileUrl($this->image_path);
    }
}
