<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Product/service category of the mini POS (oils, filters, tyres, labour…).
 *
 * @property int $id
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
#[Fillable(['name', 'sort_order', 'is_active'])]
class Category extends Model
{
    use BelongsToTenant;

    /** @var list<string> */
    public const DEFAULTS = ['زيوت', 'فلاتر', 'كاوتش', 'بطاريات', 'عمالة / مصنعية'];

    /** @var array<string, mixed> */
    protected $attributes = [
        'sort_order' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
