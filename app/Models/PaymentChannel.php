<?php

namespace App\Models;

use App\Enums\PaymentChannelType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Master media pembayaran (QRIS/rekening bank) yang ditampilkan teknisi ke
 * customer. Tidak menggantikan `payments.metode` — hanya daftar channel.
 *
 * @see dev-plan/database-schema.md §payment_channels
 */
class PaymentChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'jenis',
        'atas_nama',
        'nomor_rekening',
        'nama_bank',
        'gambar',
        'aktif',
        'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => PaymentChannelType::class,
            'aktif' => 'boolean',
        ];
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    /**
     * Channel yang aktif dan layak ditampilkan ke customer.
     */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('aktif', true);
    }

    public function scopeTerurut(Builder $query): Builder
    {
        return $query->orderBy('jenis')->orderBy('nama');
    }
}
