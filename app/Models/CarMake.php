<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Platform-wide vehicle make (curated by the super admin).
 *
 * @property int $id
 * @property string $name_ar
 * @property string|null $name_en
 * @property bool $is_active
 */
#[Fillable(['name_ar', 'name_en', 'is_active'])]
class CarMake extends Model
{
    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<CarModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }
}
