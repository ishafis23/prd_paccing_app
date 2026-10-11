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
        'omset_jasa',
        'omset_material',
        'catatan',
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
            'omset_jasa' => 'decimal:2',
            'omset_material' => 'decimal:2',
            'tanggal_bayar' => 'date:Y-m-d',
        ];
    }

    /**
     * Split omset jasa/material sudah diisi di pembayaran ini (dev-plan/24)?
     * Bila ya, inilah acuan final; bila tidak, laporan jatuh ke `order_items`.
     */
    public function punyaSplitOmset(): bool
    {
        return $this->omset_jasa !== null && $this->omset_material !== null;
    }

    /**
     * @return array{jasa: float, material: float}|null
     */
    public function splitOmset(): ?array
    {
        if (! $this->punyaSplitOmset()) {
            return null;
        }

        return ['jasa' => (float) $this->omset_jasa, 'material' => (float) $this->omset_material];
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
