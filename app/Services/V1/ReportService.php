<?php

namespace App\Services\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\EmployeeTransaction;
use App\Models\Expense;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\VehicleBalance;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Owner reports. Periods are local days of the center (converted to UTC
 * bounds); branch-level data follows the selected branch (none = all).
 * Sales exclude opening balances and cancelled orders; money movements come
 * from the payments ledger (payments − refunds), never double counted.
 */
class ReportService
{
    /**
     * @return array<string, mixed>
     */
    public function today(): array
    {
        $period = Period::today();
        $completed = $this->completedOrders($period);

        return [
            'date' => $period->localFrom(),
            'orders_count' => Order::query()
                ->where('is_opening_balance', false)
                ->where('status', '!=', OrderStatus::Cancelled)
                ->whereBetween('created_at', [$period->start, $period->end])
                ->count(),
            'open_orders_count' => Order::query()->where('status', OrderStatus::Open)->count(),
            'sales' => $this->sum((clone $completed), 'total'),
            'collected' => $this->netCollected($period)->toFloat(),
            'new_debts' => Money::of((string) (clone $completed)->selectRaw('COALESCE(SUM(total - paid), 0) AS debt')->value('debt'))->toFloat(),
            'expenses' => $this->sum($this->expenses($period), 'amount'),
            'total_debt' => Money::of((string) VehicleBalance::query()->sum('balance'))->toFloat(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function sales(array $filters): array
    {
        $period = Period::fromFilters($filters);
        $orders = $this->completedOrders($period);

        $totals = (clone $orders)->toBase()->selectRaw(
            'COUNT(*) AS orders_count, COALESCE(SUM(subtotal), 0) AS subtotal, COALESCE(SUM(discount), 0) AS discount, COALESCE(SUM(total), 0) AS total, COALESCE(SUM(paid), 0) AS paid'
        )->first();

        $daily = array_fill_keys($period->localDays(), ['orders_count' => 0, 'total' => Money::zero()]);

        foreach ((clone $orders)->toBase()->select(['completed_at', 'total'])->cursor() as $row) {
            $day = $period->localDate($row->completed_at);
            $daily[$day]['orders_count']++;
            $daily[$day]['total'] = $daily[$day]['total']->plus((string) $row->total);
        }

        return [
            'from' => $period->localFrom(),
            'to' => $period->localTo(),
            'orders_count' => (int) $totals->orders_count,
            'subtotal' => formatNumber((string) $totals->subtotal),
            'discount' => formatNumber((string) $totals->discount),
            'total' => formatNumber((string) $totals->total),
            'paid' => formatNumber((string) $totals->paid),
            'remaining' => Money::of((string) $totals->total)->minus((string) $totals->paid)->toFloat(),
            'collections_by_method' => $this->collectionsByMethod($period),
            'daily' => collect($daily)->map(fn (array $day, string $date): array => [
                'date' => $date,
                'orders_count' => $day['orders_count'],
                'total' => $day['total']->toFloat(),
            ])->values()->all(),
        ];
    }

    /**
     * Customers who owe money, with the debt split per vehicle.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function debts(array $filters): LengthAwarePaginator
    {
        $paginator = VehicleBalance::query()
            ->toBase()
            ->selectRaw('customer_id, SUM(balance) AS total, MIN(oldest_due_at) AS oldest_due_at, COUNT(*) AS vehicles_count')
            ->where('tenant_id', tenantContext()->id())
            ->groupBy('customer_id')
            ->when(($filters['sort'] ?? 'amount') === 'oldest',
                fn ($query) => $query->orderBy('oldest_due_at'),
                fn ($query) => $query->orderByDesc('total'))
            ->paginate($filters['per_page'] ?? config('paginate.med'));

        $customerIds = collect($paginator->items())->pluck('customer_id');
        $customers = Customer::query()->withTrashed()->whereIn('id', $customerIds)->get(['id', 'name', 'phone'])->keyBy('id');
        $vehicles = VehicleBalance::query()->with('vehicle:id,plate_number')->whereIn('customer_id', $customerIds)->get()->groupBy('customer_id');

        return $paginator->through(fn (object $row): array => [
            'customer' => ['id' => (int) $row->customer_id, 'name' => $customers[$row->customer_id]?->name, 'phone' => $customers[$row->customer_id]?->phone],
            'total' => formatNumber((string) $row->total),
            'vehicles_count' => (int) $row->vehicles_count,
            'oldest_due_at' => $row->oldest_due_at ? CarbonImmutable::parse($row->oldest_due_at)->timestamp : null,
            'vehicles' => ($vehicles[$row->customer_id] ?? collect())->map(fn (VehicleBalance $balance): array => [
                'vehicle_id' => $balance->vehicle_id,
                'plate_number' => $balance->vehicle?->plate_number,
                'balance' => formatNumber($balance->balance),
                'orders_count' => $balance->orders_count,
            ])->values()->all(),
        ]);
    }

    /**
     * Sales − cost of goods (cost snapshots) − expenses − payroll.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function profit(array $filters): array
    {
        $period = Period::fromFilters($filters);
        $orderIds = $this->completedOrders($period)->select('id');

        $sales = Money::of((string) $this->completedOrders($period)->sum('total'));

        $costs = OrderItem::query()->whereIn('order_id', $orderIds)->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN unit_cost IS NOT NULL THEN ROUND(qty * unit_cost, 2) ELSE 0 END), 0) AS cogs')
            ->selectRaw('SUM(CASE WHEN unit_cost IS NULL THEN 1 ELSE 0 END) AS items_without_cost')
            ->first();

        $cogs = Money::of((string) $costs->cogs);
        $expenses = Money::of((string) $this->expenses($period)->sum('amount'));

        $payroll = $this->employeeTransactions($period)->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type IN ('salary', 'bonus') THEN amount WHEN type = 'deduction' THEN -amount ELSE 0 END), 0) AS payroll")
            ->value('payroll');
        $payroll = Money::of((string) $payroll);

        $grossProfit = $sales->minus($cogs);

        return [
            'from' => $period->localFrom(),
            'to' => $period->localTo(),
            'sales' => $sales->toFloat(),
            'cost_of_goods' => $cogs->toFloat(),
            'gross_profit' => $grossProfit->toFloat(),
            'expenses' => $expenses->toFloat(),
            'payroll' => $payroll->toFloat(),
            'net_profit' => $grossProfit->minus($expenses)->minus($payroll)->toFloat(),
            'items_without_cost' => (int) $costs->items_without_cost,
        ];
    }

    /**
     * Drawer movement: cash collected − (cash refunds + cash expenses +
     * employee advances and payments).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function cashFlow(array $filters): array
    {
        $period = Period::fromFilters($filters);
        $cash = PaymentMethod::Cash->value;

        $payments = $this->payments($period)->where('method', $cash)->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) AS cash_in")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END), 0) AS refunds")
            ->first();

        $expenses = Money::of((string) $this->expenses($period)->where('method', $cash)->sum('amount'));

        $employeeOut = $this->employeeTransactions($period)->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'advance' THEN amount ELSE 0 END), 0) AS advances")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) AS salaries_paid")
            ->first();

        $cashIn = Money::of((string) $payments->cash_in);
        $out = Money::sum([(string) $payments->refunds, $expenses, (string) $employeeOut->advances, (string) $employeeOut->salaries_paid]);

        return [
            'from' => $period->localFrom(),
            'to' => $period->localTo(),
            'cash_in' => $cashIn->toFloat(),
            'cash_out' => [
                'refunds' => formatNumber((string) $payments->refunds),
                'expenses' => $expenses->toFloat(),
                'employee_advances' => formatNumber((string) $employeeOut->advances),
                'employee_payments' => formatNumber((string) $employeeOut->salaries_paid),
                'total' => $out->toFloat(),
            ],
            'net' => $cashIn->minus($out)->toFloat(),
            'collections_by_method' => $this->collectionsByMethod($period),
        ];
    }

    /**
     * Vehicles whose next service date (from their latest invoice) is due
     * within `days` or already passed.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function serviceDue(array $filters): LengthAwarePaginator
    {
        $timezone = tenantContext()->tenant()->timezone;
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $until = $today->addDays((int) ($filters['days'] ?? 7))->toDateString();

        $latestPerVehicle = Order::query()->withoutGlobalScope('branch')
            ->selectRaw('MAX(id)')
            ->where('status', OrderStatus::Completed)
            ->where('is_opening_balance', false)
            ->groupBy('vehicle_id');

        return Order::query()
            ->with(['vehicle' => fn ($query) => $query->with(['carMake', 'carModel']), 'customer:id,name,phone'])
            ->whereIn('id', $latestPerVehicle)
            ->whereNotNull('next_service_date')
            ->whereDate('next_service_date', '<=', $until)
            ->orderBy('next_service_date')
            ->paginate($filters['per_page'] ?? config('paginate.med'))
            ->through(function (Order $order) use ($today, $timezone): Order {
                $dueDate = CarbonImmutable::parse($order->next_service_date->toDateString(), $timezone);
                $order->setAttribute('days_left', (int) round($today->diffInDays($dueDate, false)));

                return $order;
            });
    }

    /**
     * @return Builder<Order>
     */
    private function completedOrders(Period $period): Builder
    {
        return Order::query()
            ->where('status', OrderStatus::Completed)
            ->where('is_opening_balance', false)
            ->whereBetween('completed_at', [$period->start, $period->end]);
    }

    /**
     * @return Builder<Payment>
     */
    private function payments(Period $period): Builder
    {
        return Payment::query()->notVoided()->whereBetween('paid_at', [$period->start, $period->end]);
    }

    /**
     * @return Builder<Expense>
     */
    private function expenses(Period $period): Builder
    {
        return Expense::query()->notVoided()->whereBetween('spent_at', [$period->localFrom(), $period->localTo()]);
    }

    /**
     * @return Builder<EmployeeTransaction>
     */
    private function employeeTransactions(Period $period): Builder
    {
        return EmployeeTransaction::query()->notVoided()->whereBetween('occurred_at', [$period->localFrom(), $period->localTo()]);
    }

    private function netCollected(Period $period): Money
    {
        return Money::of((string) $this->payments($period)->toBase()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END), 0) AS net")
            ->value('net'));
    }

    /**
     * @return list<array{method: array{label: string, value: string}, collected: float, refunded: float, net: float}>
     */
    private function collectionsByMethod(Period $period): array
    {
        $rows = $this->payments($period)->toBase()
            ->selectRaw("method, COALESCE(SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END), 0) AS collected")
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'refund' THEN amount ELSE 0 END), 0) AS refunded")
            ->groupBy('method')
            ->get()
            ->keyBy('method');

        return array_map(function (PaymentMethod $method) use ($rows): array {
            $row = $rows[$method->value] ?? null;
            $collected = Money::of((string) ($row->collected ?? '0'));
            $refunded = Money::of((string) ($row->refunded ?? '0'));

            return [
                'method' => $method->response(),
                'collected' => $collected->toFloat(),
                'refunded' => $refunded->toFloat(),
                'net' => $collected->minus($refunded)->toFloat(),
            ];
        }, PaymentMethod::cases());
    }

    private function sum(Builder $query, string $column): float
    {
        return Money::of((string) $query->sum($column))->toFloat();
    }
}
