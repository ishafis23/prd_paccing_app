<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// B26: pembersihan otomatis foto tua & file yatim — harian 03:00.
// Revisi 14 Nov 2026: pengingat servis berkala ke customer — harian 09:00.
Schedule::command('reminder:kirim')->dailyAt('09:00')->withoutOverlapping();
// Revisi 14 Nov 2026 poin 23: laporan + invoice/kuitansi dikirim sistem, tidak menunggu teknisi/admin.
Schedule::command('laporan:kirim-otomatis')->everyFiveMinutes()->withoutOverlapping(30);

Schedule::command('foto:bersihkan')->dailyAt('03:00')->withoutOverlapping();
