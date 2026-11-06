<?php

namespace App\Models\Concerns;

use App\Exceptions\MissingMaintenanceCenterContextException;
use App\Models\MaintenanceCenter;
use App\Support\MaintenanceCenter\MaintenanceCenterContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scopes every query to the current maintenance center and stamps `maintenance_center_id` on create.
 * Without a maintenance center in context, queries fail closed.
 *
 * @property int $maintenance_center_id
 */
trait BelongsToMaintenanceCenter
{
    public static function bootBelongsToMaintenanceCenter(): void
    {
        static::addGlobalScope('maintenance_center', function (Builder $query): void {
            $context = app(MaintenanceCenterContext::class);

            if ($context->check()) {
                $query->where($query->qualifyColumn('maintenance_center_id'), $context->id());

                return;
            }

            if (static::requiresMaintenanceCenterContext()) {
                throw new MissingMaintenanceCenterContextException;
            }
        });

        static::creating(function (self $model): void {
            $model->maintenance_center_id ??= app(MaintenanceCenterContext::class)->id();
        });
    }

    /**
     * Whether querying this model with no maintenance center in context is a bug.
     */
    protected static function requiresMaintenanceCenterContext(): bool
    {
        return true;
    }

    /**
     * @return BelongsTo<MaintenanceCenter, $this>
     */
    public function maintenanceCenter(): BelongsTo
    {
        return $this->belongsTo(MaintenanceCenter::class);
    }
}
