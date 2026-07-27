<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('notifications:check-reservations')->hourly();

// Keeps the rolling window of auto-generated Mass reservations topped up.
// Idempotent (see GenerateMassSchedule), so running daily is just a cheap
// way to make sure the window always extends
// config('mass_schedule.generate_weeks_ahead') weeks out — it never
// re-creates or overwrites rows that already exist for a given
// (template, date) pair.
Schedule::command('mass:generate-schedule')->daily();