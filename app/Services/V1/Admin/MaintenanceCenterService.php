<?php

namespace App\Services\V1\Admin;

use App\Models\MaintenanceCenter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class MaintenanceCenterService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, MaintenanceCenter>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return MaintenanceCenter::query()
            ->withCount(['branches' => fn ($query) => $query->withoutGlobalScope('maintenance_center')])
            ->withCount('users')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($filters['expired'] ?? null, fn ($query) => $query->where('subscription_ends_at', '<', now()))
            ->latest('id')
            ->paginate($filters['per_page'] ?? config('paginate.default'));
    }

    public function show(MaintenanceCenter $maintenanceCenter): MaintenanceCenter
    {
        return $maintenanceCenter->load(['branches' => fn ($query) => $query->withoutGlobalScope('maintenance_center')])
            ->loadCount('users');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MaintenanceCenter $maintenanceCenter, array $data): MaintenanceCenter
    {
        $maintenanceCenter->update(Arr::only($data, ['name', 'phone', 'address', 'cashier_max_discount', 'timezone', 'whatsapp_device_id']));

        return $this->show($maintenanceCenter);
    }

    /**
     * @param  array{plan?: ?string, subscription_ends_at?: ?string}  $data
     */
    public function updateSubscription(MaintenanceCenter $maintenanceCenter, array $data): MaintenanceCenter
    {
        $maintenanceCenter->update(Arr::only($data, ['plan', 'subscription_ends_at']));

        return $this->show($maintenanceCenter);
    }

    /**
     * Suspending a center also signs every one of its users out.
     */
    public function toggle(int $maintenanceCenterId): MaintenanceCenter
    {
        $maintenanceCenter = MaintenanceCenter::query()->findOrFail($maintenanceCenterId);
        $maintenanceCenter->update(['is_active' => ! $maintenanceCenter->is_active]);

        if (! $maintenanceCenter->is_active) {
            maintenanceCenterContext()->run($maintenanceCenter, fn () => $maintenanceCenter->users()->each(fn ($user) => $user->tokens()->delete()));
        }

        return $this->show($maintenanceCenter);
    }
}
