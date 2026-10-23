<?php

use App\Http\Controllers\V1\Admin\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');

    Route::middleware(['auth:sanctum', 'admin.only'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
    });
});
