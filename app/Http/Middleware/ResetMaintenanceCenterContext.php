<?php

namespace App\Http\Middleware;

use App\Support\MaintenanceCenter\BranchContext;
use App\Support\MaintenanceCenter\MaintenanceCenterContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request starts with no maintenance center/branch: nothing may leak from a previous
 * request handled by the same process (long-running workers, tests). The
 * `maintenance_center` middleware then binds the authenticated user's maintenance center.
 */
class ResetMaintenanceCenterContext
{
    public function __construct(
        private readonly MaintenanceCenterContext $maintenanceCenterContext,
        private readonly BranchContext $branchContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->maintenanceCenterContext->set(null);
        $this->branchContext->set(null);

        return $next($request);
    }
}
