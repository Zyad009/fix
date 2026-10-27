<?php

namespace App\Services\V1;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Support\Money;
use App\Support\Period;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The customer's account statement, like a bank statement: invoices are
 * debits, collections are credits, with a running balance. It covers every
 * branch and ends on exactly the debt computed from `vehicle_balances`:
 * completed invoices only, cancelled orders (and their refunds) left out,
 * deposits on still-open orders reported apart.
 */
class CustomerStatementService
{
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_OPENING_BALANCE = 'opening_balance';

    public const TYPE_PAYMENT = 'payment';

    public function __construct(private readonly BalanceService $balances) {}

    /**
     * @param  array<string, mixed>  $filters  from, to (local Y-m-d), vehicle_id
     * @return array<string, mixed>
     */
    public function statement(Customer $customer, array $filters): array
    {
        $vehicleId = isset($filters['vehicle_id']) ? (int) $filters['vehicle_id'] : null;
        [$start, $end] = $this->bounds($filters);

        $all = $this->movements($customer->id, $vehicleId);
        $opening = Money::zero();
        $balance = Money::zero();
        $entries = [];

        foreach ($all as $movement) {
            if ($end && $movement['at']->greaterThan($end)) {
                break;
            }

            $balance = $balance->plus($movement['debit'])->minus($movement['credit']);

            if ($start && $movement['at']->lessThan($start)) {
                $opening = $balance;

                continue;
            }

            $entries[] = $this->present($movement, $balance);
        }

        return [
            'customer' => ['id' => $customer->id, 'name' => $customer->name, 'phone' => $customer->phone],
            'summary' => $this->summary($customer->id, $vehicleId, $all),
            'period' => [
                'from' => $filters['from'] ?? null,
                'to' => $filters['to'] ?? null,
            ],
            'opening_balance' => $opening->toFloat(),
            'entries' => $entries,
            'closing_balance' => $balance->toFloat(),
        ];
    }

    /**
     * Every debit and credit of the customer, oldest first (an invoice before
     * a collection recorded at the same moment).
     *
     * @return Collection<int, array{type: string, at: CarbonImmutable, debit: Money, credit: Money, order_id: ?int, receipt_id: ?int, reference: ?int, vehicle_plate: ?string, method: ?string, branch: ?string}>
     */
    private function movements(int $customerId, ?int $vehicleId): Collection
    {
        $invoices = $this->completedOrders($customerId, $vehicleId)
            ->with(['vehicle:id,plate_number', 'branch:id,name'])
            ->get(['id', 'order_no', 'branch_id', 'vehicle_id', 'total', 'completed_at', 'is_opening_balance'])
            ->map(fn (Order $order): array => [
                'type' => $order->is_opening_balance ? self::TYPE_OPENING_BALANCE : self::TYPE_INVOICE,
                'at' => CarbonImmutable::instance($order->completed_at),
                'debit' => Money::of($order->total),
                'credit' => Money::zero(),
                'order_id' => $order->id,
                'receipt_id' => null,
                'reference' => $order->order_no,
                'vehicle_plate' => $order->vehicle?->plate_number,
                'method' => null,
                'branch' => $order->branch?->name,
            ]);

        $collected = Payment::query()->withoutGlobalScope('branch')
            ->notVoided()
            ->where('type', PaymentType::Payment)
            ->whereHas('order', fn (Builder $query) => $query->withoutGlobalScope('branch')
                ->where('customer_id', $customerId)
                ->where('status', OrderStatus::Completed)
                ->when($vehicleId, fn (Builder $query) => $query->where('vehicle_id', $vehicleId)))
            ->selectRaw('receipt_id, MIN(paid_at) AS paid_at, SUM(amount) AS amount')
            ->groupBy('receipt_id')
            ->toBase()
            ->get();

        $receipts = PaymentReceipt::query()->withoutGlobalScope('branch')
            ->with(['vehicle:id,plate_number', 'branch:id,name'])
            ->whereIn('id', $collected->pluck('receipt_id'))
            ->get(['id', 'receipt_no', 'branch_id', 'vehicle_id', 'method'])
            ->keyBy('id');

        $payments = $collected->map(function (object $row) use ($receipts): array {
            $receipt = $receipts->get($row->receipt_id);

            return [
                'type' => self::TYPE_PAYMENT,
                'at' => CarbonImmutable::parse($row->paid_at, 'UTC'),
                'debit' => Money::zero(),
                'credit' => Money::of((string) $row->amount),
                'order_id' => null,
                'receipt_id' => $row->receipt_id,
                'reference' => $receipt?->receipt_no,
                'vehicle_plate' => $receipt?->vehicle?->plate_number,
                'method' => $receipt?->method->value,
                'branch' => $receipt?->branch?->name,
            ];
        });

        return $invoices->toBase()->merge($payments)
            ->sortBy(fn (array $movement): array => [
                $movement['at']->getTimestamp(),
                $movement['type'] === self::TYPE_PAYMENT ? 1 : 0,
                $movement['order_id'] ?? $movement['receipt_id'],
            ])
            ->values();
    }

    /**
     * Whole-history totals (independent of the period window).
     *
     * @param  Collection<int, array<string, mixed>>  $movements
     * @return array<string, mixed>
     */
    private function summary(int $customerId, ?int $vehicleId, Collection $movements): array
    {
        $invoiced = Money::sum($movements->pluck('debit'));
        $paid = Money::sum($movements->pluck('credit'));

        $deposits = Order::query()->withoutGlobalScope('branch')
            ->where('customer_id', $customerId)
            ->where('status', OrderStatus::Open)
            ->when($vehicleId, fn (Builder $query) => $query->where('vehicle_id', $vehicleId))
            ->sum('paid');

        return [
            'total_invoiced' => $invoiced->toFloat(),
            'total_paid' => $paid->toFloat(),
            'balance' => $invoiced->minus($paid)->toFloat(),
            'open_orders_deposits' => Money::of((string) $deposits)->toFloat(),
            'vehicles' => $this->balances->customerDebts($customerId)['vehicles'],
        ];
    }

    /**
     * @param  array<string, mixed>  $movement
     * @return array<string, mixed>
     */
    private function present(array $movement, Money $balance): array
    {
        return [
            'type' => ['value' => $movement['type'], 'label' => __('messages.statement_'.$movement['type'])],
            'date' => $movement['at']->getTimestamp(),
            'reference' => $movement['reference'],
            'order_id' => $movement['order_id'],
            'receipt_id' => $movement['receipt_id'],
            'vehicle_plate' => $movement['vehicle_plate'],
            'branch' => $movement['branch'],
            'method' => $movement['method'] ? PaymentMethod::from($movement['method'])->response() : null,
            'debit' => $movement['debit']->toFloat(),
            'credit' => $movement['credit']->toFloat(),
            'balance' => $balance->toFloat(),
        ];
    }

    /**
     * @return Builder<Order>
     */
    private function completedOrders(int $customerId, ?int $vehicleId): Builder
    {
        return Order::query()->withoutGlobalScope('branch')
            ->where('customer_id', $customerId)
            ->where('status', OrderStatus::Completed)
            ->when($vehicleId, fn (Builder $query) => $query->where('vehicle_id', $vehicleId));
    }

    /**
     * Local-day bounds of the window; either side may be open.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function bounds(array $filters): array
    {
        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? null;

        return [
            $from ? Period::fromFilters(['from' => $from])->start : null,
            $to ? Period::fromFilters(['from' => $to])->end : null,
        ];
    }
}
