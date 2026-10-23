<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    foreach (['admin', 'center'] as $module) {
        Route::prefix($module)->name($module.'.')->group(function () use ($module): void {
            requireRoutesFiles(__DIR__.'/api/v1/'.$module);
        });
    }
});
