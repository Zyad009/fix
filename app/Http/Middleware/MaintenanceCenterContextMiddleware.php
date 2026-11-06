<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Support\MaintenanceCenter\MaintenanceCenterContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the maintenance center of the authenticated center user. The maintenance center always comes
 * from the token's owner — never from headers or the request body.
 */
class MaintenanceCenterContextMiddleware
{
    public function __construct(private readonly MaintenanceCenterContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->tokenCan('center')) {
            throw new AuthenticationException;
        }

        $maintenanceCenter = $user->maintenanceCenter;

        if (! $user->is_active || ! $maintenanceCenter->is_active) {
            throw new BusinessRuleException(__('messages.account_inactive'), 403, errorCode: 'account_inactive');
        }

        $this->context->set($maintenanceCenter);

        return $next($request);
    }
}
