<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Keterangan ringkas satu UNIT yang dikerjakan pada sebuah order (dev-plan/21
 * §5, Fase 4): lokasi ("CK mana"), posisi, suhu/RPM, kondisi. Dibuat/di-prefill
 * oleh App\Services\UnitReportService::siapkan(); relasi didefinisikan dari
 * sini (bukan di Order) — daftar unit per order lewat
 * `OrderUnitReport::where('order_id', …)`.
 */
class OrderUnitReport extends Model
{
    public const KONDISI_NORMAL = 'normal';

    public const KONDISI_TIDAK_NORMAL = 'tidak_normal';

    public const BAGIAN = ['indoor' => 'Indoor', 'outdoor' => 'Outdoor'];

    public const KONDISI = [
        self::KONDISI_NORMAL => 'Normal',
        self::KONDISI_TIDAK_NORMAL => 'Tidak normal',
    ];

    protected $fillable = [
        'order_id',
        'order_item_id',
        'unit_no',
        'customer_ac_unit_id',
        'lokasi_label',
        'posisi',
        'jenis_pekerjaan',
        'suhu',
        'rpm',
        'kondisi',
        'catatan_kondisi',
        'bagian',
    ];

    protected function casts(): array
    {
        return [
            'unit_no' => 'integer',
            'suhu' => 'decimal:1',
            'rpm' => 'decimal:1',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function acUnit(): BelongsTo
    {
        return $this->belongsTo(CustomerAcUnit::class, 'customer_ac_unit_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(WorkReportPhoto::class);
    }

    public function sudahDiisi(): bool
    {
        return filled($this->kondisi);
    }
}
