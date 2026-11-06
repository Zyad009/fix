<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MacroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::macro('toggleActive', function (string $name, string $controller): void {
            Route::patch($name.'/toggle/{id}', [$controller, 'toggle'])->name($name.'.toggle');
        });

        $this->registerSchemaMacros();
    }

    /**
     * Isolation building blocks shared by every maintenance center table:
     * `maintenance_center_id` + UNIQUE(maintenance_center_id, id) so children can
     * reference (maintenance_center_id, x_id) and a row can never point at another
     * center's row. New tables use `maintenanceCenterKey` and
     * `maintenanceCenterForeign`.
     *
     * `tenantKey`, `tenantForeign`, `createdBy` and `voidColumns` are frozen: only
     * migrations that ran before the rename call them, and they must keep building
     * the old `tenant_id` schema for a fresh install to migrate from zero.
     */
    private function registerSchemaMacros(): void
    {
        Blueprint::macro('maintenanceCenterKey', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('maintenance_center_id')->constrained('maintenance_centers');
            $this->unique(['maintenance_center_id', 'id']);
        });

        Blueprint::macro('maintenanceCenterForeign', function (string $column, string $parentTable, bool $nullable = false, ?string $onDelete = null): void {
            /** @var Blueprint $this */
            $this->unsignedBigInteger($column)->nullable($nullable);

            $foreign = $this->foreign(['maintenance_center_id', $column])->references(['maintenance_center_id', 'id'])->on($parentTable);

            if ($onDelete) {
                $foreign->onDelete($onDelete);
            }
        });

        Blueprint::macro('tenantKey', function (): void {
            /** @var Blueprint $this */
            $this->foreignId('tenant_id')->constrained('tenants');
            $this->unique(['tenant_id', 'id']);
        });

        Blueprint::macro('tenantForeign', function (string $column, string $parentTable, bool $nullable = false, ?string $onDelete = null): void {
            /** @var Blueprint $this */
            $this->unsignedBigInteger($column)->nullable($nullable);

            $foreign = $this->foreign(['tenant_id', $column])->references(['tenant_id', 'id'])->on($parentTable);

            if ($onDelete) {
                $foreign->onDelete($onDelete);
            }
        });

        Blueprint::macro('createdBy', function (): void {
            /** @var Blueprint $this */
            $this->tenantForeign('created_by', 'users', nullable: true);
        });

        Blueprint::macro('voidColumns', function (): void {
            /** @var Blueprint $this */
            $this->timestamp('voided_at')->nullable();
            $this->tenantForeign('voided_by', 'users', nullable: true);
            $this->string('void_reason', 500)->nullable();
        });
    }
}
