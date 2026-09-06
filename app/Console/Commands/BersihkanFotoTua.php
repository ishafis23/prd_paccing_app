<?php

namespace App\Console\Commands;

use App\Models\WorkReport;
use App\Services\StorageQuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Bersihkan foto pengerjaan yang berumur melebihi ambang (B23/B26):
 * - file foto pada work_reports tua dihapus + kolom foto dikosongkan
 *   (riwayat/catatan laporan tetap utuh);
 * - file yatim di folder work-reports (tanpa referensi DB) dihapus.
 * File lain (QRIS channel, logo, bukti) TIDAK disentuh.
 */
class BersihkanFotoTua extends Command
{
    protected $signature = 'foto:bersihkan';

    protected $description = 'Hapus file foto work_reports berumur > ambang & file yatim (kebijakan penyimpanan B23)';

    public function handle(): int
    {
        $disk = Storage::disk((string) config('penyimpanan.disk', 'public'));
        $folder = (string) config('penyimpanan.folder_foto', 'work-reports');
        $batas = now()->subDays((int) config('penyimpanan.max_umur_hari', 60));

        $laporanDibersihkan = 0;
        $fileDihapus = 0;

        // 1) Foto tua pada laporan: hapus file, kosongkan kolom.
        $tua = WorkReport::query()
            ->where('created_at', '<=', $batas)
            ->where(fn ($q) => $q->whereNotNull('foto_sebelum')->orWhereNotNull('foto_sesudah'))
            ->get();

        foreach ($tua as $report) {
            foreach (['foto_sebelum', 'foto_sesudah'] as $kolom) {
                $path = $report->{$kolom};

                if ($path !== null) {
                    if ($disk->exists($path)) {
                        $disk->delete($path);
                        $fileDihapus++;
                    }
                    $report->{$kolom} = null;
                }
            }
            $report->save();
            $laporanDibersihkan++;
        }

        // 2) File yatim: file di folder foto yang tidak dirujuk kolom mana pun.
        $dirujuk = WorkReport::query()
            ->whereNotNull('foto_sebelum')
            ->pluck('foto_sebelum')
            ->merge(WorkReport::query()->whereNotNull('foto_sesudah')->pluck('foto_sesudah'))
            ->filter()
            ->flip();

        foreach ($disk->allFiles($folder) as $file) {
            if (! isset($dirujuk[$file])) {
                $disk->delete($file);
                $fileDihapus++;
            }
        }

        StorageQuotaService::lupakanCache();

        $this->info(sprintf(
            'Selesai: %d laporan dibersihkan fotonya, %d file dihapus.',
            $laporanDibersihkan,
            $fileDihapus
        ));

        return self::SUCCESS;
    }
}
