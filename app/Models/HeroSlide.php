<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Slide carousel landing (B38) — grup Website → Hero Slider.
 */
class HeroSlide extends Model
{
    use HasFactory;

    protected $fillable = [
        'judul',
        'subjudul',
        'gambar',
        'tombol_teks',
        'tombol_url',
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
}
