<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the browser log viewer with HTTP Basic credentials from config. With
 * no credentials configured the page does not exist (404).
 */
class LogViewerBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $username = (string) config('services.log_viewer.username');
        $password = (string) config('services.log_viewer.password');

        abort_if($username === '' || $password === '', 404);

        $authorized = hash_equals($username, (string) $request->getUser())
            && hash_equals($password, (string) $request->getPassword());

        if (! $authorized) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="Logs"']);
        }

        return $next($request);
    }
}
