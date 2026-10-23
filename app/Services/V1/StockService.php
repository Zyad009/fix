<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Models\Product;
use App\Models\ProductBranchStock;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of branch stock. Selling never blocks on stock: a shortage
 * produces a warning, not an error. Callers must already be in a transaction.
 */
class StockService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Apply quantity changes (negative = out) for stock-tracked products.
     *
     * @param  array<int, string|BigDecimal>  $deltas  product_id => quantity change
     * @return list<string> warnings for products whose stock fell below zero
     */
    public function apply(int $branchId, array $deltas): array
    {
        $deltas = array_filter(
            array_map(fn (string|BigDecimal $delta): BigDecimal => BigDecimal::of($delta), $deltas),
            fn (BigDecimal $delta): bool => ! $delta->isZero(),
        );

        $products = Product::query()->withTrashed()
            ->whereIn('id', array_keys($deltas))
            ->where('track_stock', true)
            ->orderBy('id')
            ->pluck('name', 'id');

        if ($products->isEmpty()) {
            return [];
        }

        $this->ensureRows($branchId, $products->keys()->all());

        // Locks are always taken in product_id order so concurrent sales cannot deadlock.
        $rows = ProductBranchStock::query()->withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->whereIn('product_id', $products->keys())
            ->orderBy('product_id')
            ->lockForUpdate()
            ->get();

        $warnings = [];

        foreach ($rows as $row) {
            $delta = $deltas[$row->product_id];
            $newQty = BigDecimal::of($row->qty)->plus($delta);

            $row->update(['qty' => (string) $newQty]);

            if ($delta->isNegative() && $newQty->isNegative()) {
                $warnings[] = __('messages.stock_insufficient', ['product' => $products[$row->product_id], 'qty' => (string) $newQty]);
            }
        }

        return $warnings;
    }

    /**
     * Manual count correction by the owner (audited).
     */
    public function set(Product $product, int $branchId, string $qty, ?string $reason = null): ProductBranchStock
    {
        return DB::transaction(function () use ($product, $branchId, $qty, $reason): ProductBranchStock {
            $this->ensureRows($branchId, [$product->id]);

            $row = ProductBranchStock::query()->withoutGlobalScope('branch')
                ->where('branch_id', $branchId)
                ->where('product_id', $product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $old = $row->qty;
            $row->update(['qty' => $qty]);

            $this->audit->record($product, AuditEvent::StockAdjusted, ['branch_id' => $branchId, 'qty' => $old], ['branch_id' => $branchId, 'qty' => $row->qty], $reason);

            return $row;
        });
    }

    /**
     * @param  list<int>  $productIds
     */
    private function ensureRows(int $branchId, array $productIds): void
    {
        $now = now();

        DB::table('product_branch_stock')->insertOrIgnore(array_map(fn (int $productId): array => [
            'tenant_id' => tenantContext()->id(),
            'branch_id' => $branchId,
            'product_id' => $productId,
            'qty' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $productIds));
    }
}
