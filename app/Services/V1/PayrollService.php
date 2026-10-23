<?php

namespace App\Services\V1;

use App\Enums\EmployeeTransactionType;
use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "Issue this month's salaries": one `salary` credit per active employee of
 * the branch at their base salary (overridable), never twice for a month.
 */
class PayrollService
{
    public function __construct(private readonly EmployeeTransactionService $transactions) {}

    /**
     * @return list<array{employee_id: int, name: string, base_salary: float, already_issued: bool, balance: float}>
     */
    public function preview(string $period): array
    {
        $periodStart = EmployeeTransactionService::periodStart($period);
        $issued = $this->issuedEmployeeIds($periodStart);

        return $this->activeEmployees()
            ->map(fn (Employee $employee): array => [
                'employee_id' => $employee->id,
                'name' => $employee->name,
                'job_title' => $employee->job_title,
                'base_salary' => formatNumber($employee->base_salary),
                'already_issued' => in_array($employee->id, $issued, true),
                'balance' => formatNumber($employee->balance?->balance),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $overrides  employee_id => amount
     * @return array{issued: int, skipped: list<int>}
     */
    public function issue(string $period, array $overrides = [], ?string $notes = null): array
    {
        return DB::transaction(function () use ($period, $overrides, $notes): array {
            $periodStart = EmployeeTransactionService::periodStart($period);
            $issued = $this->issuedEmployeeIds($periodStart);
            $count = 0;
            $skipped = [];

            foreach ($this->activeEmployees() as $employee) {
                $amount = Money::of($overrides[$employee->id] ?? $employee->base_salary);

                if (in_array($employee->id, $issued, true) || ! $amount->isPositive()) {
                    $skipped[] = $employee->id;

                    continue;
                }

                $this->transactions->record($employee, [
                    'type' => EmployeeTransactionType::Salary->value,
                    'amount' => (string) $amount,
                    'period' => $periodStart,
                    'notes' => $notes,
                ]);
                $count++;
            }

            return ['issued' => $count, 'skipped' => $skipped];
        });
    }

    /**
     * @return Collection<int, Employee>
     */
    private function activeEmployees(): Collection
    {
        branchContext()->require();

        return Employee::query()->with('balance')->where('is_active', true)->orderBy('name')->get();
    }

    /**
     * @return list<int>
     */
    private function issuedEmployeeIds(string $periodStart): array
    {
        return EmployeeTransaction::query()
            ->notVoided()
            ->where('type', EmployeeTransactionType::Salary)
            ->whereDate('period', $periodStart)
            ->pluck('employee_id')
            ->all();
    }
}
