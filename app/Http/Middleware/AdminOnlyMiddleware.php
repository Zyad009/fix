<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminOnlyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user();

        if (! $admin instanceof Admin || ! $admin->is_active || ! $admin->tokenCan('admin')) {
            throw new AuthenticationException;
        }

        return $next($request);
    }
}
