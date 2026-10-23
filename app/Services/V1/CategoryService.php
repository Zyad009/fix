<?php

namespace App\Services\V1;

use App\Exceptions\BusinessRuleException;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

class CategoryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Category>
     */
    public function list(array $filters): Collection
    {
        return Category::query()
            ->withCount('products')
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Category
    {
        return Category::query()->create(Arr::only($data, ['name', 'sort_order']));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Category $category, array $data): Category
    {
        $category->update(Arr::only($data, ['name', 'sort_order']));

        return $category;
    }

    public function toggle(int $categoryId): Category
    {
        $category = Category::query()->findOrFail($categoryId);
        $category->update(['is_active' => ! $category->is_active]);

        return $category;
    }

    public function destroy(Category $category): void
    {
        if (Product::query()->withTrashed()->where('category_id', $category->id)->exists()) {
            throw new BusinessRuleException(__('messages.category_in_use'), 409);
        }

        $category->delete();
    }
}
