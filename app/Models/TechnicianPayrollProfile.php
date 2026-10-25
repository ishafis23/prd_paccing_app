<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Info penggajian dasar tiap teknisi (dev-plan/hrd/02).
 */
class TechnicianPayrollProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tanggal_masuk',
        'cutoff_hari',
        'gaji_pokok',
        'potongan_bpjs_tk',
        'potongan_off_per_hari',
        'denda_telat_per_hari',
        'aktif',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_masuk' => 'date:Y-m-d',
            'cutoff_hari' => 'integer',
            'gaji_pokok' => 'decimal:2',
            'potongan_bpjs_tk' => 'decimal:2',
            'potongan_off_per_hari' => 'decimal:2',
            'denda_telat_per_hari' => 'decimal:2',
            'aktif' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
