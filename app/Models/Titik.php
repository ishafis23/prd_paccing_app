<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Slot jam kunjungan baku (dev-plan/18) — mis. "Titik 1" = 08:15. Admin
 * kelola sekali, dipakai berulang tiap hari saat bikin order lewat wizard
 * Create Order, gantikan input jam manual.
 */
class Titik extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'jam',
        'urutan',
        'aktif',
    ];

    protected function casts(): array
    {
        return [
            'urutan' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
