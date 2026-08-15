<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('vsla:generate-contributions')->dailyAt('00:05')->withoutOverlapping();
Schedule::command('vsla:apply-late-fees')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('vsla:rebuild-passbooks')->weeklyOn(0, '02:00')->withoutOverlapping();
// Compound interest: run on the 1st of each month at 00:10.
Schedule::command('vsla:accrue-loan-interest')->monthlyOn(1, '00:10')->withoutOverlapping();
Schedule::command('vsla:flag-defaulted-loans')->dailyAt('01:00')->withoutOverlapping();
