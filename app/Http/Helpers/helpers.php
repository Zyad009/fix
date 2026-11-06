<?php

use App\Models\User;
use App\Support\MaintenanceCenter\BranchContext;
use App\Support\MaintenanceCenter\MaintenanceCenterContext;
use App\Support\Money;
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

if (! function_exists('maintenanceCenterContext')) {
    function maintenanceCenterContext(): MaintenanceCenterContext
    {
        return app(MaintenanceCenterContext::class);
    }
}

if (! function_exists('branchContext')) {
    function branchContext(): BranchContext
    {
        return app(BranchContext::class);
    }
}

if (! function_exists('maintenanceCenterExists')) {
    /**
     * `exists` rule limited to the current maintenance center (and to live rows when the
     * table soft-deletes).
     */
    function maintenanceCenterExists(string $table, string $column = 'id', bool $withoutTrashed = false): Exists
    {
        $rule = Rule::exists($table, $column)->where('maintenance_center_id', maintenanceCenterContext()->id());

        return $withoutTrashed ? $rule->withoutTrashed() : $rule;
    }
}

if (! function_exists('maintenanceCenterUnique')) {
    /**
     * `unique` rule limited to the current maintenance center.
     */
    function maintenanceCenterUnique(string $table, string $column): Unique
    {
        return Rule::unique($table, $column)->where('maintenance_center_id', maintenanceCenterContext()->id());
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
