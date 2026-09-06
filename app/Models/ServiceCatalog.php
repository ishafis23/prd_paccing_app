<?php

namespace App\Models;

use App\Enums\ServiceType;
use App\Enums\UnitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceCatalog extends Model
{
    use HasFactory;

    protected $fillable = [
        'jenis_layanan',
        'jenis_unit',
        'pk',
        'harga',
        'interval_bulan',
        'aktif',
        // B38: konten layanan untuk landing page.
        'deskripsi',
        'gambar',
        'tampil_beranda',
        'urutan_beranda',
    ];

    protected function casts(): array
    {
        return [
            'jenis_layanan' => ServiceType::class,
            'jenis_unit' => UnitType::class,
            'harga' => 'decimal:2',
            'interval_bulan' => 'integer',
            'aktif' => 'boolean',
            'tampil_beranda' => 'boolean',
            'urutan_beranda' => 'integer',
        ];
    }

    /**
     * Label ringkas utk tampilan admin/landing: "Cuci AC · Split · 1 PK".
     */
    public function labelLayanan(): string
    {
        $bagian = array_filter([
            $this->jenis_layanan ? str($this->jenis_layanan->value)->headline()->toString() : null,
            $this->jenis_unit ? str($this->jenis_unit->value)->headline()->toString() : null,
            $this->pk ?: null,
        ]);

        return implode(' · ', $bagian);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
