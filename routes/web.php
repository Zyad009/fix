<?php

use App\Http\Middleware\LogViewerBasicAuth;
use Illuminate\Support\Facades\Route;
use Rap2hpoutre\LaravelLogViewer\LogViewerController;

Route::get('logs', [LogViewerController::class, 'index'])
    ->middleware(LogViewerBasicAuth::class)
    ->name('logs');
