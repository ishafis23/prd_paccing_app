<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Alamat milik satu Customer (dev-plan/14) — 1 customer bisa punya banyak
 * alamat (rumah 1, rumah 2, usaha, dst). Setiap alamat punya koordinat
 * sendiri (maps/lat/lng) karena teknisi menuju alamat terpilih saat order.
 * Unit AC (`CustomerAcUnit`) kini menunjuk ke alamat, bukan langsung customer.
 */
class CustomerAddress extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'nama_lokasi',
        'alamat',
        'maps_link',
        'latitude',
        'longitude',
        'is_utama',
        'catatan',
    ];

    protected function casts(): array
    {
        return [
            'is_utama' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Unit AC yang berada di alamat ini (dev-plan/14) — prinsipnya
     * "1 alamat punya daftar unit sendiri".
     */
    public function acUnits(): HasMany
    {
        return $this->hasMany(CustomerAcUnit::class);
    }

    /**
     * Order yang alamat pengerjaannya adalah alamat ini.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Label ringkas utk dropdown/tampilan — mis. "Rumah 1 — Jl. Mawar 4".
     */
    public function labelTampil(): string
    {
        $label = filled($this->nama_lokasi)
            ? $this->nama_lokasi.' — '.$this->alamat
            : $this->alamat;

        return $label;
    }

    /**
     * Alamat PERTAMA yang dibuat customer otomatis jadi utama (dev-plan/14)
     * — alamat berikutnya TIDAK otomatis menggantikan (harus lewat tombol
     * "Jadikan Utama" di tab Alamat).
     */
    protected static function booted(): void
    {
        static::created(function (CustomerAddress $alamat): void {
            $sudahAdaUtama = static::where('customer_id', $alamat->customer_id)
                ->where('id', '!=', $alamat->id)
                ->where('is_utama', true)
                ->exists();

            if (! $sudahAdaUtama) {
                $alamat->forceFill(['is_utama' => true])->saveQuietly();
            }
        });
    }
}
