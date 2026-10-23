<?php

use App\Http\Controllers\V1\Admin\BranchController;
use App\Http\Controllers\V1\Admin\CarMakeController;
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

    Route::controller(CarMakeController::class)->group(function (): void {
        Route::get('car-makes', 'index')->name('car-makes.index');
        Route::post('car-makes', 'store')->name('car-makes.store');
        Route::put('car-makes/{car_make}', 'update')->name('car-makes.update');
        Route::patch('car-makes/toggle/{id}', 'toggle')->name('car-makes.toggle');
        Route::post('car-makes/{car_make}/models', 'storeModel')->name('car-models.store');
        Route::put('car-models/{car_model}', 'updateModel')->name('car-models.update');
        Route::patch('car-models/toggle/{id}', 'toggleModel')->name('car-models.toggle');
    });
});
