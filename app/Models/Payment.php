<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'metode',
        'status',
        'total_tagihan',
        'jumlah_dibayar',
        'tanggal_bayar',
        'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'metode' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'total_tagihan' => 'decimal:2',
            'jumlah_dibayar' => 'decimal:2',
            'tanggal_bayar' => 'date:Y-m-d',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
