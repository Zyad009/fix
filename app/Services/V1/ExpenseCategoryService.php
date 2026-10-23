<?php

namespace App\Services\V1;

use App\Exceptions\BusinessRuleException;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class ExpenseCategoryService
{
    /**
     * @return Collection<int, ExpenseCategory>
     */
    public function list(): Collection
    {
        return ExpenseCategory::query()->orderBy('name')->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): ExpenseCategory
    {
        return ExpenseCategory::query()->create(Arr::only($data, ['name']));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ExpenseCategory $category, array $data): ExpenseCategory
    {
        $category->update(Arr::only($data, ['name']));

        return $category;
    }

    public function toggle(int $categoryId): ExpenseCategory
    {
        $category = ExpenseCategory::query()->findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);

        return $category;
    }

    public function destroy(ExpenseCategory $category): void
    {
        if (Expense::query()->withoutGlobalScope('branch')->where('category_id', $category->id)->exists()) {
            throw new BusinessRuleException(__('messages.expense_category_in_use'), 409);
        }

        $category->delete();
    }
}
