<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\PlateNormalizer;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $plate_number
 * @property string $plate_normalized
 * @property string|null $vin
 * @property int|null $car_make_id
 * @property int|null $car_model_id
 * @property int|null $year
 * @property string|null $color
 * @property int|null $last_odometer
 * @property Carbon|null $last_visit_at
 * @property string|null $notes
 */
#[Fillable(['customer_id', 'plate_number', 'vin', 'car_make_id', 'car_model_id', 'year', 'color', 'notes', 'created_by'])]
class Vehicle extends Model
{
    use BelongsToTenant, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'last_odometer' => 'integer',
            'last_visit_at' => 'datetime',
        ];
    }

    /**
     * Keep the display form as typed and the normalized form for search/uniqueness.
     *
     * @return Attribute<string, array<string, string>>
     */
    protected function plateNumber(): Attribute
    {
        return Attribute::make(set: fn (string $value): array => [
            'plate_number' => trim($value),
            'plate_normalized' => PlateNormalizer::normalize($value),
        ]);
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function vin(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => filled($value) ? mb_strtoupper(preg_replace('/\s+/', '', $value)) : null);
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    /**
     * @return BelongsTo<CarMake, $this>
     */
    public function carMake(): BelongsTo
    {
        return $this->belongsTo(CarMake::class, 'car_make_id');
    }

    /**
     * @return BelongsTo<CarModel, $this>
     */
    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class, 'car_model_id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
