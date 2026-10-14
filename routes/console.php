<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tmdb:refresh-stale')->dailyAt('03:00');

Schedule::command('tmdb:import', ['--from' => now()->subYear()->year])->weeklyOn(0, '04:00');

Schedule::command('ebay:snapshot-prices')->dailyAt('06:00');
