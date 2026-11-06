<?php

namespace App\Support\MaintenanceCenter;

use App\Exceptions\MissingMaintenanceCenterContextException;
use App\Models\MaintenanceCenter;
use Closure;

/**
 * The maintenance center the current request (or job/command) acts for.
 *
 * Filled by the `maintenance_center` middleware from the authenticated user — never from
 * request input. Maintenance-center-scoped models refuse to query without it.
 */
class MaintenanceCenterContext
{
    private ?MaintenanceCenter $maintenanceCenter = null;

    public function set(?MaintenanceCenter $maintenanceCenter): void
    {
        $this->maintenanceCenter = $maintenanceCenter;
    }

    public function check(): bool
    {
        return $this->maintenanceCenter !== null;
    }

    public function maintenanceCenter(): MaintenanceCenter
    {
        return $this->maintenanceCenter ?? throw new MissingMaintenanceCenterContextException;
    }

    public function id(): int
    {
        return $this->maintenanceCenter()->id;
    }

    public function idOrNull(): ?int
    {
        return $this->maintenanceCenter?->id;
    }

    /**
     * Run a callback acting for the given maintenance center, restoring the previous one.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(MaintenanceCenter $maintenanceCenter, Closure $callback): mixed
    {
        $previous = $this->maintenanceCenter;
        $this->maintenanceCenter = $maintenanceCenter;

        try {
            return $callback();
        } finally {
            $this->maintenanceCenter = $previous;
        }
    }
}
