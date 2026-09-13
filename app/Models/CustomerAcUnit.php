<?php

namespace App\Models;

use App\Enums\UnitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unit AC fisik milik satu Customer (khususnya company dengan banyak
 * unit, mis. sekolah/kantor) — satu baris = satu unit AC, bukan agregat
 * jumlah. Dasar untuk histori pencucian per unit & portal corporate
 * (lihat dev-plan/12-analisis-chat-13sep-dan-roadmap.md §3.10).
 */
class CustomerAcUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'kode_unit',
        'kode_ruangan',
        'jenis_unit',
        'pk',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'jenis_unit' => UnitType::class,
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Label ringkas utk dropdown/tampilan (dev-plan/12 §3.10 lanjutan) —
     * mis. "AC-01 — Ruang Guru (1 PK)".
     */
    public function labelTampil(): string
    {
        $label = trim($this->kode_unit.' — '.$this->kode_ruangan);

        return filled($this->pk) ? "{$label} ({$this->pk})" : $label;
    }
}
