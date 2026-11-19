<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Goods-receipt findings past their due date (needs the Laravel scheduler running on the server).
Schedule::command('receipts:notify-overdue')->hourly();

// SOP read-confirmation and review reminders (cache-throttled inside the service).
Schedule::command('sops:remind')->dailyAt('08:00');
