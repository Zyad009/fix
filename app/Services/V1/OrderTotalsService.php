<?php

namespace App\Services\V1;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Support\Money;

/**
 * Recomputes an order's denormalized money columns from their source rows
 * (never incrementally). Call inside the transaction that changed the
 * items, discount or payments, with the order row locked.
 */
class OrderTotalsService
{
    public function recalculate(Order $order): Order
    {
        $subtotal = Money::of((string) OrderItem::query()->where('order_id', $order->id)->sum('line_total'));

        $paid = Money::of((string) Payment::query()->withoutGlobalScope('branch')
            ->where('order_id', $order->id)
            ->notVoided()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END), 0) AS net")
            ->value('net'));

        $discount = Money::of($order->discount);

        if ($discount->isGreaterThan($subtotal)) {
            throw new BusinessRuleException(__('messages.discount_exceeds_subtotal'), 422);
        }

        $total = $subtotal->minus($discount);

        if ($total->isLessThan($paid)) {
            throw new BusinessRuleException(__('messages.total_below_paid', ['paid' => (string) $paid]), 422);
        }

        $order->forceFill([
            'subtotal' => (string) $subtotal,
            'total' => (string) $total,
            'paid' => (string) $paid,
        ])->save();

        return $order;
    }
}
