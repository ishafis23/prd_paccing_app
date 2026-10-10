<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Merender dokumen laporan (keluaran LaporanPengerjaanService) menjadi PDF
 * lewat barryvdh/laravel-dompdf. Akses remote dimatikan: semua gambar masuk
 * sebagai data URI dari file lokal, jadi mesin tanpa internet tetap bisa cetak.
 */
class LaporanPdfService
{
    /**
     * @param  array<string, mixed>  $dokumen
     * @param  array<string, mixed>  $opsiOutput  diteruskan ke Dompdf::output() (mis. ['compress' => 0] utk uji)
     */
    public function render(array $dokumen, array $opsiOutput = []): string
    {
        // Laporan bulanan yang besar butuh waktu/memori lebih dari batas web default.
        @set_time_limit(0);
        if ((int) ini_get('memory_limit') !== -1 && $this->memoriMb() < 512) {
            @ini_set('memory_limit', '512M');
        }

        return Pdf::setOption([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'defaultFont' => 'Helvetica',
            'dpi' => 96,
            'chroot' => [base_path(), storage_path()],
        ])
            ->loadView('laporan.pdf', ['dokumen' => $dokumen])
            ->setPaper('a4')
            ->output($opsiOutput);
    }

    public static function jumlahHalaman(string $pdf): int
    {
        return (int) preg_match_all('/\/Type\s*\/Page(?![s\w])/', $pdf);
    }

    private function memoriMb(): int
    {
        $nilai = trim((string) ini_get('memory_limit'));
        $angka = (int) $nilai;

        return match (strtolower(substr($nilai, -1))) {
            'g' => $angka * 1024,
            'k' => intdiv($angka, 1024),
            'm' => $angka,
            default => intdiv($angka, 1024 * 1024),
        };
    }
}
