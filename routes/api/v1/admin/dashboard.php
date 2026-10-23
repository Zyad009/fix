<?php

use App\Http\Controllers\V1\Admin\BranchController;
use App\Http\Controllers\V1\Admin\TenantController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin.only'])->group(function (): void {
    Route::apiResource('tenants', TenantController::class)->except('destroy');
    Route::toggleActive('tenants', TenantController::class);
    Route::patch('tenants/{tenant}/subscription', [TenantController::class, 'subscription'])->name('tenants.subscription');

    Route::prefix('tenants/{tenant}/branches')->name('tenants.branches.')->controller(BranchController::class)->group(function (): void {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->name('store');
        Route::put('{branch}', 'update')->name('update');
        Route::patch('{branch}/toggle', 'toggle')->name('toggle');
    });
});
