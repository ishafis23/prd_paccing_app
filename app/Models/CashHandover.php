<?php

namespace App\Models;

use App\Enums\CashHandoverStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Laporan setoran uang tunai dari teknisi untuk satu order (dev-plan/29 C2).
 */
class CashHandover extends Model
{
    protected $fillable = [
        'order_id',
        'teknisi_id',
        'tanggal_setor',
        'nominal',
        'diserahkan_ke',
        'foto_path',
        'catatan',
        'status',
        'diterima_oleh',
        'diterima_pada',
        'catatan_verifikasi',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_setor' => 'date',
            'nominal' => 'decimal:2',
            'status' => CashHandoverStatus::class,
            'diterima_pada' => 'datetime',
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

    public function diterimaOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh');
    }

    public function scopeMenunggu(Builder $query): Builder
    {
        return $query->where('status', CashHandoverStatus::Menunggu->value);
    }

    public function scopeDiterima(Builder $query): Builder
    {
        return $query->where('status', CashHandoverStatus::Diterima->value);
    }

    public function fotoUrl(): ?string
    {
        return filled($this->foto_path) ? asset('storage/'.ltrim($this->foto_path, '/')) : null;
    }
}
