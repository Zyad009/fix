<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\PhoneNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string|null $notes
 */
#[Fillable(['name', 'phone', 'notes', 'created_by'])]
class Customer extends Model
{
    use BelongsToTenant, SoftDeletes;

    /**
     * @return Attribute<string, string>
     */
    protected function phone(): Attribute
    {
        return Attribute::make(set: fn (?string $value): string => PhoneNormalizer::normalize($value));
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
