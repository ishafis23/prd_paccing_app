<?php

namespace App\Models;

use App\Enums\IncentiveKategori;
use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\IncentiveSumber;
use App\Enums\IncentiveTipe;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger generik bonus/denda teknisi (dev-plan/15, §4) — 1 baris = 1
 * kejadian (Games 1-6, denda telat, dll) per teknisi/tanggal/kategori.
 */
class TechnicianIncentive extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tanggal',
        'kategori',
        'tipe',
        'nominal',
        'foto_bukti',
        'sumber',
        'referensi',
        'dicatat_oleh',
        'catatan',
        'status_verifikasi',
        'diverifikasi_pada',
        'diverifikasi_oleh',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => IncentiveKategori::class,
            'tipe' => IncentiveTipe::class,
            'sumber' => IncentiveSumber::class,
            'status_verifikasi' => IncentiveStatusVerifikasi::class,
            'tanggal' => 'date:Y-m-d',
            'nominal' => 'decimal:2',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
