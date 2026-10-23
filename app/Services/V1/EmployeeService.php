<?php

namespace App\Services\V1;

use App\Models\Employee;
use App\Models\EmployeeBalance;
use App\Models\EmployeeTransaction;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class EmployeeService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Employee>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['branch:id,name', 'balance', 'user:id,name,email'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")))
            ->when(isset($filters['is_active']), fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active']))
            ->orderBy('name')
            ->paginate($filters['per_page'] ?? config('paginate.med'));
    }

    /**
     * Active employees of the selected branch (technician pickers).
     *
     * @return Collection<int, Employee>
     */
    public function active(): Collection
    {
        return Employee::query()->where('is_active', true)->orderBy('name')->get(['id', 'branch_id', 'name', 'job_title']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Employee
    {
        $employee = Employee::query()->create([
            ...Arr::only($data, ['user_id', 'name', 'phone', 'job_title', 'base_salary', 'hired_at']),
            'branch_id' => branchContext()->requireId(),
            'created_by' => actor()?->id,
        ]);

        return $this->show($employee);
    }

    public function show(Employee $employee): Employee
    {
        return $employee->load(['branch:id,name', 'balance', 'user:id,name,email']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        $employee->update(Arr::only($data, ['user_id', 'name', 'phone', 'job_title', 'base_salary', 'hired_at']));

        return $this->show($employee);
    }

    public function toggle(int $employeeId): Employee
    {
        $employee = Employee::query()->findOrFail($employeeId);
        $employee->update(['is_active' => ! $employee->is_active]);

        return $this->show($employee);
    }

    /**
     * Ledger statement: every non-voided movement and the running balance
     * (positive = the center owes the employee).
     *
     * @param  array<string, mixed>  $filters
     * @return array{employee: Employee, balance: Money, transactions: LengthAwarePaginator<int, EmployeeTransaction>}
     */
    public function statement(Employee $employee, array $filters): array
    {
        $transactions = $employee->transactions()
            ->with(['creator:id,name', 'voidedBy:id,name'])
            ->when(! ($filters['include_voided'] ?? false), fn ($query) => $query->notVoided())
            ->latest('occurred_at')
            ->latest('id')
            ->paginate($filters['per_page'] ?? config('paginate.med'));

        return [
            'employee' => $this->show($employee),
            'balance' => $this->balance($employee->id),
            'transactions' => $transactions,
        ];
    }

    public function balance(int $employeeId): Money
    {
        return Money::of((string) (EmployeeBalance::query()->whereKey($employeeId)->value('balance') ?? '0'));
    }
}
