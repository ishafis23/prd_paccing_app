<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'teknisi_id',
        'catatan_pengerjaan',
        'foto_sebelum',
        'foto_sesudah',
        'waktu_mulai',
        'waktu_selesai',
        'diverifikasi_pada',
        'diverifikasi_oleh',
    ];

    protected function casts(): array
    {
        return [
            'waktu_mulai' => 'datetime',
            'waktu_selesai' => 'datetime',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function sudahDiverifikasi(): bool
    {
        return $this->diverifikasi_pada !== null;
    }

    public function materials(): HasMany
    {
        return $this->hasMany(WorkReportMaterial::class);
    }

    /**
     * Foto laporan per kategori order_item (dev-plan/13 §3) — laporan baru
     * pakai ini, bukan lagi kolom foto_sebelum/foto_sesudah.
     */
    public function photos(): HasMany
    {
        return $this->hasMany(WorkReportPhoto::class);
    }
}
