<?php

use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
