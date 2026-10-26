<?php

namespace App\Services\V1;

use App\Enums\ProductKind;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Product>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($filters['per_page'] ?? config('paginate.med'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Product>
     */
    public function query(array $filters): Builder
    {
        return Product::query()
            ->with(['category:id,name', 'stocks'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('barcode', $q)))
            ->when($filters['category_id'] ?? null, fn (Builder $query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['kind'] ?? null, fn (Builder $query, string $kind) => $query->where('kind', $kind))
            ->when(isset($filters['is_active']), fn (Builder $query) => $query->where('is_active', (bool) $filters['is_active']))
            ->orderBy('name');
    }

    /**
     * POS screen: active categories with their active products and stock.
     * `q` is a scanned barcode (exact match wins) or part of a name; only
     * categories with a match are returned then.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Category>
     */
    public function catalog(array $filters = []): Collection
    {
        $q = $filters['q'] ?? null;
        $barcodeHit = $q !== null && Product::query()->where('is_active', true)->where('barcode', $q)->exists();

        $products = fn ($query) => $query->where('is_active', true)
            ->when($q !== null, fn ($query) => $barcodeHit
                ? $query->where('barcode', $q)
                : $query->where('name', 'like', "%{$q}%"));

        return Category::query()
            ->where('is_active', true)
            ->when($q !== null, fn ($query) => $query->whereHas('products', $products))
            ->with(['products' => fn ($query) => $products($query)->with('stocks')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data, ?UploadedFile $image = null): Product
    {
        $product = Product::query()->create([...$this->attributes($data), 'created_by' => actor()?->id]);

        if ($image) {
            $this->updateImage($product, $image);
        }

        return $product->load(['category:id,name', 'stocks']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Product $product, array $data): Product
    {
        $product->update($this->attributes([...['kind' => $product->kind->value], ...$data]));

        return $product->load(['category:id,name', 'stocks']);
    }

    public function updateImage(Product $product, UploadedFile $image): Product
    {
        $disk = config('filesystems.default');
        $path = $image->store('tenants/'.tenantContext()->id().'/products', $disk);

        if ($product->image_path) {
            Storage::disk($disk)->delete($product->image_path);
        }

        $product->update(['image_path' => $path]);

        return $product->load(['category:id,name', 'stocks']);
    }

    public function toggle(int $productId): Product
    {
        $product = Product::query()->findOrFail($productId);
        $product->update(['is_active' => ! $product->is_active]);

        return $product->load(['category:id,name', 'stocks']);
    }

    /**
     * Soft delete only: past invoices keep their snapshot and their link.
     */
    public function destroy(Product $product): void
    {
        $product->delete();
    }

    /**
     * Services never carry stock.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $attributes = Arr::only($data, ['category_id', 'name', 'barcode', 'kind', 'sale_price', 'cost_price', 'track_stock']);

        if (($data['kind'] ?? null) === ProductKind::Service->value) {
            $attributes['track_stock'] = false;
        }

        return $attributes;
    }
}
