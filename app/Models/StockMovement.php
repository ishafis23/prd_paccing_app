<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_item_id',
        'jenis',
        'jumlah',
        'referensi',
        'keterangan',
        'dicatat_oleh',
        'tanggal',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => MovementType::class,
            'jumlah' => 'integer',
            'tanggal' => 'date:Y-m-d',
        ];
    }

    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    /** Dampak jumlah terhadap stok: keluar mengurangi, lainnya menambah. */
    public function stokDelta(): int
    {
        return $this->jenis === MovementType::Keluar ? -$this->jumlah : $this->jumlah;
    }
}
