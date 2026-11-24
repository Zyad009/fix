<?php

namespace App\Models;

use App\Models\Concerns\BelongsToMaintenanceCenter;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A recurring job the center does often, with no fixed price: the price is
 * typed on each invoice line and suggested from the prices used before.
 *
 * @property int $id
 * @property string $name
 * @property bool $is_active
 * @property int|null $usage_count
 * @property string|null $last_used_at
 */
#[Fillable(['name', 'is_active', 'created_by'])]
class RoutineService extends Model
{
    use BelongsToMaintenanceCenter;
    use SoftDeletes;

    /**
     * Seeded for every maintenance center (new ones on creation, existing
     * ones by DefaultRoutineServicesSeeder).
     *
     * @var list<string>
     */
    public const DEFAULTS = [
        'تغيير زيت المحرك',
        'تغيير فلتر الزيت',
        'تغيير فلتر الهواء',
        'تغيير فلتر التكييف',
        'تغيير فلتر البنزين',
        'تغيير زيت الفتيس',
        'تغيير زيت الفرامل',
        'تغيير مياه الرديتر',
        'تغيير تيل أمامي',
        'تغيير تيل خلفي',
        'خرط طنابير',
        'تركيب كاوتش',
        'ترصيص عجل',
        'ظبط زوايا',
        'إصلاح كاوتش (بنشر)',
        'تغيير بوجيهات',
        'تغيير سير المحرك',
        'تنظيف بوابة الهواء (ثروتل)',
        'تنظيف بخاخات',
        'غسيل رديتر',
        'تغيير بطارية',
        'شحن فريون تكييف',
        'كشف كمبيوتر',
        'تغيير مساعدين',
        'فحص فني',
    ];

    /**
     * Computed by RoutineServiceService, never stored.
     */
    public ?string $suggested_price = null;

    /**
     * Price distribution of the suggestion window (show only), never stored.
     *
     * @var list<array{price: float|int, uses: int}>|null
     */
    public ?array $prices = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
