<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Enums\EmployeeTransactionType;
use App\Exceptions\BusinessRuleException;
use App\Models\Employee;
use App\Models\EmployeeTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Salary, bonus, deduction, advance and payment movements on an employee's
 * ledger. Advances simply reduce what the employee is owed — no special
 * payroll logic is needed.
 */
class EmployeeTransactionService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * @param  array{type: string, amount: string, period?: ?string, occurred_at?: ?string, notes?: ?string}  $data
     */
    public function record(Employee $employee, array $data): EmployeeTransaction
    {
        $type = EmployeeTransactionType::from($data['type']);

        if ($type === EmployeeTransactionType::Salary && empty($data['period'])) {
            throw new BusinessRuleException(__('messages.period_required'), 422);
        }

        if (! $employee->is_active) {
            throw new BusinessRuleException(__('messages.employee_inactive'), 422);
        }

        try {
            return EmployeeTransaction::query()->create([
                'branch_id' => $employee->branch_id,
                'employee_id' => $employee->id,
                'type' => $type,
                'amount' => $data['amount'],
                'period' => isset($data['period']) ? self::periodStart($data['period']) : null,
                'occurred_at' => $data['occurred_at'] ?? now(tenantContext()->tenant()->timezone)->toDateString(),
                'notes' => $data['notes'] ?? null,
                'created_by' => actor()?->id,
            ])->load('creator:id,name');
        } catch (UniqueConstraintViolationException) {
            throw new BusinessRuleException(__('messages.salary_already_issued'), 409);
        }
    }

    public function void(EmployeeTransaction $transaction, string $reason): EmployeeTransaction
    {
        return DB::transaction(function () use ($transaction, $reason): EmployeeTransaction {
            $transaction = EmployeeTransaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();
            $transaction->markVoided($reason);

            $this->audit->record($transaction, AuditEvent::Voided, ['amount' => $transaction->amount, 'type' => $transaction->type->value], reason: $reason);

            return $transaction->load(['creator:id,name', 'voidedBy:id,name']);
        });
    }

    /**
     * "2026-10" or any date in the month → first day of that month.
     */
    public static function periodStart(string $period): string
    {
        $date = preg_match('/^\d{4}-\d{2}$/', $period) ? "{$period}-01" : $period;

        return CarbonImmutable::parse($date)->startOfMonth()->toDateString();
    }
}
