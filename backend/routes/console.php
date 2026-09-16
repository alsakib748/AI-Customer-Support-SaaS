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


// Process subscription renewals daily
// Process renewals and expirations daily at 1 AM
Schedule::command('billing:process --renew --expire')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/billing.log'));

// Send renewal reminders daily at 9 AM
Schedule::command('billing:process --reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/billing.log'));

// Send trial ending reminders daily at 10 AM
Schedule::command('billing:process --trial-reminders')
    ->dailyAt('10:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/billing.log'));

// Reset monthly usage on 1st of month
Schedule::command('billing:process --reset-usage')
    ->monthlyOn(1, '00:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/billing.log'));

// Sync storage usage every 6 hours
Schedule::command('billing:sync-storage')
    ->everySixHours()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/billing.log'));

// Reconcile subscriptions daily at 2 AM
Schedule::command('billing:reconcile')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/billing.log'));

// Sync storage usage every 6 hours
Schedule::command('billing:sync-storage')
    ->everySixHours()
    ->appendOutputTo(storage_path('logs/billing.log'));