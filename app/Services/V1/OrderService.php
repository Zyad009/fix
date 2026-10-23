<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Vehicle;
use App\Support\Money;
use App\Support\Period;
use App\Support\PlateNormalizer;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * The POS flow: open an order for a vehicle, add lines, discount, complete
 * it into an invoice (stock out, vehicle visit recorded, optional payments)
 * or cancel it (stock back, money refunded).
 */
class OrderService
{
    /** @var list<string> */
    private const HEADER_FIELDS = ['odometer', 'technician_id', 'next_service_odometer', 'next_service_date', 'notes'];

    public function __construct(
        private readonly OrderGuard $guard,
        private readonly OrderItemService $items,
        private readonly OrderTotalsService $totals,
        private readonly PaymentService $payments,
        private readonly StockService $stock,
        private readonly VehicleService $vehicles,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Order>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($filters['per_page'] ?? config('paginate.default'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Order>
     */
    public function query(array $filters): Builder
    {
        $canSeeClosed = actor()?->hasPermission('orders.view_closed');

        return Order::query()
            ->with(['customer:id,name,phone', 'vehicle', 'technician:id,name', 'branch:id,name'])
            ->withCount('items')
            ->when(! $canSeeClosed, fn (Builder $query) => $query->where('status', OrderStatus::Open))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['vehicle_id'] ?? null, fn (Builder $query, $vehicleId) => $query->where('vehicle_id', $vehicleId))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['technician_id'] ?? null, fn (Builder $query, $technicianId) => $query->where('technician_id', $technicianId))
            ->when(! ($filters['include_opening_balance'] ?? false), fn (Builder $query) => $query->where('is_opening_balance', false))
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $query) => $query
                ->when(ctype_digit($q), fn (Builder $query) => $query->where('order_no', (int) $q))
                ->orWhereHas('vehicle', fn (Builder $query) => $query->where('plate_normalized', 'like', '%'.PlateNormalizer::normalize($q).'%'))))
            ->when(isset($filters['from']) || isset($filters['to']), function (Builder $query) use ($filters): void {
                $period = Period::fromFilters($filters);
                $query->whereBetween('created_at', [$period->start, $period->end]);
            })
            ->latest('id');
    }

    public function show(Order $order): Order
    {
        $this->guard->ensureVisible($order);

        return $this->load($order);
    }

    /**
     * Open an order for a vehicle; the customer is the vehicle's current owner.
     *
     * @param  array<string, mixed>  $data
     * @return array{order: Order, warnings: list<string>}
     */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $vehicle = Vehicle::query()->findOrFail($data['vehicle_id']);
            $branchId = branchContext()->requireId();

            $this->assertTechnician($data['technician_id'] ?? null, $branchId);

            $order = Order::query()->create([
                ...Arr::only($data, self::HEADER_FIELDS),
                'branch_id' => $branchId,
                'order_no' => Branch::takeNumber($branchId, 'next_order_no'),
                'customer_id' => $vehicle->customer_id,
                'vehicle_id' => $vehicle->id,
                'status' => OrderStatus::Open,
                'created_by' => actor()?->id,
            ]);

            $warnings = $this->odometerWarnings($vehicle, $data['odometer'] ?? null);

            $this->items->addLines($order, $data['items'] ?? []);

            if (isset($data['discount'])) {
                $this->assertDiscountAllowed($order, $data['discount']);
                $order->discount = $data['discount'];
            }

            $this->totals->recalculate($order);

            return ['order' => $this->load($order), 'warnings' => $warnings];
        });
    }

    /**
     * Header changes (odometer, technician, discount, next service, notes).
     *
     * @param  array<string, mixed>  $data
     * @return array{order: Order, warnings: list<string>}
     */
    public function update(Order $order, array $data): array
    {
        return DB::transaction(function () use ($order, $data): array {
            $order = $this->guard->lockEditable($order);
            $old = $order->only([...self::HEADER_FIELDS, 'discount']);

            if (array_key_exists('technician_id', $data)) {
                $this->assertTechnician($data['technician_id'], $order->branch_id);
            }

            $order->fill(Arr::only($data, self::HEADER_FIELDS));

            if (array_key_exists('discount', $data) && ! Money::of($data['discount'])->isEqualTo($order->discount)) {
                $this->assertDiscountAllowed($order, $data['discount']);
                $order->discount = $data['discount'] ?? 0;
            }

            $this->totals->recalculate($order);

            $warnings = $order->wasChanged('odometer') || array_key_exists('odometer', $data)
                ? $this->odometerWarnings($order->vehicle()->firstOrFail(), $order->odometer, $order->id)
                : [];

            if ($order->isCompleted()) {
                $this->audit->record($order, AuditEvent::Updated, $old, $order->only([...self::HEADER_FIELDS, 'discount']));
                $this->vehicles->refreshLastVisit($order->vehicle_id);
            }

            return ['order' => $this->load($order), 'warnings' => $warnings];
        });
    }

    /**
     * Turn the order into an invoice: stock leaves the branch, the vehicle's
     * last visit/reading is refreshed and optional payments are collected.
     *
     * @param  list<array{amount: string, method: string}>  $payments
     * @return array{order: Order, warnings: list<string>}
     */
    public function complete(Order $order, array $payments = []): array
    {
        return DB::transaction(function () use ($order, $payments): array {
            $order = $this->guard->lock($order);

            if (! $order->isOpen()) {
                throw new BusinessRuleException(__('messages.order_not_open'), 422);
            }

            $items = $order->items()->get(['product_id', 'qty']);

            if ($items->isEmpty()) {
                throw new BusinessRuleException(__('messages.order_has_no_items'), 422);
            }

            $order->forceFill([
                'status' => OrderStatus::Completed,
                'completed_at' => now(),
                'completed_by' => actor()?->id,
            ])->save();

            $warnings = $this->stock->apply($order->branch_id, $this->stockDeltas($items, out: true));
            $this->vehicles->refreshLastVisit($order->vehicle_id);

            foreach ($payments as $payment) {
                $this->payments->payOrder($order, (string) $payment['amount'], PaymentMethod::from($payment['method']));
            }

            return ['order' => $this->load($order->refresh()), 'warnings' => $warnings];
        });
    }

    /**
     * Cancel an order. Completed invoices: owner only, with a reason; stock
     * returns and any money collected is refunded.
     */
    public function cancel(Order $order, ?string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason): Order {
            $order = $this->guard->lock($order);

            if ($order->isCancelled()) {
                throw new BusinessRuleException(__('messages.order_cancelled_locked'), 422);
            }

            $wasCompleted = $order->isCompleted();

            if ($wasCompleted && ! actor()?->hasPermission('orders.cancel_completed')) {
                throw new BusinessRuleException(__('messages.order_completed_locked'), 403);
            }

            if ($wasCompleted && blank($reason)) {
                throw new BusinessRuleException(__('messages.cancel_reason_required'), 422);
            }

            if (Money::of($order->paid)->isPositive()) {
                $this->payments->refundOrder($order);
            }

            $order->forceFill([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => actor()?->id,
                'cancel_reason' => $reason,
            ])->save();

            if ($wasCompleted) {
                $this->stock->apply($order->branch_id, $this->stockDeltas($order->items()->get(['product_id', 'qty']), out: false));
                $this->vehicles->refreshLastVisit($order->vehicle_id);
            }

            $this->audit->record($order, AuditEvent::Cancelled, ['status' => $wasCompleted ? 'completed' : 'open'], ['status' => 'cancelled'], $reason);

            return $this->load($order);
        });
    }

    public function load(Order $order): Order
    {
        return $order->load([
            'branch:id,name',
            'customer:id,name,phone',
            'vehicle' => fn ($query) => $query->with(['carMake', 'carModel']),
            'technician:id,name',
            'creator:id,name',
            'items' => fn ($query) => $query->orderBy('id'),
            'payments' => fn ($query) => $query->with('receipt:id,receipt_no')->orderBy('id'),
        ]);
    }

    private function assertDiscountAllowed(Order $order, mixed $discount): void
    {
        $user = actor();
        $discount = Money::of($discount === null ? '0' : (string) $discount);

        if ($discount->isZero()) {
            return;
        }

        if (! $user?->hasPermission('orders.discount')) {
            throw new BusinessRuleException(__('messages.discount_forbidden'), 403);
        }

        $limit = tenantContext()->tenant()->cashier_max_discount;

        if (! $user->hasPermission('orders.discount_unlimited') && $discount->isGreaterThan($limit)) {
            throw new BusinessRuleException(__('messages.discount_exceeds_limit', ['limit' => (string) Money::of($limit)]), 422);
        }
    }

    private function assertTechnician(?int $technicianId, int $branchId): void
    {
        if ($technicianId === null) {
            return;
        }

        $exists = Employee::query()->withoutGlobalScope('branch')
            ->whereKey($technicianId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw new BusinessRuleException(__('messages.technician_invalid'), 422);
        }
    }

    /**
     * A reading lower than the last recorded one is allowed (meter replaced or
     * broken) but surfaced as a warning.
     *
     * @return list<string>
     */
    private function odometerWarnings(Vehicle $vehicle, ?int $reading, ?int $ignoreOrderId = null): array
    {
        if ($reading === null) {
            return [];
        }

        $last = Order::query()->withoutGlobalScope('branch')
            ->where('vehicle_id', $vehicle->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->when($ignoreOrderId, fn (Builder $query) => $query->whereKeyNot($ignoreOrderId))
            ->max('odometer');

        $last = max((int) $last, (int) $vehicle->last_odometer);

        return $reading < $last
            ? [__('messages.odometer_lower_than_last', ['reading' => $reading, 'last' => $last])]
            : [];
    }

    /**
     * @param  iterable<OrderItem>  $items
     * @return array<int, BigDecimal>
     */
    private function stockDeltas(iterable $items, bool $out): array
    {
        $deltas = [];

        foreach ($items as $item) {
            if ($item->product_id === null) {
                continue;
            }

            $qty = BigDecimal::of($item->qty);
            $deltas[$item->product_id] = ($deltas[$item->product_id] ?? BigDecimal::zero())->plus($out ? $qty->negated() : $qty);
        }

        return $deltas;
    }
}
