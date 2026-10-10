<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris tagihan invoice — salinan dari order_items yang boleh diedit admin
 * selama invoice masih draft. subtotal = jumlah × harga.
 */
class InvoiceItem extends Model
{
    protected $fillable = [
        'invoice_id',
        'order_id',
        'nama',
        'deskripsi',
        'jumlah',
        'harga',
        'subtotal',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'harga' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'urutan' => 'integer',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
