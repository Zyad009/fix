<?php

namespace App\Services\V1;

use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Locking and the "who may touch this order" rules shared by order services.
 */
class OrderGuard
{
    /**
     * Re-read the order with a row lock (inside a transaction).
     */
    public function lock(Order $order): Order
    {
        return Order::query()->withoutGlobalScope('branch')->whereKey($order->id)->lockForUpdate()->firstOrFail();
    }

    /**
     * Lock an order whose lines/header may change: open orders by anyone who
     * can create orders; completed invoices only by the owner; never a
     * cancelled or opening-balance order.
     */
    public function lockEditable(Order $order): Order
    {
        $order = $this->lock($order);

        if ($order->isCancelled()) {
            throw new BusinessRuleException(__('messages.order_cancelled_locked'), 422);
        }

        if ($order->is_opening_balance) {
            throw new BusinessRuleException(__('messages.opening_balance_locked'), 422);
        }

        if ($order->isCompleted() && ! actor()?->hasPermission('orders.edit_completed')) {
            throw new BusinessRuleException(__('messages.order_completed_locked'), 403);
        }

        return $order;
    }

    /**
     * Staff without `orders.view_closed` (technicians) only see open orders.
     */
    public function ensureVisible(Order $order): void
    {
        if (! $order->isOpen() && ! actor()?->hasPermission('orders.view_closed')) {
            throw (new ModelNotFoundException)->setModel(Order::class, [$order->id]);
        }
    }
}
