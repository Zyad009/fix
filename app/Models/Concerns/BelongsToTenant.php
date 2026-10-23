<?php

namespace App\Models\Concerns;

use App\Exceptions\MissingTenantContextException;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes every query to the current tenant and stamps `tenant_id` on create.
 * Without a tenant in context, queries fail closed.
 *
 * @property int $tenant_id
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query): void {
            $context = app(TenantContext::class);

            if ($context->check()) {
                $query->where($query->qualifyColumn('tenant_id'), $context->id());

                return;
            }

            if (static::requiresTenantContext()) {
                throw new MissingTenantContextException;
            }
        });

        static::creating(function (self $model): void {
            $model->tenant_id ??= app(TenantContext::class)->id();
        });
    }

    /**
     * Whether querying this model with no tenant in context is a bug.
     */
    protected static function requiresTenantContext(): bool
    {
        return true;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
