<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\AdminOnlyMiddleware;
use App\Http\Middleware\BranchContextMiddleware;
use App\Http\Middleware\EnsureSubscriptionActive;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\ResetTenancyContext;
use App\Http\Middleware\SetLangMiddleware;
use App\Http\Middleware\TenantContextMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES', '*'));
        // Global (not just the api group) so that unmatched-route 404s are localized too.
        $middleware->prepend([ResetTenancyContext::class, SetLangMiddleware::class]);

        $middleware->alias([
            'admin.only' => AdminOnlyMiddleware::class,
            'tenant' => TenantContextMiddleware::class,
            'branch' => BranchContextMiddleware::class,
            'subscription' => EnsureSubscriptionActive::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Tenant/branch context must exist before route-model binding runs,
        // so bound models are resolved through the tenant and branch scopes.
        // (Each is inserted right before SubstituteBindings, in this order.)
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: TenantContextMiddleware::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: BranchContextMiddleware::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        $envelope = fn (string $msg, int $status, mixed $data = [], ?string $code = null) => response()->json(
            array_filter(['status' => false, 'msg' => $msg, 'code' => $code, 'data' => $data], fn (mixed $value): bool => $value !== null),
            $status,
        );

        $exceptions->render(function (Throwable $e, Request $request) use ($envelope) {
            if (! $request->is('api/*') || $e instanceof ValidationException) {
                return null;
            }

            return match (true) {
                $e instanceof BusinessRuleException => $envelope($e->getMessage(), $e->status(), $e->data, $e->errorCode),
                $e instanceof AuthenticationException => $envelope(__('messages.unauthenticated'), 401),
                $e instanceof ModelNotFoundException,
                $e instanceof NotFoundHttpException => $envelope(__('messages.not_found'), 404),
                $e instanceof AccessDeniedHttpException => $envelope(__('messages.unauthorized'), 403),
                $e instanceof TooManyRequestsHttpException => $envelope(__('messages.too_many_requests'), 429),
                $e instanceof HttpExceptionInterface => $envelope($e->getMessage() ?: __('messages.main_message_error'), $e->getStatusCode()),
                default => $envelope(config('app.debug') ? $e->getMessage() : __('messages.main_message_error'), 500),
            };
        });
    })->create();
