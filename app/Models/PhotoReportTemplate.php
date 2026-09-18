<?php

namespace App\Models;

use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Model;

/**
 * dev-plan/17: 1 baris = 1 slot foto laporan yang ditawarkan ke teknisi
 * untuk kategori layanan tertentu — dikelola admin (aktif/nonaktif,
 * wajib/tidak), menggantikan hardcode `App\Support\FotoLaporanSlot`.
 */
class PhotoReportTemplate extends Model
{
    protected $fillable = [
        'kategori',
        'kode_slot',
        'label',
        'urutan',
        'wajib',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => ServiceType::class,
            'urutan' => 'integer',
            'wajib' => 'boolean',
            'aktif' => 'boolean',
        ];
    }
}
