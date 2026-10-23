<?php

namespace App\Services\V1;

use App\Enums\AuditEvent;
use App\Enums\OrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\Vehicle;
use App\Support\PlateNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class VehicleService
{
    public function __construct(
        private readonly BalanceService $balanceService,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Vehicle>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($filters['per_page'] ?? config('paginate.default'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Vehicle>
     */
    public function query(array $filters): Builder
    {
        return Vehicle::query()
            ->with(['customer:id,name,phone', 'carMake', 'carModel'])
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where('plate_normalized', 'like', '%'.PlateNormalizer::normalize($q).'%'))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, $customerId) => $query->where('customer_id', $customerId))
            ->when($filters['car_make_id'] ?? null, fn (Builder $query, $makeId) => $query->where('car_make_id', $makeId))
            ->latest('id');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Vehicle
    {
        $vehicle = Vehicle::query()->create([
            ...Arr::only($data, ['customer_id', 'plate_number', 'vin', 'car_make_id', 'car_model_id', 'year', 'color', 'notes']),
            'created_by' => actor()?->id,
        ]);

        return $vehicle->load(['customer:id,name,phone', 'carMake', 'carModel']);
    }

    /**
     * Vehicle page: owner, last reading and visit, next service, and debt.
     */
    public function show(Vehicle $vehicle): Vehicle
    {
        $vehicle->load(['customer:id,name,phone,deleted_at', 'carMake', 'carModel']);

        $lastOrder = Order::query()
            ->withoutGlobalScope('branch')
            ->where('vehicle_id', $vehicle->id)
            ->where('status', OrderStatus::Completed)
            ->where('is_opening_balance', false)
            ->latest('completed_at')
            ->latest('id')
            ->first(['id', 'next_service_odometer', 'next_service_date']);

        $vehicle->setAttribute('next_service_odometer', $lastOrder?->next_service_odometer);
        $vehicle->setAttribute('next_service_date', $lastOrder?->next_service_date?->toDateString());
        $vehicle->setAttribute('balance', $this->balanceService->vehicleBalance($vehicle->id, $vehicle->customer_id));

        return $vehicle;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Vehicle $vehicle, array $data): Vehicle
    {
        $vehicle->update(Arr::only($data, ['plate_number', 'vin', 'car_make_id', 'car_model_id', 'year', 'color', 'notes']));

        return $this->show($vehicle);
    }

    /**
     * Change the current owner. Past visits and their debts stay with whoever
     * was the customer at the time.
     */
    public function transferOwner(Vehicle $vehicle, int $customerId): Vehicle
    {
        if ($vehicle->customer_id === $customerId) {
            throw new BusinessRuleException(__('messages.same_owner'), 422);
        }

        $previousOwner = $vehicle->customer_id;
        $vehicle->update(['customer_id' => $customerId]);

        $this->audit->record($vehicle, AuditEvent::OwnerTransferred, ['customer_id' => $previousOwner], ['customer_id' => $customerId]);

        return $this->show($vehicle);
    }

    public function destroy(Vehicle $vehicle): void
    {
        if ($this->balanceService->vehicleBalance($vehicle->id)->isPositive()) {
            throw new BusinessRuleException(__('messages.vehicle_has_debt'), 422);
        }

        $vehicle->delete();
    }

    /**
     * Full service history of the vehicle (every center branch).
     *
     * @return LengthAwarePaginator<int, Order>
     */
    public function history(Vehicle $vehicle, array $filters): LengthAwarePaginator
    {
        return Order::query()
            ->withoutGlobalScope('branch')
            ->with(['branch:id,name', 'customer:id,name', 'technician:id,name'])
            ->withCount('items')
            ->where('vehicle_id', $vehicle->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->latest('id')
            ->paginate($filters['per_page'] ?? config('paginate.default'));
    }

    /**
     * Refresh the vehicle's cached last reading/visit from its completed orders.
     */
    public function refreshLastVisit(int $vehicleId): void
    {
        $orders = Order::query()
            ->withoutGlobalScope('branch')
            ->where('vehicle_id', $vehicleId)
            ->where('status', OrderStatus::Completed)
            ->where('is_opening_balance', false);

        $lastVisitAt = (clone $orders)->max('completed_at');
        $lastOdometer = (clone $orders)->whereNotNull('odometer')->latest('completed_at')->latest('id')->value('odometer');

        DB::table('vehicles')->where('id', $vehicleId)->update([
            'last_visit_at' => $lastVisitAt,
            'last_odometer' => $lastOdometer,
        ]);
    }
}
