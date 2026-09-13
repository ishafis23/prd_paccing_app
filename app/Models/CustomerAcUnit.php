<?php

namespace App\Models;

use App\Enums\UnitType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
     * Riwayat pengerjaan (baris order_items) yg pernah menautkan unit ini
     * — dasar histori pencucian per unit (dev-plan/12 §3.6).
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'customer_ac_unit_id');
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
