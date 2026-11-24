<?php

namespace Database\Seeders;

use App\Models\MaintenanceCenter;
use App\Models\RoutineService;
use Illuminate\Database\Seeder;

/**
 * Gives every existing center the default routine services
 * (RoutineService::DEFAULTS). Additive and idempotent: a default the center
 * deleted (soft deleted) is not recreated, and renamed ones are left alone.
 */
class DefaultRoutineServicesSeeder extends Seeder
{
    public function run(): void
    {
        $created = 0;

        MaintenanceCenter::query()->each(function (MaintenanceCenter $maintenanceCenter) use (&$created): void {
            maintenanceCenterContext()->run($maintenanceCenter, function () use (&$created): void {
                $existing = RoutineService::query()->withTrashed()->pluck('name')->all();

                foreach (array_diff(RoutineService::DEFAULTS, $existing) as $name) {
                    RoutineService::query()->create(['name' => $name]);
                    $created++;
                }
            });
        });

        $this->command?->info("Routine services created: {$created}");
    }
}
