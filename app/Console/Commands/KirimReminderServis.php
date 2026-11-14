<?php

namespace App\Console\Commands;

use App\Services\ServiceReminderService;
use Illuminate\Console\Command;

/**
 * Kirim pengingat servis berkala (WA) ke customer yang sudah jatuh tempo.
 * Sukses -> status_kirim 1 + jadwal bulanan berikutnya; gagal -> dicoba lagi
 * pada jalannya berikutnya (maks. ServiceReminder::MAKS_PERCOBAAN kali).
 */
class KirimReminderServis extends Command
{
    protected $signature = 'reminder:kirim';

    protected $description = 'Kirim pengingat servis berkala (WA) untuk reminder yang jatuh tempo';

    public function handle(ServiceReminderService $service): int
    {
        $h = $service->prosesAntrean();

        $this->info(sprintf(
            'Reminder servis: %d terkirim, %d gagal, %d dilewati, %d ditutup.',
            $h['terkirim'], $h['gagal'], $h['dilewati'], $h['ditutup'],
        ));

        return self::SUCCESS;
    }
}
