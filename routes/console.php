<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Service-date and overdue-debt reminders to customers on WhatsApp.
Schedule::command('whatsapp:reminders')
    ->dailyAt('10:00')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->onOneServer();
