<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\EmployeeRequest;
use App\Http\Requests\V1\Center\EmployeeTransactionRequest;
use App\Http\Requests\V1\Center\IndexEmployeeRequest;
use App\Http\Requests\V1\Center\PayrollRequest;
use App\Http\Requests\V1\Center\VoidRequest;
use App\Http\Resources\V1\Center\EmployeeResource;
use App\Http\Resources\V1\Center\EmployeeTransactionResource;
use App\Models\Employee;
use App\Models\EmployeeTransaction;
use App\Services\V1\EmployeeService;
use App\Services\V1\EmployeeTransactionService;
use App\Services\V1\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class EmployeeController extends Controller implements HasMiddleware
{
    protected string $resource = EmployeeResource::class;

    public function __construct(
        protected EmployeeService $employeeService,
        protected EmployeeTransactionService $transactionService,
        protected PayrollService $payrollService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:orders.create', only: ['technicians']),
            new Middleware('permission:payroll.manage', except: ['technicians']),
        ];
    }

    public function index(IndexEmployeeRequest $request): JsonResponse
    {
        try {
            return $this->paginate($this->employeeService->list($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Active employees of the branch, for the order's technician picker.
     */
    public function technicians(): JsonResponse
    {
        try {
            return $this->response($this->employeeService->active()->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->name,
                'job_title' => $employee->job_title,
            ]));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(EmployeeRequest $request): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->employeeService->store($request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(Employee $employee): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->employeeService->show($employee)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(EmployeeRequest $request, Employee $employee): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->employeeService->update($employee, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->employeeService->toggle($id)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Account statement: ledger movements and the current balance.
     */
    public function statement(IndexEmployeeRequest $request, Employee $employee): JsonResponse
    {
        try {
            $statement = $this->employeeService->statement($employee, $request->validated());
            $transactions = $statement['transactions'];

            return $this->response([
                'employee' => new $this->resource($statement['employee']),
                'balance' => $statement['balance']->toFloat(),
                'transactions' => EmployeeTransactionResource::collection($transactions),
            ], pagination: self::formatPagination($transactions));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function storeTransaction(EmployeeTransactionRequest $request, Employee $employee): JsonResponse
    {
        try {
            $transaction = $this->transactionService->record($employee, $request->validated());

            return $this->response(new EmployeeTransactionResource($transaction), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function voidTransaction(VoidRequest $request, EmployeeTransaction $transaction): JsonResponse
    {
        try {
            return $this->response(new EmployeeTransactionResource($this->transactionService->void($transaction, $request->validated('reason'))), __('messages.voided'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function payrollPreview(PayrollRequest $request): JsonResponse
    {
        try {
            return $this->response($this->payrollService->preview($request->validated('period')));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Issue the month's salaries for every active employee of the branch.
     */
    public function payrollIssue(PayrollRequest $request): JsonResponse
    {
        try {
            $overrides = collect($request->validated('items', []))->pluck('amount', 'employee_id')->map(fn ($amount) => (string) $amount)->all();
            $result = $this->payrollService->issue($request->validated('period'), $overrides, $request->validated('notes'));

            return $this->response($result, __('messages.payroll_issued', ['count' => $result['issued']]), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
