<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\ExpenseRequest;
use App\Http\Requests\V1\Center\IndexExpenseRequest;
use App\Http\Requests\V1\Center\VoidRequest;
use App\Http\Resources\V1\Center\ExpenseResource;
use App\Models\Expense;
use App\Services\V1\ExpenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExpenseController extends Controller implements HasMiddleware
{
    protected string $resource = ExpenseResource::class;

    public function __construct(protected ExpenseService $expenseService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:expenses.view', only: ['index', 'show', 'summary']),
            new Middleware('permission:expenses.manage', only: ['store']),
            new Middleware('permission:expenses.void', only: ['void']),
        ];
    }

    public function index(IndexExpenseRequest $request): JsonResponse|BinaryFileResponse
    {
        try {
            if ($this->wantsExcel($request)) {
                $rows = $this->expenseService->query($request->validated())->with('category:id,name')->latest('spent_at')->get()
                    ->map(fn (Expense $expense): array => [
                        $expense->spent_at?->toDateString(),
                        $expense->category?->name,
                        formatNumber($expense->amount),
                        $expense->method->label(),
                        $expense->notes,
                    ])->all();

                return $this->excel('expenses', ['التاريخ', 'التصنيف', 'المبلغ', 'طريقة الدفع', 'ملاحظات'], $rows);
            }

            return $this->paginate($this->expenseService->list($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function summary(IndexExpenseRequest $request): JsonResponse
    {
        try {
            return $this->response($this->expenseService->summary($request->validated()));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(ExpenseRequest $request): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->expenseService->store($request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(Expense $expense): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->expenseService->load($expense)));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function void(VoidRequest $request, Expense $expense): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->expenseService->void($expense, $request->validated('reason'))), __('messages.voided'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
