<?php

namespace App\Models;

use App\Enums\IncomeCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Jenis Layanan TAMBAHAN (dev-plan/26) — melengkapi enum `ServiceType`
 * (bawaan, read-only). Dikelola lewat menu "Jenis Layanan".
 */
class ServiceTypeOption extends Model
{
    protected $table = 'service_types';

    protected $fillable = [
        'kode',
        'label',
        'komponen',
        'bukan_omset',
        'urutan',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'komponen' => IncomeCategory::class,
            'bukan_omset' => 'boolean',
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }
}
