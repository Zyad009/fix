<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class MacroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::macro('toggleActive', function (string $name, string $controller): void {
            Route::patch($name.'/toggle/{id}', [$controller, 'toggle'])->name($name.'.toggle');
        });
    }
}
