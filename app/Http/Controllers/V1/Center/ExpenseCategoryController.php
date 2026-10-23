<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\ExpenseCategoryRequest;
use App\Http\Resources\V1\Center\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use App\Services\V1\ExpenseCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ExpenseCategoryController extends Controller implements HasMiddleware
{
    protected string $resource = ExpenseCategoryResource::class;

    public function __construct(protected ExpenseCategoryService $categoryService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:expenses.view', only: ['index']),
            new Middleware('permission:expenses.manage', except: ['index']),
        ];
    }

    public function index(): JsonResponse
    {
        try {
            return $this->response($this->resource::collection($this->categoryService->list()));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(ExpenseCategoryRequest $request): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->categoryService->store($request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(ExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->categoryService->update($expenseCategory, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        try {
            $this->categoryService->destroy($expenseCategory);

            return $this->response([], __('messages.deleted'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->categoryService->toggle($id)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
