<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Flag missing clock-outs every morning (Asia/Jakarta).
Schedule::command('attendance:mark-exceptions')->dailyAt('01:00')->timezone('Asia/Jakarta');

// Storage reconciliation + idempotency pruning every 6 hours.
Schedule::command('storage:reconcile')->everySixHours();
