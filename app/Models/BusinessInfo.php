<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Info identitas usaha (B37) — baris tunggal id=1, dikelola lewat menu
 * Manajemen → "Info Usaha". Dipakai brand admin, teknisi, landing, & resi.
 */
class BusinessInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama_usaha',
        'alamat',
        'kontak_wa',
        'email',
        'nama_pemilik',
        'logo_path',
        'diubah_oleh',
    ];

    public function pengubah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diubah_oleh');
    }
}
