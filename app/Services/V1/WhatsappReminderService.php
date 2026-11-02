<?php

namespace App\Services\V1;

use App\Enums\OrderStatus;
use App\Models\Customer;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Picks who gets a WhatsApp reminder today, for the current tenant.
 *
 * Service: the day before the next service date of a vehicle's latest invoice.
 * Debt: when the customer's newest unpaid invoice, last payment and last debt
 * reminder are all at least `debt_reminder_days` old.
 */
class WhatsappReminderService
{
    public function __construct(private readonly WhatsappNotifier $notifier) {}

    /**
     * @return array{service: int, debt: int}
     */
    public function run(): array
    {
        $tenant = tenantContext()->tenant();
        $sent = ['service' => 0, 'debt' => 0];

        if ($tenant->whatsapp_service_reminder_enabled) {
            foreach ($this->dueServiceOrders() as $order) {
                $this->notifier->serviceReminder($order, $order->vehicle->customer_id);
                Order::query()->withoutGlobalScope('branch')->whereKey($order->id)->toBase()->update(['service_reminder_sent_at' => now()]);
                $sent['service']++;
            }
        }

        if ($tenant->whatsapp_debt_reminder_enabled) {
            foreach ($this->dueDebtors($tenant->debt_reminder_days) as $customer) {
                $this->notifier->debtReminder($customer);
                Customer::query()->whereKey($customer->id)->toBase()->update(['debt_reminded_at' => now()]);
                $sent['debt']++;
            }
        }

        return $sent;
    }

    /**
     * @return Collection<int, Order>
     */
    public function dueServiceOrders(): Collection
    {
        $tomorrow = CarbonImmutable::now(tenantContext()->tenant()->timezone)->addDay()->toDateString();

        $latestPerVehicle = Order::query()->withoutGlobalScope('branch')
            ->selectRaw('MAX(id)')
            ->where('status', OrderStatus::Completed)
            ->where('is_opening_balance', false)
            ->groupBy('vehicle_id');

        return Order::query()->withoutGlobalScope('branch')
            ->with('vehicle:id,customer_id')
            ->whereIn('id', $latestPerVehicle)
            ->where('next_service_date', $tomorrow)
            ->whereNull('service_reminder_sent_at')
            ->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->whereNull('deleted_at')
                ->whereHas('customer', fn (Builder $customer) => $customer->whereNull('deleted_at')))
            ->get(['id', 'vehicle_id', 'next_service_date']);
    }

    /**
     * Customers who still owe money and have heard nothing from the center
     * (invoice, payment or reminder) for `$days` days.
     *
     * @return Collection<int, Customer>
     */
    public function dueDebtors(int $days): Collection
    {
        $cutoff = CarbonImmutable::now(tenantContext()->tenant()->timezone)->startOfDay()->subDays($days - 1)->utc();

        $owing = DB::table('vehicle_balances')
            ->select('customer_id')
            ->where('tenant_id', tenantContext()->id())
            ->groupBy('customer_id')
            ->havingRaw('SUM(balance) > 0');

        return Customer::query()
            ->whereIn('id', $owing)
            ->whereRaw(<<<'SQL'
                GREATEST(
                    COALESCE((SELECT MAX(o.completed_at) FROM orders o
                        WHERE o.tenant_id = customers.tenant_id AND o.customer_id = customers.id
                          AND o.status = ? AND o.total > o.paid), '1970-01-01'),
                    COALESCE((SELECT MAX(r.received_at) FROM payment_receipts r
                        WHERE r.tenant_id = customers.tenant_id AND r.customer_id = customers.id
                          AND r.voided_at IS NULL), '1970-01-01'),
                    COALESCE(customers.debt_reminded_at, '1970-01-01')
                ) < ?
                SQL, [OrderStatus::Completed->value, $cutoff->toDateTimeString()])
            ->get(['id', 'tenant_id', 'phone']);
    }
}
