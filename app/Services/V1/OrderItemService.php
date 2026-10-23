<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Enums\ProductKind;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Money;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Invoice lines. Catalog lines snapshot name/price/cost from the product at
 * the moment they are added; free lines are typed by hand (product_id NULL).
 * On a completed invoice (owner only) stock follows every change immediately.
 */
class OrderItemService
{
    public function __construct(
        private readonly OrderGuard $guard,
        private readonly OrderTotalsService $totals,
        private readonly StockService $stock,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{order: Order, warnings: list<string>}
     */
    public function add(Order $order, array $data): array
    {
        return DB::transaction(function () use ($order, $data): array {
            $order = $this->guard->lockEditable($order);
            $warnings = $this->addLines($order, [$data]);
            $this->totals->recalculate($order);

            return ['order' => $order, 'warnings' => $warnings];
        });
    }

    /**
     * Add several lines to an already locked, editable order.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<string>
     */
    public function addLines(Order $order, array $lines): array
    {
        $warnings = [];

        foreach ($lines as $data) {
            $item = $order->items()->create([...$this->buildLine($data), 'created_by' => actor()?->id]);

            if ($order->isCompleted()) {
                $warnings = [...$warnings, ...$this->moveStock($order, $item->product_id, BigDecimal::of($item->qty)->negated())];
                $this->audit->record($order, AuditEvent::ItemAdded, new: $item->only(['id', 'name', 'qty', 'unit_price']));
            }
        }

        return $warnings;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{order: Order, warnings: list<string>}
     */
    public function update(Order $order, OrderItem $item, array $data): array
    {
        return DB::transaction(function () use ($order, $item, $data): array {
            $order = $this->guard->lockEditable($order);
            $item = $order->items()->whereKey($item->id)->firstOrFail();
            $old = $item->only(['name', 'qty', 'unit_price', 'unit_cost']);

            if (isset($data['unit_price']) && $item->product_id !== null && ! Money::of($data['unit_price'])->isEqualTo($item->unit_price)) {
                $this->assertCanOverridePrice();
            }

            $attributes = array_intersect_key($data, array_flip(['qty', 'unit_price']));

            if ($item->product_id === null) {
                $attributes += array_intersect_key($data, array_flip(['name', 'kind']));

                if (array_key_exists('unit_cost', $data) && actor()?->hasPermission('costs.view')) {
                    $attributes['unit_cost'] = $data['unit_cost'];
                }
            }

            $item->update($attributes);
            $warnings = [];

            if ($order->isCompleted()) {
                $qtyChange = BigDecimal::of($item->qty)->minus($old['qty']);
                $warnings = $this->moveStock($order, $item->product_id, $qtyChange->negated());
                $this->audit->record($order, AuditEvent::ItemUpdated, ['item_id' => $item->id, ...$old], ['item_id' => $item->id, ...$item->only(['name', 'qty', 'unit_price', 'unit_cost'])]);
            }

            $this->totals->recalculate($order);

            return ['order' => $order, 'warnings' => $warnings];
        });
    }

    public function remove(Order $order, OrderItem $item): Order
    {
        return DB::transaction(function () use ($order, $item): Order {
            $order = $this->guard->lockEditable($order);
            $item = $order->items()->whereKey($item->id)->firstOrFail();

            if ($order->isCompleted()) {
                $this->moveStock($order, $item->product_id, BigDecimal::of($item->qty));
                $this->audit->record($order, AuditEvent::ItemRemoved, $item->only(['id', 'name', 'qty', 'unit_price']));
            }

            $item->delete();
            $this->totals->recalculate($order);

            return $order;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function buildLine(array $data): array
    {
        if (empty($data['product_id'])) {
            return [
                'product_id' => null,
                'name' => $data['name'],
                'kind' => $data['kind'] ?? ProductKind::Part->value,
                'qty' => $data['qty'],
                'unit_price' => $data['unit_price'],
                'unit_cost' => actor()?->hasPermission('costs.view') ? ($data['unit_cost'] ?? null) : null,
            ];
        }

        $product = Product::query()->findOrFail($data['product_id']);

        if (! $product->is_active) {
            throw new BusinessRuleException(__('messages.product_inactive'), 422);
        }

        $unitPrice = $data['unit_price'] ?? $product->sale_price;

        if (! Money::of($unitPrice)->isEqualTo($product->sale_price)) {
            $this->assertCanOverridePrice();
        }

        return [
            'product_id' => $product->id,
            'name' => $product->name,
            'kind' => $product->kind->value,
            'qty' => $data['qty'],
            'unit_price' => $unitPrice,
            'unit_cost' => $product->cost_price,
        ];
    }

    private function assertCanOverridePrice(): void
    {
        if (! actor()?->hasPermission('orders.price_override')) {
            throw new BusinessRuleException(__('messages.price_override_forbidden'), 403);
        }
    }

    /**
     * @return list<string>
     */
    private function moveStock(Order $order, ?int $productId, BigDecimal $delta): array
    {
        return $productId === null ? [] : $this->stock->apply($order->branch_id, [$productId => $delta]);
    }
}
