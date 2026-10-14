<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Template pesan WA yang dikelola admin (dev-plan/27 fase 27-A).
 * `isi` memakai placeholder {nama}, {layanan}, {total}, dst — diisi oleh
 * App\Services\WaTemplateService. Bila baris dinonaktifkan (aktif=false),
 * pemakaian otomatis dilewati; bila baris tidak ada, dipakai default bawaan.
 */
class WaTemplate extends Model
{
    protected $fillable = [
        'kode',
        'nama',
        'isi',
        'aktif',
        'diubah_oleh',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Catat pengubah terakhir (tidak menimpa bila dari proses sistem/queue).
        static::saving(function (WaTemplate $template): void {
            if (auth()->check()) {
                $template->diubah_oleh = auth()->id();
            }
        });
    }

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
