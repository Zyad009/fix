<?php

namespace App\Support\Tenancy;

use App\Exceptions\MissingTenantContextException;
use App\Models\Tenant;
use Closure;

/**
 * The tenant the current request (or job/command) acts for.
 *
 * Filled by the `tenant` middleware from the authenticated user — never from
 * request input. Tenant-scoped models refuse to query without it.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function check(): bool
    {
        return $this->tenant !== null;
    }

    public function tenant(): Tenant
    {
        return $this->tenant ?? throw new MissingTenantContextException;
    }

    public function id(): int
    {
        return $this->tenant()->id;
    }

    public function idOrNull(): ?int
    {
        return $this->tenant?->id;
    }

    /**
     * Run a callback acting for the given tenant, restoring the previous one.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    public function run(Tenant $tenant, Closure $callback): mixed
    {
        $previous = $this->tenant;
        $this->tenant = $tenant;

        try {
            return $callback();
        } finally {
            $this->tenant = $previous;
        }
    }
}
