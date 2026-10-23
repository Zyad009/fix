<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $address
 * @property int $next_order_no
 * @property int $next_receipt_no
 * @property bool $is_default
 * @property bool $is_active
 */
#[Fillable(['name', 'phone', 'address', 'is_default', 'is_active'])]
class Branch extends Model
{
    use BelongsToTenant;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'next_order_no' => 'integer',
            'next_receipt_no' => 'integer',
        ];
    }

    /**
     * Reserve the next sequential number of a counter (orders, receipts).
     * Must run inside a transaction: the row lock serializes concurrent sales.
     *
     * @param  'next_order_no'|'next_receipt_no'  $counter
     */
    public static function takeNumber(int $branchId, string $counter): int
    {
        $branch = static::query()->whereKey($branchId)->lockForUpdate()->firstOrFail();
        $number = $branch->{$counter};

        static::query()->whereKey($branchId)->increment($counter);

        return $number;
    }
}
