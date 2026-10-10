<?php

namespace App\Jobs;

use App\Models\LaporanBulanan;
use App\Services\LaporanBulananService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Membuat PDF Laporan Bulanan di antrean. Hasil/ status/ error dicatat oleh
 * LaporanBulananService::kerjakan() di tabel laporan_bulanan. Bila tidak ada
 * queue worker, admin memakai tombol "Buat sekarang (sinkron)" di halaman.
 */
class BuatLaporanBulananJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(public readonly int $laporanBulananId) {}

    public function handle(LaporanBulananService $service): void
    {
        $permintaan = LaporanBulanan::query()->find($this->laporanBulananId);

        // Dihapus admin sebelum sempat jalan, atau sudah selesai (job ganda).
        if ($permintaan === null || $permintaan->status === LaporanBulanan::SELESAI) {
            return;
        }

        $service->kerjakan($permintaan);
    }

    /**
     * Kegagalan di luar kerjakan() (mis. timeout / worker mati).
     */
    public function failed(Throwable $e): void
    {
        $permintaan = LaporanBulanan::query()->find($this->laporanBulananId);

        if ($permintaan !== null) {
            app(LaporanBulananService::class)->catatGagal($permintaan, $e);
        }
    }
}
