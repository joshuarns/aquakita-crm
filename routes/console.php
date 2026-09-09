<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Avisos diarios de seguimientos próximos y vencidos (§4.3).
Schedule::command('leads:notify-followups')->dailyAt('08:00');
