<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan seksi landing (B38) — baris tunggal, dikelola lewat
 * menu Website → Pengaturan Beranda.
 */
class BerandaSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'maps_embed',
        'jam_operasional',
        'sosmed_instagram',
        'sosmed_facebook',
        'tampil_layanan',
        'tampil_cara_kerja',
        'tampil_area',
        'tampil_peta',
    ];

    protected function casts(): array
    {
        return [
            'tampil_layanan' => 'boolean',
            'tampil_cara_kerja' => 'boolean',
            'tampil_area' => 'boolean',
            'tampil_peta' => 'boolean',
        ];
    }
}
