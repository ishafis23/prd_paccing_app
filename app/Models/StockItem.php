<?php

namespace App\Models;

use App\Enums\StockCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_barang',
        'kategori',
        'satuan',
        'stok_saat_ini',
        'stok_minimum',
        'harga_beli',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => StockCategory::class,
            'stok_saat_ini' => 'integer',
            'stok_minimum' => 'integer',
            'harga_beli' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function workReportMaterials(): HasMany
    {
        return $this->hasMany(WorkReportMaterial::class);
    }

    public function scopeMenipis($query)
    {
        return $query->whereColumn('stok_saat_ini', '<=', 'stok_minimum');
    }
}
