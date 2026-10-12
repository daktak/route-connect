<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rides:send-reminders')->dailyAt('08:00')->timezone(config('app.default_timezone'));
Schedule::command('rides:mark-past-completed')->dailyAt('00:05')->timezone(config('app.default_timezone'));
