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
    ];

    protected function casts(): array
    {
        return [
            'interval_bulan' => 'integer',
            'tanggal_servis_berikutnya' => 'date:Y-m-d',
            'status_notice' => ReminderStatus::class,
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
