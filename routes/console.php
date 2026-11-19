<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SOP read-confirmation and review reminders (cache-throttled inside the service).
Schedule::command('sops:remind')->dailyAt('08:00');
