<?php

namespace App\Services\V1\Admin;

use App\Exceptions\BusinessRuleException;
use App\Models\Branch;
use App\Models\MaintenanceCenter;
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
    public function list(MaintenanceCenter $maintenanceCenter): Collection
    {
        return maintenanceCenterContext()->run($maintenanceCenter, fn () => Branch::query()->orderBy('id')->get());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function store(MaintenanceCenter $maintenanceCenter, array $data): Branch
    {
        return maintenanceCenterContext()->run($maintenanceCenter, fn () => Branch::query()->create(Arr::only($data, ['name', 'phone', 'address'])));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(MaintenanceCenter $maintenanceCenter, int $branchId, array $data): Branch
    {
        return maintenanceCenterContext()->run($maintenanceCenter, function () use ($branchId, $data): Branch {
            $branch = Branch::query()->findOrFail($branchId);
            $branch->update(Arr::only($data, ['name', 'phone', 'address']));

            return $branch;
        });
    }

    public function toggle(MaintenanceCenter $maintenanceCenter, int $branchId): Branch
    {
        return maintenanceCenterContext()->run($maintenanceCenter, function () use ($branchId): Branch {
            $branch = Branch::query()->findOrFail($branchId);

            if ($branch->is_default && $branch->is_active) {
                throw new BusinessRuleException(__('messages.default_branch_locked'), 422);
            }

            $branch->update(['is_active' => ! $branch->is_active]);

            return $branch;
        });
    }
}
