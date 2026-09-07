<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run daily at 2 AM
Schedule::command('widget:cleanup-sessions --days=30')
    ->dailyAt('02:00')
    ->appendOutputTo(storage_path('logs/widget-cleanup.log'));

// Run weekly soft cleanup
Schedule::command('widget:cleanup-sessions --soft --days=7')
    ->weekly()
    ->appendOutputTo(storage_path('logs/widget-cleanup.log'));