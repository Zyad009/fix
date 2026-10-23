<?php

namespace App\Services\V1;

use App\Models\VehicleBalance;
use App\Support\Money;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Debt is computed, never stored: SUM(total - paid) over completed orders,
 * read from the `vehicle_balances` view. A customer's debt is the sum of the
 * debts on their vehicles.
 */
class BalanceService
{
    /**
     * Correlated subquery for `customers` listings: the customer's open debt.
     */
    public function customerBalanceSubquery(): Builder
    {
        return DB::table('vehicle_balances')
            ->selectRaw('COALESCE(SUM(vehicle_balances.balance), 0)')
            ->whereColumn('vehicle_balances.customer_id', 'customers.id')
            ->whereColumn('vehicle_balances.tenant_id', 'customers.tenant_id');
    }

    public function customerBalance(int $customerId): Money
    {
        return Money::of((string) VehicleBalance::query()->where('customer_id', $customerId)->sum('balance'));
    }

    /**
     * Open debt on a vehicle, optionally only what a given customer owes on it.
     */
    public function vehicleBalance(int $vehicleId, ?int $customerId = null): Money
    {
        return Money::of((string) VehicleBalance::query()
            ->where('vehicle_id', $vehicleId)
            ->when($customerId, fn ($query) => $query->where('customer_id', $customerId))
            ->sum('balance'));
    }

    /**
     * Per-vehicle breakdown of what a customer owes (including vehicles they
     * have since sold: the old debt stays with them).
     *
     * @return array{total: float, vehicles: list<array{vehicle_id: int, plate_number: ?string, balance: float, orders_count: int, oldest_due_at: ?int}>}
     */
    public function customerDebts(int $customerId): array
    {
        $rows = VehicleBalance::query()
            ->with('vehicle:id,plate_number')
            ->where('customer_id', $customerId)
            ->orderBy('oldest_due_at')
            ->get();

        return [
            'total' => Money::sum($rows->pluck('balance'))->toFloat(),
            'vehicles' => $rows->map(fn (VehicleBalance $row): array => [
                'vehicle_id' => $row->vehicle_id,
                'plate_number' => $row->vehicle?->plate_number,
                'balance' => formatNumber($row->balance),
                'orders_count' => $row->orders_count,
                'oldest_due_at' => $row->oldest_due_at?->timestamp,
            ])->values()->all(),
        ];
    }
}
