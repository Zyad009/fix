<?php

use App\Http\Controllers\V1\Admin\BranchController;
use App\Http\Controllers\V1\Admin\CarMakeController;
use App\Http\Controllers\V1\Admin\MaintenanceCenterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'admin.only'])->group(function (): void {
    Route::apiResource('maintenance-centers', MaintenanceCenterController::class)->except('destroy');
    Route::toggleActive('maintenance-centers', MaintenanceCenterController::class);
    Route::patch('maintenance-centers/{maintenance_center}/subscription', [MaintenanceCenterController::class, 'subscription'])->name('maintenance-centers.subscription');

    Route::prefix('maintenance-centers/{maintenance_center}/branches')->name('maintenance-centers.branches.')->controller(BranchController::class)->group(function (): void {
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
