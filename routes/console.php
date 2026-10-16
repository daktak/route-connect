<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('rides:send-reminders')->hourly()->withoutOverlapping();
Schedule::command('rides:mark-past-completed')->hourly()->withoutOverlapping();
