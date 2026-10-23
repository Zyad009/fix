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
     * Tenancy building blocks shared by every tenant table:
     * `tenant_id` + UNIQUE(tenant_id, id) so children can reference
     * (tenant_id, x_id) and a row can never point at another tenant's row.
     */
    private function registerSchemaMacros(): void
    {
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
