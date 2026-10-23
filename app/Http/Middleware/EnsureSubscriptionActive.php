<?php

namespace App\Http\Middleware;

use App\Exceptions\BusinessRuleException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * An expired subscription leaves the center read-only: reads pass, every
 * write answers 402. Must run after `tenant`.
 */
class EnsureSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethodSafe() && tenantContext()->tenant()->isSubscriptionExpired()) {
            throw new BusinessRuleException(__('messages.subscription_expired'), 402, errorCode: 'subscription_expired');
        }

        return $next($request);
    }
}
