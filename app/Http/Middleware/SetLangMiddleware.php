<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLangMiddleware
{
    /** @var list<string> */
    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->header('lang', 'ar');

        App::setLocale(in_array($locale, self::SUPPORTED, true) ? $locale : 'ar');

        return $next($request);
    }
}
