<?php

namespace App\Services\V1\Admin;

use App\Models\Tenant;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class TenantService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Tenant>
     */
    public function list(array $filters): LengthAwarePaginator
    {
        return Tenant::query()
            ->withCount(['branches' => fn ($query) => $query->withoutGlobalScope('tenant')])
            ->withCount('users')
            ->when($filters['q'] ?? null, fn ($query, $q) => $query->where(fn ($query) => $query
                ->where('name', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")))
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', (bool) $filters['is_active']))
            ->when($filters['expired'] ?? null, fn ($query) => $query->where('subscription_ends_at', '<', now()))
            ->latest('id')
            ->paginate($filters['per_page'] ?? config('paginate.default'));
    }

    public function show(Tenant $tenant): Tenant
    {
        return $tenant->load(['branches' => fn ($query) => $query->withoutGlobalScope('tenant')])
            ->loadCount('users');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->update(Arr::only($data, ['name', 'phone', 'address', 'cashier_max_discount', 'timezone']));

        return $this->show($tenant);
    }

    /**
     * @param  array{plan?: ?string, subscription_ends_at?: ?string}  $data
     */
    public function updateSubscription(Tenant $tenant, array $data): Tenant
    {
        $tenant->update(Arr::only($data, ['plan', 'subscription_ends_at']));

        return $this->show($tenant);
    }

    /**
     * Suspending a center also signs every one of its users out.
     */
    public function toggle(int $tenantId): Tenant
    {
        $tenant = Tenant::query()->findOrFail($tenantId);
        $tenant->update(['is_active' => ! $tenant->is_active]);

        if (! $tenant->is_active) {
            tenantContext()->run($tenant, fn () => $tenant->users()->each(fn ($user) => $user->tokens()->delete()));
        }

        return $this->show($tenant);
    }
}
