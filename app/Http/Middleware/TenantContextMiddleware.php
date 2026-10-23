<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessRuleException;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the tenant of the authenticated center user. The tenant always comes
 * from the token's owner — never from headers or the request body.
 */
class TenantContextMiddleware
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->tokenCan('center')) {
            throw new AuthenticationException;
        }

        $tenant = $user->tenant;

        if (! $user->is_active || ! $tenant->is_active) {
            throw new BusinessRuleException(__('messages.account_inactive'), 403, errorCode: 'account_inactive');
        }

        $this->context->set($tenant);

        return $next($request);
    }
}
