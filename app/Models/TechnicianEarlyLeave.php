<?php

namespace App\Models;

use App\Enums\EarlyLeaveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengajuan pulang cepat teknisi (dev-plan/hrd/02).
 */
class TechnicianEarlyLeave extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tanggal',
        'jam_pulang',
        'alasan',
        'status',
        'catatan_hrd',
        'diverifikasi_oleh',
        'diverifikasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'status' => EarlyLeaveStatus::class,
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public function scopeDisetujui(Builder $query): Builder
    {
        return $query->where('status', EarlyLeaveStatus::Disetujui->value);
    }
}
