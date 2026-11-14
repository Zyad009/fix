<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Brings every center's default roles up to the permissions they ship with
 * in config/rbac.php (e.g. `orders.edit_completed` for technician_cashier).
 * Additive and idempotent: permissions the owner granted stay, custom roles
 * are untouched and a default role the owner deleted is not recreated.
 */
class DefaultRolePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $updated = 0;

        foreach (config('rbac.defaults') as $key => $default) {
            if ($default['is_owner']) {
                continue;
            }

            Role::query()
                ->withoutGlobalScope('maintenance_center')
                ->where('key', $key)
                ->each(function (Role $role) use ($default, &$updated): void {
                    $missing = array_values(array_diff($default['permissions'], $role->permissions));

                    if ($missing !== []) {
                        $role->update(['permissions' => [...$role->permissions, ...$missing]]);
                        $updated++;
                    }
                });
        }

        $this->command?->info("Default roles updated: {$updated}");
    }
}
