<?php

namespace App\Services\V1;

use App\Exceptions\BusinessRuleException;
use App\Models\Customer;
use App\Support\PhoneNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CustomerService
{
    public function __construct(
        private readonly VehicleService $vehicleService,
        private readonly BalanceService $balanceService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Customer>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($filters['per_page'] ?? config('paginate.default'));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Customer>
     */
    public function query(array $filters): Builder
    {
        return Customer::query()
            ->withCount('vehicles')
            ->addSelect(['balance' => $this->balanceService->customerBalanceSubquery()])
            ->when($filters['q'] ?? null, fn (Builder $query, string $q) => $query->where(fn (Builder $query) => $query
                ->where('name', 'like', "%{$q}%")
                ->when(PhoneNormalizer::normalize($q), fn (Builder $query, string $phone) => $query->orWhere('phone', 'like', "%{$phone}%"))))
            ->when($filters['has_debt'] ?? null, fn (Builder $query) => $query->having('balance', '>', 0))
            ->latest('id');
    }

    /**
     * Create a customer, optionally with their first vehicles in one step.
     *
     * @param  array<string, mixed>  $data
     */
    public function store(array $data): Customer
    {
        return DB::transaction(function () use ($data): Customer {
            $customer = Customer::query()->create([
                ...Arr::only($data, ['name', 'phone', 'notes']),
                'created_by' => actor()?->id,
            ]);

            foreach ($data['vehicles'] ?? [] as $vehicle) {
                $this->vehicleService->store([...$vehicle, 'customer_id' => $customer->id]);
            }

            return $this->show($customer);
        });
    }

    public function show(Customer $customer): Customer
    {
        $customer->load(['vehicles' => fn ($query) => $query->with(['carMake', 'carModel'])->latest('id')]);
        $customer->setAttribute('debts', $this->balanceService->customerDebts($customer->id));

        return $customer;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Customer $customer, array $data): Customer
    {
        $customer->update(Arr::only($data, ['name', 'phone', 'notes']));

        return $this->show($customer);
    }

    /**
     * Soft delete (with the customer's vehicles); refused while any debt is open.
     */
    public function destroy(Customer $customer): void
    {
        if ($this->balanceService->customerBalance($customer->id)->isPositive()) {
            throw new BusinessRuleException(__('messages.customer_has_debt'), 422);
        }

        DB::transaction(function () use ($customer): void {
            $customer->vehicles()->each(fn ($vehicle) => $vehicle->delete());
            $customer->delete();
        });
    }
}
