<?php

use App\Http\Controllers\V1\Center\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->name('auth.')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login');

    Route::middleware(['auth:sanctum', 'tenant'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::put('password', [AuthController::class, 'changePassword'])->name('password');
    });
});
