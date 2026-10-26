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
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    /** @var list<string> */
    private const FIELDS = ['name', 'phone', 'job_title', 'base_salary', 'hired_at'];

    public function __construct(private readonly UserService $users) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Employee>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return Employee::query()
            ->with(['branch:id,name', 'balance', 'user:id,name,email,role,is_active'])
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
     * The employee and, when `account` is sent, their login in the same branch.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Employee
    {
        return DB::transaction(function () use ($data): Employee {
            $employee = Employee::query()->create([
                ...Arr::only($data, self::FIELDS),
                'branch_id' => branchContext()->requireId(),
                'created_by' => actor()?->id,
            ]);

            if (isset($data['account'])) {
                $this->createAccount($employee, $data['account']);
            }

            return $this->show($employee);
        });
    }

    public function show(Employee $employee): Employee
    {
        return $employee->load(['branch:id,name', 'balance', 'user:id,name,email,role,is_active']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data): Employee {
            $employee->update(Arr::only($data, self::FIELDS));
            $user = $employee->user()->first();

            if ($user && (isset($data['account']) || $employee->wasChanged('name'))) {
                $this->users->update($user, [...($data['account'] ?? []), 'name' => $employee->name]);
            } elseif (isset($data['account'])) {
                $this->createAccount($employee, $data['account']);
            }

            return $this->show($employee);
        });
    }

    /**
     * Stopping an employee also stops their login (and signs it out).
     */
    public function toggle(int $employeeId): Employee
    {
        return DB::transaction(function () use ($employeeId): Employee {
            $employee = Employee::query()->findOrFail($employeeId);
            $employee->update(['is_active' => ! $employee->is_active]);

            if ($user = $employee->user()->first()) {
                $user->update(['is_active' => $employee->is_active]);

                if (! $employee->is_active) {
                    $user->tokens()->delete();
                }
            }

            return $this->show($employee);
        });
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

    /**
     * @param  array<string, mixed>  $account
     */
    private function createAccount(Employee $employee, array $account): void
    {
        $user = $this->users->store([
            ...Arr::only($account, ['email', 'password', 'role']),
            'name' => $employee->name,
            'branch_id' => $employee->branch_id,
        ]);

        $employee->update(['user_id' => $user->id]);
    }
}
