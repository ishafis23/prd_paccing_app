<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Merender keluaran LaporanRekapService menjadi PDF landscape lewat dompdf
 * (pengaturan sama dgn LaporanPdfService: tanpa akses remote).
 */
class LaporanRekapPdfService
{
    /**
     * @param  array<string, mixed>  $data  keluaran LaporanRekapService::susun()
     */
    public function render(array $data, string $namaUsaha): string
    {
        @set_time_limit(0);

        return Pdf::setOption([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'defaultFont' => 'Helvetica',
            'dpi' => 96,
            'chroot' => [base_path(), storage_path()],
        ])
            ->loadView('laporan.rekap-dana', ['data' => $data, 'namaUsaha' => $namaUsaha])
            ->setPaper('a4', 'landscape')
            ->output();
    }
}
