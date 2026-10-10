<?php

namespace App\Models;

use App\Enums\IncomeCategory;
use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris layanan pada Order. Baris pertama dibuat otomatis dari
 * service_catalog_id/jumlah_unit order (lihat Order::booted()); baris
 * tambahan (mis. sparepart pengganti hasil "Ada Perbaikan") ditambahkan
 * admin lewat OrderService::tambahLayanan(). Lihat dev-plan/13.
 */
class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'service_catalog_id',
        'customer_ac_unit_id',
        'nama_layanan',
        'kategori',
        'komponen',
        'penyesuaian',
        'harga',
        'jumlah',
        'dibatalkan',
        'dibatalkan_pada',
        'alasan_batal',
        'catatan',
        'ditambahkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => ServiceType::class,
            'komponen' => IncomeCategory::class,
            'penyesuaian' => 'boolean',
            'harga' => 'decimal:2',
            'jumlah' => 'integer',
            'dibatalkan' => 'boolean',
            'dibatalkan_pada' => 'datetime',
        ];
    }

    /**
     * `komponen` selalu terisi: bila pembuat baris tidak menyebutnya, turunkan
     * dari mode_omset katalog, atau dari kategori/nama baris (manual).
     */
    protected static function booted(): void
    {
        static::creating(function (OrderItem $item): void {
            if ($item->komponen !== null) {
                return;
            }

            $catalog = $item->service_catalog_id !== null ? ServiceCatalog::find($item->service_catalog_id) : null;

            $item->komponen = $catalog !== null
                ? $catalog->komponenOmset()
                : IncomeCategory::defaultUntukBaris($item->kategori, $item->nama_layanan);
        });
    }

    /**
     * Baris layanan/unit yang dibatalkan (revisi customer: 1 unit tidak jadi).
     * Tidak dihitung ke total tagihan & tidak menuntut foto.
     */
    public function dibatalkan(): bool
    {
        return (bool) $this->dibatalkan;
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    /**
     * Unit AC spesifik yg dikerjakan baris ini (dev-plan/12 §3.10 lanjutan)
     * — penting kalau customer punya lebih dari satu unit AC.
     */
    public function acUnit(): BelongsTo
    {
        return $this->belongsTo(CustomerAcUnit::class, 'customer_ac_unit_id');
    }

    public function ditambahkanOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditambahkan_oleh');
    }

    public function subtotal(): float
    {
        return (float) $this->harga * $this->jumlah;
    }

    /**
     * Baris ini jasa atau material? (kolom `komponen`, bisa diubah admin).
     */
    public function komponenOmset(): IncomeCategory
    {
        return $this->komponen ?? IncomeCategory::untukLayanan($this->kategori);
    }

    /**
     * Total omset per komponen utk sekumpulan baris (baris dibatalkan diabaikan).
     * Σ jasa + Σ material = Σ subtotal baris aktif = Order::total().
     *
     * @param  iterable<int, OrderItem>  $items
     * @return array{jasa: float, material: float}
     */
    public static function totalPerKomponen(iterable $items): array
    {
        $hasil = ['jasa' => 0.0, 'material' => 0.0];

        foreach ($items as $item) {
            if ($item->dibatalkan()) {
                continue;
            }

            $hasil[$item->komponenOmset()->value] += $item->subtotal();
        }

        return $hasil;
    }

    /**
     * Foto laporan yg terkait ke baris layanan ini (dev-plan/13 §3).
     */
    public function photos(): HasMany
    {
        return $this->hasMany(WorkReportPhoto::class);
    }
}
