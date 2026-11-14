<?php

namespace App\Models;

use App\Enums\ReminderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'order_id',
        'interval_bulan',
        'tanggal_servis_berikutnya',
        'status_notice',
        'sumber_interval',
        'status_kirim',
        'percobaan',
        'jumlah_terkirim',
        'terakhir_dicoba_at',
        'terkirim_at',
        'error_terakhir',
        'tahap',
    ];

    public const KIRIM_MENUNGGU = 0;

    public const KIRIM_TERKIRIM = 1;

    public const KIRIM_GAGAL = 2;

    public const MAKS_PERCOBAAN = 3;

    protected function casts(): array
    {
        return [
            'interval_bulan' => 'integer',
            'tanggal_servis_berikutnya' => 'date:Y-m-d',
            'status_notice' => ReminderStatus::class,
            'status_kirim' => 'integer',
            'percobaan' => 'integer',
            'jumlah_terkirim' => 'integer',
            'terakhir_dicoba_at' => 'datetime',
            'terkirim_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Reminder yang harus diproses cron: sudah jatuh tempo, belum ditutup/dihubungi
     * manual, dan percobaan kirim belum habis.
     */
    public function scopeAntreanKirim(Builder $query): Builder
    {
        return $query
            ->where('tanggal_servis_berikutnya', '<=', now()->toDateString())
            ->whereNotIn('status_notice', [ReminderStatus::Selesai->value, ReminderStatus::SudahDihubungi->value])
            ->where('percobaan', '<', self::MAKS_PERCOBAAN);
    }

    /**
     * Reminder yang sudah mendekati jatuh tempo (H-7) dan belum dihubungi.
     */
    public function scopeJatuhTempo(Builder $query, int $days = 7): Builder
    {
        return $query
            ->where('tanggal_servis_berikutnya', '<=', now()->addDays($days)->toDateString())
            ->whereIn('status_notice', [
                ReminderStatus::BelumJatuhTempo->value,
                ReminderStatus::SiapDihubungi->value,
            ]);
    }
}
