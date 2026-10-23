<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\BranchContext;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request starts with no tenant/branch: nothing may leak from a previous
 * request handled by the same process (long-running workers, tests). The
 * `tenant` middleware then binds the authenticated user's tenant.
 */
class ResetTenancyContext
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly BranchContext $branchContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenantContext->set(null);
        $this->branchContext->set(null);

        return $next($request);
    }
}
