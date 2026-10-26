<?php

namespace App\Http\Controllers\V1\Center;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Center\IndexProductRequest;
use App\Http\Requests\V1\Center\ProductRequest;
use App\Http\Requests\V1\Center\StockRequest;
use App\Http\Requests\V1\ImageRequest;
use App\Http\Requests\V1\ListRequest;
use App\Http\Resources\V1\Center\CategoryResource;
use App\Http\Resources\V1\Center\ProductResource;
use App\Models\Product;
use App\Services\V1\ProductService;
use App\Services\V1\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductController extends Controller implements HasMiddleware
{
    protected string $resource = ProductResource::class;

    public function __construct(
        protected ProductService $productService,
        protected StockService $stockService,
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware('permission:catalog.view', only: ['index', 'show', 'catalog']),
            new Middleware('permission:catalog.manage', only: ['store', 'update', 'image', 'destroy', 'toggle']),
            new Middleware('permission:stock.manage', only: ['stock']),
        ];
    }

    public function index(IndexProductRequest $request): JsonResponse|BinaryFileResponse
    {
        try {
            if ($this->wantsExcel($request)) {
                $rows = $this->productService->query($request->validated())->get()
                    ->map(fn (Product $product): array => [
                        $product->name,
                        $product->category?->name,
                        $product->kind->label(),
                        formatNumber($product->sale_price),
                        $product->track_stock ? formatNumber((string) $product->stocks->sum('qty')) : null,
                    ])->all();

                return $this->excel('products', ['الاسم', 'التصنيف', 'النوع', 'سعر البيع', 'الرصيد'], $rows);
            }

            return $this->paginate($this->productService->list($request->validated()), $this->resource);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * POS screen in one call: categories → active products (+ branch stock),
     * and the branch's next invoice number (display only: the real number is
     * taken when the order is saved).
     */
    public function catalog(ListRequest $request): JsonResponse
    {
        try {
            return $this->response([
                'next_order_no' => branchContext()->resolve()?->fresh()?->next_order_no,
                'categories' => CategoryResource::collection($this->productService->catalog($request->validated())),
            ]);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function store(ProductRequest $request): JsonResponse
    {
        try {
            $product = $this->productService->store($request->safe()->except('image'), $request->file('image'));

            return $this->response(new $this->resource($product), __('messages.created'), 201);
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function show(Product $product): JsonResponse
    {
        try {
            return $this->response(new $this->resource($product->load(['category:id,name', 'stocks'])));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function update(ProductRequest $request, Product $product): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->productService->update($product, $request->safe()->except('image'))), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function image(ImageRequest $request, Product $product): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->productService->updateImage($product, $request->file('image'))), __('messages.updated'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function destroy(Product $product): JsonResponse
    {
        try {
            $this->productService->destroy($product);

            return $this->response([], __('messages.deleted'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    public function toggle(int $id): JsonResponse
    {
        try {
            return $this->response(new $this->resource($this->productService->toggle($id)), __('messages.status_changed'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }

    /**
     * Owner's manual stock count for the selected branch.
     */
    public function stock(StockRequest $request, Product $product): JsonResponse
    {
        try {
            $this->stockService->set($product, branchContext()->requireId(), (string) $request->validated('qty'), $request->validated('reason'));

            return $this->response(new $this->resource($product->load(['category:id,name', 'stocks'])), __('messages.stock_adjusted'));
        } catch (\Throwable $th) {
            return $this->failed($th);
        }
    }
}
