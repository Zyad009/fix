<?php

namespace App\Services\V1\Admin;

use App\Models\Branch;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\MaintenanceCenter;
use App\Models\RoutineService;
use App\Models\User;
use App\Services\V1\RoleService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates a service center with everything it needs on day one: the owner
 * account, a default branch, the default roles, catalog/expense categories
 * and routine services.
 */
class MaintenanceCenterProvisioningService
{
    public function __construct(private readonly RoleService $roles) {}

    /**
     * @param  array{name: string, phone?: ?string, address?: ?string, plan?: ?string, subscription_ends_at?: ?string, cashier_max_discount?: numeric-string|int|null, owner: array{name: string, username?: ?string, email: string, password: string}, branch_name?: ?string}  $data
     */
    public function provision(array $data): MaintenanceCenter
    {
        return DB::transaction(function () use ($data): MaintenanceCenter {
            $maintenanceCenter = MaintenanceCenter::query()->create(Arr::only($data, [
                'name', 'phone', 'address', 'plan', 'subscription_ends_at', 'cashier_max_discount',
            ]));

            maintenanceCenterContext()->run($maintenanceCenter, function () use ($maintenanceCenter, $data): void {
                Branch::query()->create([
                    'name' => $data['branch_name'] ?? __('messages.default_branch_name'),
                    'phone' => $maintenanceCenter->phone,
                    'address' => $maintenanceCenter->address,
                    'is_default' => true,
                ]);

                User::query()->create([
                    ...Arr::only($data['owner'], ['name', 'username', 'email', 'password']),
                    'role_id' => $this->roles->seedDefaults()->id,
                ]);

                $this->seedDefaults();
            });

            return $maintenanceCenter;
        });
    }

    private function seedDefaults(): void
    {
        foreach (Category::DEFAULTS as $index => $name) {
            Category::query()->create(['name' => $name, 'sort_order' => $index + 1]);
        }

        foreach (ExpenseCategory::DEFAULTS as $name) {
            ExpenseCategory::query()->create(['name' => $name]);
        }

        foreach (RoutineService::DEFAULTS as $name) {
            RoutineService::query()->create(['name' => $name]);
        }
    }
}
