<?php

namespace App\Services\V1\Admin;

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;

/**
 * Branches are part of the subscription, so only the platform admin
 * creates or changes them.
 */
class BranchService
{
    /**
     * @return Collection<int, Branch>
     */
    public function list(Tenant $tenant): Collection
    {
        return tenantContext()->run($tenant, fn () => Branch::query()->orderBy('id')->get());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(Tenant $tenant, array $data): Branch
    {
        return tenantContext()->run($tenant, fn () => Branch::query()->create(Arr::only($data, ['name', 'phone', 'address'])));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Tenant $tenant, int $branchId, array $data): Branch
    {
        return tenantContext()->run($tenant, function () use ($branchId, $data): Branch {
            $branch = Branch::query()->findOrFail($branchId);
            $branch->update(Arr::only($data, ['name', 'phone', 'address']));

            return $branch;
        });
    }

    public function toggle(Tenant $tenant, int $branchId): Branch
    {
        return tenantContext()->run($tenant, function () use ($branchId): Branch {
            $branch = Branch::query()->findOrFail($branchId);

            if ($branch->is_default && $branch->is_active) {
                throw new BusinessRuleException(__('messages.default_branch_locked'), 422);
            }

            $branch->update(['is_active' => ! $branch->is_active]);

            return $branch;
        });
    }
}
