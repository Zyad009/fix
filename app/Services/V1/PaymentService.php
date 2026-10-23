<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentType;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentReceipt;
use App\Support\Money;
use App\Support\Period;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Money in and out of orders. Payments belong to orders (so debt stays per
 * vehicle); each collection is one receipt; nothing is edited — mistakes are
 * voided and cancellations refunded.
 */
class PaymentService
{
    public function __construct(
        private readonly OrderGuard $guard,
        private readonly OrderTotalsService $totals,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Collect money against one order (a deposit on an open order, or a
     * full/partial payment of an invoice). Never more than what is due.
     */
    public function payOrder(Order $order, string $amount, PaymentMethod $method, ?string $notes = null): PaymentReceipt
    {
        return DB::transaction(function () use ($order, $amount, $method, $notes): PaymentReceipt {
            $order = $this->guard->lock($order);

            if ($order->isCancelled()) {
                throw new BusinessRuleException(__('messages.order_cancelled_locked'), 422);
            }

            $amount = Money::of($amount);

            if ($amount->isGreaterThan($order->remaining())) {
                throw new BusinessRuleException(__('messages.amount_exceeds_due', ['due' => (string) $order->remaining()]), 422);
            }

            $receipt = $this->createReceipt($order->customer_id, $order->vehicle_id, $amount, $method, $notes, $order->branch_id);
            $this->createPayment($receipt, $order, $amount);
            $this->totals->recalculate($order);

            return $this->loadReceipt($receipt);
        });
    }

    /**
     * Collect a lump sum from a customer (optionally for one vehicle) and
     * spread it over their unpaid invoices, oldest first.
     */
    public function collectDebt(Customer $customer, ?int $vehicleId, string $amount, PaymentMethod $method, ?string $notes = null): PaymentReceipt
    {
        return DB::transaction(function () use ($customer, $vehicleId, $amount, $method, $notes): PaymentReceipt {
            $orders = Order::query()->withoutGlobalScope('branch')
                ->where('customer_id', $customer->id)
                ->when($vehicleId, fn (Builder $query) => $query->where('vehicle_id', $vehicleId))
                ->where('status', OrderStatus::Completed)
                ->whereColumn('total', '>', 'paid')
                ->orderBy('completed_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $debt = Money::sum($orders->map(fn (Order $order): Money => $order->remaining()));
            $amount = Money::of($amount);

            if ($debt->isZero()) {
                throw new BusinessRuleException(__('messages.no_debt'), 422);
            }

            if ($amount->isGreaterThan($debt)) {
                throw new BusinessRuleException(__('messages.amount_exceeds_debt', ['debt' => (string) $debt]), 422);
            }

            $receipt = $this->createReceipt($customer->id, $vehicleId, $amount, $method, $notes);
            $left = $amount;

            foreach ($orders as $order) {
                if ($left->isZero()) {
                    break;
                }

                $portion = $left->min($order->remaining());
                $this->createPayment($receipt, $order, $portion);
                $this->totals->recalculate($order);
                $left = $left->minus($portion);
            }

            return $this->loadReceipt($receipt);
        });
    }

    /**
     * Give back everything collected on an order being cancelled: one refund
     * row per payment method, dated now (cash out of today's drawer).
     * Runs inside the caller's transaction with the order locked.
     */
    public function refundOrder(Order $order): void
    {
        $netByMethod = Payment::query()->withoutGlobalScope('branch')
            ->where('order_id', $order->id)
            ->notVoided()
            ->selectRaw("method, SUM(CASE WHEN type = 'payment' THEN amount ELSE -amount END) AS net")
            ->groupBy('method')
            ->pluck('net', 'method');

        foreach ($netByMethod as $method => $net) {
            if (! Money::of((string) $net)->isPositive()) {
                continue;
            }

            Payment::query()->create([
                'branch_id' => branchContext()->id() ?? $order->branch_id,
                'order_id' => $order->id,
                'type' => PaymentType::Refund,
                'amount' => (string) $net,
                'method' => $method,
                'paid_at' => now(),
                'received_by' => actor()?->id,
            ]);
        }

        $this->totals->recalculate($order);
    }

    /**
     * Void a whole receipt (owner, with a reason): its payments stop counting
     * and the orders owe that money again.
     */
    public function voidReceipt(PaymentReceipt $receipt, string $reason): PaymentReceipt
    {
        return DB::transaction(function () use ($receipt, $reason): PaymentReceipt {
            $receipt = PaymentReceipt::query()->withoutGlobalScope('branch')->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            $payments = $receipt->payments()->notVoided()->get();

            $orders = Order::query()->withoutGlobalScope('branch')
                ->whereIn('id', $payments->pluck('order_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($orders->contains(fn (Order $order): bool => $order->isCancelled())) {
                throw new BusinessRuleException(__('messages.receipt_has_cancelled_order'), 422);
            }

            $receipt->markVoided($reason);
            $payments->each(fn (Payment $payment) => $payment->markVoided($reason));
            $orders->each(fn (Order $order) => $this->totals->recalculate($order));

            $this->audit->record($receipt, AuditEvent::Voided, ['amount' => $receipt->amount], reason: $reason);

            return $this->loadReceipt($receipt);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, PaymentReceipt>
     */
    public function listReceipts(array $filters): LengthAwarePaginator
    {
        return PaymentReceipt::query()
            ->with(['customer:id,name,phone', 'vehicle:id,plate_number', 'receiver:id,name'])
            ->when($filters['q'] ?? null, fn (Builder $query, $q) => $query->where('receipt_no', (int) $q))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['method'] ?? null, fn (Builder $query, $method) => $query->where('method', $method))
            ->when(isset($filters['voided']), fn (Builder $query) => $filters['voided'] ? $query->whereNotNull('voided_at') : $query->notVoided())
            ->when(isset($filters['from']) || isset($filters['to']), function (Builder $query) use ($filters): void {
                $period = Period::fromFilters($filters);
                $query->whereBetween('received_at', [$period->start, $period->end]);
            })
            ->latest('id')
            ->paginate($filters['per_page'] ?? config('paginate.default'));
    }

    public function loadReceipt(PaymentReceipt $receipt): PaymentReceipt
    {
        return $receipt->load([
            'customer:id,name,phone',
            'vehicle:id,plate_number',
            'receiver:id,name',
            'payments.order' => fn ($query) => $query->select(['id', 'order_no', 'branch_id', 'vehicle_id', 'total', 'paid', 'status']),
        ]);
    }

    /**
     * The receipt belongs to the branch whose drawer takes the money: the
     * selected branch, else the order's own branch (when paying one order).
     */
    private function createReceipt(int $customerId, ?int $vehicleId, Money $amount, PaymentMethod $method, ?string $notes, ?int $fallbackBranchId = null): PaymentReceipt
    {
        $branchId = branchContext()->id() ?? $fallbackBranchId ?? branchContext()->requireId();

        return PaymentReceipt::query()->create([
            'branch_id' => $branchId,
            'receipt_no' => Branch::takeNumber($branchId, 'next_receipt_no'),
            'customer_id' => $customerId,
            'vehicle_id' => $vehicleId,
            'amount' => (string) $amount,
            'method' => $method,
            'received_by' => actor()?->id,
            'received_at' => now(),
            'notes' => $notes,
        ]);
    }

    private function createPayment(PaymentReceipt $receipt, Order $order, Money $amount): Payment
    {
        return Payment::query()->create([
            'branch_id' => $receipt->branch_id,
            'receipt_id' => $receipt->id,
            'order_id' => $order->id,
            'type' => PaymentType::Payment,
            'amount' => (string) $amount,
            'method' => $receipt->method,
            'paid_at' => $receipt->received_at,
            'received_by' => $receipt->received_by,
        ]);
    }
}
