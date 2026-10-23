<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\CategoryRequest;
use App\Http\Requests\V1\Center\IndexProductRequest;
use App\Http\Resources\V1\Center\CategoryResource;
use App\Models\Category;
use App\Services\V1\CategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryController extends Controller implements HasMiddleware
{
    protected string $resource = CategoryResource::class;

    public function __construct(protected CategoryService $categoryService) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:catalog.view', only: ['index', 'show']),
            new Middleware('permission:catalog.manage', except: ['index', 'show']),
        ];
    }

    public function index(IndexProductRequest $request): JsonResponse
    {
        try {
            return $this->response($this->resource::collection($this->categoryService->list($request->validated())));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->categoryService->store($request->validated())), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(Category $category): JsonResponse
    {
        try {
            return $this->response(new $this->resource($category->loadCount('products')));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(CategoryRequest $request, Category $category): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->categoryService->update($category, $request->validated())), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function destroy(Category $category): JsonResponse
    {
        try {
            $this->categoryService->destroy($category);

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
