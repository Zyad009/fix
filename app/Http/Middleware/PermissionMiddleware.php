<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * `permission:a|b` — passes when the user holds any of the listed permissions.
 */
class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = actor();

        foreach (explode('|', $permissions) as $permission) {
            if ($user?->hasPermission($permission)) {
                return $next($request);
            }
        }

        throw new AccessDeniedHttpException;
    }
}
