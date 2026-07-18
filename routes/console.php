<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('winprox:recurrence-tick')->dailyAt('06:00');
Schedule::command('winprox:time-auto-close-stale')->hourly();
Schedule::command('winprox:time-finalize-qr-grace')->dailyAt('04:15');
Schedule::command('winprox:retention-prune')->dailyAt('03:30');
Schedule::command('marketing:process-promo-bounces')->hourly();
Schedule::command('translation:backfill-slots')->dailyAt('01:55');
Schedule::command('translation:export')->dailyAt('02:00');
Schedule::command('translation:import')->hourly();
Schedule::command('queue:work database --max-time=55 --sleep=1 --tries=3')
    ->everyMinute()
    ->withoutOverlapping();
