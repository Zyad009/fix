<?php

namespace App\Services\V1\Admin;

use App\Enums\UserRole;
use App\Models\Branch;
use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates a service center with everything it needs on day one: the owner
 * account, a default branch and the default catalog/expense categories.
 */
class TenantProvisioningService
{
    /**
     * @param  array{name: string, phone?: ?string, address?: ?string, plan?: ?string, subscription_ends_at?: ?string, cashier_max_discount?: numeric-string|int|null, owner: array{name: string, email: string, password: string}, branch_name?: ?string}  $data
     */
    public function provision(array $data): Tenant
    {
        return DB::transaction(function () use ($data): Tenant {
            $tenant = Tenant::query()->create(Arr::only($data, [
                'name', 'phone', 'address', 'plan', 'subscription_ends_at', 'cashier_max_discount',
            ]));

            tenantContext()->run($tenant, function () use ($tenant, $data): void {
                Branch::query()->create([
                    'name' => $data['branch_name'] ?? __('messages.default_branch_name'),
                    'phone' => $tenant->phone,
                    'address' => $tenant->address,
                    'is_default' => true,
                ]);

                User::query()->create([
                    ...Arr::only($data['owner'], ['name', 'email', 'password']),
                    'role' => UserRole::Owner,
                ]);

                $this->seedDefaults();
            });

            return $tenant;
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
    }
}
