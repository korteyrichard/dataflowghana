<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Setting;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule the order status sync command (only if sync is enabled)
Schedule::command('orders:sync-statuses')->everyFiveMinutes()->when(function () {
    return (bool) Setting::get('order_sync_enabled', 1);
});

// Complete bigtime and telecel orders older than 30 minutes
Schedule::command('orders:complete-old')->everyTenMinutes();
