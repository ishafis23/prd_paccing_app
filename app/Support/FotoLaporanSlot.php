<?php

namespace App\Support;

use App\Enums\ServiceType;
use App\Services\PhotoReportTemplateService;

/**
 * Template slot foto laporan per kategori order_item (dev-plan/13 §3).
 * Sejak dev-plan/17, isinya diambil dari tabel `photo_report_templates`
 * (dikelola admin, B61) lewat `PhotoReportTemplateService` — kelas ini
 * tetap jadi titik panggil stabil dipakai `OrderDetail`/`TeknisiService`,
 * supaya call site tidak perlu berubah.
 *
 * @return array<string, string> slot key => label, terurut sesuai urutan tampil
 */
class FotoLaporanSlot
{
    /**
     * Kategori tanpa baris aktif (mis. Pengadaan AC, atau kategori kosong)
     * dapat template umum sebelum/sesudah — jaring pengaman, bukan bagian
     * yang diatur admin, dan tidak pernah wajib.
     */
    public static function untuk(?ServiceType $kategori): array
    {
        $slot = app(PhotoReportTemplateService::class)->untukKategori($kategori);

        return $slot !== [] ? $slot : [
            'sebelum' => 'Sebelum',
            'sesudah' => 'Sesudah',
        ];
    }

    /**
     * Subset dari untuk() yang wajib diisi sebelum laporan bisa disubmit
     * (dev-plan/17, B63). Selalu kosong utk kategori yang jatuh ke
     * fallback umum (sebelum/sesudah tidak pernah wajib).
     */
    public static function wajibUntuk(?ServiceType $kategori): array
    {
        return app(PhotoReportTemplateService::class)->daftarWajib($kategori);
    }
}
