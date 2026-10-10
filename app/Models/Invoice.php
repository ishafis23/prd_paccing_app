<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice (dev-plan/21 §7): satu invoice memuat satu atau banyak order milik
 * customer yang sama. subtotal/total selalu dihitung dari invoice_items;
 * snapshot bank disalin dari BusinessInfo saat dibuat.
 */
class Invoice extends Model
{
    protected $fillable = [
        'nomor',
        'customer_id',
        'tanggal',
        'jatuh_tempo',
        'status',
        'catatan',
        'subtotal',
        'total',
        'bank_nama',
        'bank_rekening',
        'bank_atas_nama',
        'token',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'tanggal' => 'date:Y-m-d',
            'jatuh_tempo' => 'date:Y-m-d',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'invoice_orders')->withTimestamps();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('urutan')->orderBy('id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    /** Invoice yang masih berlaku sebagai tagihan (bukan batal). */
    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', '!=', InvoiceStatus::Batal->value);
    }

    public function batal(): bool
    {
        return $this->status === InvoiceStatus::Batal;
    }

    public function draft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }

    /**
     * Σ pembayaran tercatat (payments.jumlah_dibayar) pada order-order invoice.
     */
    public function totalDibayar(): float
    {
        $this->loadMissing('orders.payments');

        return (float) $this->orders->sum(fn (Order $o) => $o->payments->sum(fn (Payment $p) => (float) $p->jumlah_dibayar));
    }

    /** Saldo jatuh tempo = total − Σ pembayaran (tidak pernah negatif). */
    public function saldo(): float
    {
        return max(0.0, round((float) $this->total - $this->totalDibayar(), 2));
    }

    public function lewatJatuhTempo(): bool
    {
        return in_array($this->status, [InvoiceStatus::Terkirim], true)
            && $this->jatuh_tempo !== null
            && $this->jatuh_tempo->lt(now()->startOfDay())
            && $this->saldo() > 0;
    }
}
