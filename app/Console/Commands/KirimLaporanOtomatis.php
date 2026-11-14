<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\NotifikasiWaService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Revisi 14 Nov 2026 poin 23 — laporan pengerjaan + invoice/kuitansi dikirim ke customer oleh
 * SISTEM (cron), tidak lagi bergantung pada teknisi/admin. Order yang sudah selesai dan sudah
 * lewat jeda (default 15 menit, agar teknisi sempat merapikan foto/keterangan) diproses;
 * dokumen yang sudah terkirim dilewati (idempoten). Gagal dicoba lagi tiap putaran hingga batas
 * percobaan (NotifikasiWaService::MAKS_PERCOBAAN_OTOMATIS), lalu menunggu kirim manual admin.
 */
class KirimLaporanOtomatis extends Command
{
    protected $signature = 'laporan:kirim-otomatis {--jeda= : Menit sejak order selesai (default config)} {--hari= : Jendela hari ke belakang (default config)}';

    protected $description = 'Kirim otomatis laporan pengerjaan & invoice/kuitansi order selesai ke WA customer';

    public function handle(NotifikasiWaService $notif): int
    {
        if (! config('wa.notif_otomatis_selesai', false)) {
            $this->info('Kirim otomatis nonaktif (WA_NOTIF_OTOMATIS_SELESAI=false).');

            return self::SUCCESS;
        }

        $jeda = (int) ($this->option('jeda') ?? config('wa.jeda_kirim_otomatis_menit', 15));
        $hari = (int) ($this->option('hari') ?? config('wa.jendela_kirim_otomatis_hari', 3));

        $ringkas = ['terkirim' => 0, 'sudah' => 0, 'dilewati' => 0, 'gagal' => 0, 'error' => 0];

        Order::query()
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::ButuhFollowup->value])
            ->where('updated_at', '<=', now()->subMinutes(max(0, $jeda)))
            ->where('updated_at', '>=', now()->subDays(max(1, $hari)))
            ->orderBy('id')
            ->each(function (Order $order) use ($notif, &$ringkas): void {
                try {
                    foreach ($notif->kirimOtomatisSelesai($order) as $hasil) {
                        $kunci = match (true) {
                            $hasil === 'terkirim' => 'terkirim',
                            $hasil === 'sudah' => 'sudah',
                            str_starts_with($hasil, 'gagal') => 'gagal',
                            default => 'dilewati',
                        };
                        $ringkas[$kunci]++;
                    }
                } catch (Throwable $e) {
                    $ringkas['error']++;
                    report($e);
                }
            });

        $this->info(sprintf(
            'Selesai: %d terkirim, %d sudah pernah, %d dilewati, %d gagal, %d error.',
            $ringkas['terkirim'], $ringkas['sudah'], $ringkas['dilewati'], $ringkas['gagal'], $ringkas['error'],
        ));

        return self::SUCCESS;
    }
}
