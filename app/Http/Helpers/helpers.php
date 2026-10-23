<?php

use App\Models\User;
use App\Support\Money;
use App\Support\Tenancy\BranchContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

if (! function_exists('log_error')) {
    /**
     * Log an exception with its origin and the acting user.
     *
     * @param  array<string, mixed>  $context
     */
    function log_error(Throwable $exception, string $channel = 'stack', string $message = '', array $context = []): void
    {
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)[1] ?? null;

        $context = array_merge([
            'exception' => $exception::class,
            'exception_message' => $exception->getMessage(),
            'exception_code' => $exception->getCode(),
            'exception_file' => $exception->getFile(),
            'exception_line' => $exception->getLine(),
            'user_id' => Auth::id(),
            'called_class' => $caller['class'] ?? null,
            'called_function' => $caller['function'] ?? null,
        ], $context);

        Log::channel($channel)->error($message ?: 'An error occurred', $context);
    }
}

if (! function_exists('requireRoutesFiles')) {
    /**
     * Require every route file in a directory (one file per module area).
     */
    function requireRoutesFiles(string $directory): void
    {
        foreach (glob($directory.'/*.php') ?: [] as $file) {
            require $file;
        }
    }
}

if (! function_exists('actor')) {
    /**
     * The authenticated service-center user, if any.
     */
    function actor(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}

if (! function_exists('tenantContext')) {
    function tenantContext(): TenantContext
    {
        return app(TenantContext::class);
    }
}

if (! function_exists('branchContext')) {
    function branchContext(): BranchContext
    {
        return app(BranchContext::class);
    }
}

if (! function_exists('tenantExists')) {
    /**
     * `exists` rule limited to the current tenant (and to live rows when the
     * table soft-deletes).
     */
    function tenantExists(string $table, string $column = 'id', bool $withoutTrashed = false): Exists
    {
        $rule = Rule::exists($table, $column)->where('tenant_id', tenantContext()->id());

        return $withoutTrashed ? $rule->withoutTrashed() : $rule;
    }
}

if (! function_exists('tenantUnique')) {
    /**
     * `unique` rule limited to the current tenant.
     */
    function tenantUnique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('tenant_id', tenantContext()->id());
    }
}

if (! function_exists('publicFileUrl')) {
    function publicFileUrl(?string $path): ?string
    {
        return $path ? Storage::disk(config('filesystems.default'))->url($path) : null;
    }
}

if (! function_exists('money')) {
    function money(Money|string|int|null $value): Money
    {
        return Money::of($value);
    }
}

if (! function_exists('formatNumber')) {
    /**
     * Money/quantity value as a JSON number, rounded to two decimals.
     */
    function formatNumber(Money|string|int|null $value): float
    {
        return Money::of($value)->toFloat();
    }
}
