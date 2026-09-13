<?php

namespace App\Models;

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
        'nama_layanan',
        'kategori',
        'harga',
        'jumlah',
        'catatan',
        'ditambahkan_oleh',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => ServiceType::class,
            'harga' => 'decimal:2',
            'jumlah' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
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
     * Foto laporan yg terkait ke baris layanan ini (dev-plan/13 §3).
     */
    public function photos(): HasMany
    {
        return $this->hasMany(WorkReportPhoto::class);
    }
}
