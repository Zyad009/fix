<?php

namespace App\Services\V1;

use App\Enums\OrderStatus;
use App\Enums\ProductKind;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;

/**
 * Old debts from the paper ledger: recorded as a completed order flagged
 * `is_opening_balance`, so they count as debt but never as sales.
 */
class OpeningBalanceService
{
    public function __construct(
        private readonly OrderTotalsService $totals,
        private readonly OrderService $orders,
    ) {}

    public function create(Vehicle $vehicle, string $amount, ?string $notes = null): Order
    {
        return DB::transaction(function () use ($vehicle, $amount, $notes): Order {
            $branchId = branchContext()->requireId();

            $order = Order::query()->create([
                'branch_id' => $branchId,
                'order_no' => Branch::takeNumber($branchId, 'next_order_no'),
                'customer_id' => $vehicle->customer_id,
                'vehicle_id' => $vehicle->id,
                'status' => OrderStatus::Completed,
                'is_opening_balance' => true,
                'notes' => $notes,
                'created_by' => actor()?->id,
            ]);

            $order->forceFill(['completed_at' => now(), 'completed_by' => actor()?->id])->save();

            $order->items()->create([
                'name' => __('messages.opening_balance_item'),
                'kind' => ProductKind::Service,
                'qty' => 1,
                'unit_price' => $amount,
                'created_by' => actor()?->id,
            ]);

            $this->totals->recalculate($order);

            return $this->orders->load($order);
        });
    }
}
