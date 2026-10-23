<?php

use App\Http\Controllers\V1\Center\AuditLogController;
use App\Http\Controllers\V1\Center\SettingsController;
use App\Http\Controllers\V1\Center\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'tenant', 'branch', 'subscription'])->group(function (): void {
    Route::get('settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::post('settings/logo', [SettingsController::class, 'logo'])->name('settings.logo');

    Route::apiResource('users', UserController::class)->except('destroy');
    Route::toggleActive('users', UserController::class);

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
});
