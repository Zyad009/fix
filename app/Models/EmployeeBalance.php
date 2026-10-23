<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Read-only `employee_balances` view. Positive = the center owes the employee.
 *
 * @property int $employee_id
 * @property string $balance
 */
class EmployeeBalance extends Model
{
    use BelongsToTenant;

    protected $table = 'employee_balances';

    protected $primaryKey = 'employee_id';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['balance' => 'decimal:2'];
    }
}
