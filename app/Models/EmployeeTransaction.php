<?php

namespace App\Models;

use App\Enums\EmployeeTransactionType;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\Voidable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property EmployeeTransactionType $type
 * @property string $amount
 * @property Carbon|null $period
 * @property Carbon $occurred_at
 * @property string|null $notes
 */
#[Fillable(['branch_id', 'employee_id', 'type', 'amount', 'period', 'occurred_at', 'notes', 'created_by'])]
class EmployeeTransaction extends Model
{
    use BelongsToBranch, BelongsToTenant, Voidable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => EmployeeTransactionType::class,
            'amount' => 'decimal:2',
            'period' => 'date',
            'occurred_at' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
