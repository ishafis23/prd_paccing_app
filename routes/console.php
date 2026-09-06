<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// B26: pembersihan otomatis foto tua & file yatim — harian 03:00.
Schedule::command('foto:bersihkan')->dailyAt('03:00')->withoutOverlapping();
