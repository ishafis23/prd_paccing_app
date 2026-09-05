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
    ];

    protected function casts(): array
    {
        return [
            'jenis_layanan' => ServiceType::class,
            'jenis_unit' => UnitType::class,
            'harga' => 'decimal:2',
            'interval_bulan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
