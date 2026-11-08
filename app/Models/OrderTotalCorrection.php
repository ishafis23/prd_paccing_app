<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTotalCorrection extends Model
{
    protected $fillable = [
        'order_id',
        'total_original',
        'total_terkoreksi',
        'alasan',
        'dikoreksi_oleh',
        'disinkron_ke_pembayaran_pada',
    ];

    protected function casts(): array
    {
        return [
            'total_original' => 'decimal:2',
            'total_terkoreksi' => 'decimal:2',
            'disinkron_ke_pembayaran_pada' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pengkoreksi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikoreksi_oleh');
    }
}
