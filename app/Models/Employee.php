<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $job_title
 * @property string $base_salary
 * @property Carbon|null $hired_at
 * @property bool $is_active
 */
#[Fillable(['branch_id', 'user_id', 'name', 'phone', 'job_title', 'base_salary', 'hired_at', 'is_active', 'created_by'])]
class Employee extends Model
{
    use BelongsToBranch, BelongsToTenant;

    /** @var array<string, mixed> */
    protected $attributes = [
        'base_salary' => '0.00',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_salary' => 'decimal:2',
            'hired_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<EmployeeTransaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(EmployeeTransaction::class);
    }

    /**
     * @return HasOne<EmployeeBalance, $this>
     */
    public function balance(): HasOne
    {
        return $this->hasOne(EmployeeBalance::class);
    }
}
