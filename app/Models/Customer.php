<?php

namespace App\Models;

use App\Enums\CustomerArea;
use App\Enums\CustomerJenis;
use App\Enums\CustomerStatus;
use App\Enums\LeadSource;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Selain data customer biasa, model ini JUGA jadi akun login Portal
 * Customer (dev-plan/12 §3.6) — keputusan 13 Sept: 1 akun per customer
 * (bukan multi-user staf), pakai `email` yg sudah ada + `password` baru.
 * Guard terpisah `customer` (lihat config/auth.php), tidak bisa login ke
 * panel Admin/portal Teknisi. `password` null = portal belum diaktifkan
 * utk customer ini (lihat `CustomerPortalService::aturPassword()`).
 */
class Customer extends Model implements AuthenticatableContract
{
    use Authenticatable, HasFactory, SoftDeletes;

    protected $fillable = [
        'nama',
        'jenis',
        'no_hp',
        'email',
        'alamat',
        'latitude',
        'longitude',
        'area',
        'sumber_lead',
        'status',
        'catatan',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => CustomerJenis::class,
            'area' => CustomerArea::class,
            'sumber_lead' => LeadSource::class,
            'status' => CustomerStatus::class,
            'password' => 'hashed',
        ];
    }

    /**
     * Portal aktif utk customer ini (dev-plan/12 §3.6) kalau admin sudah
     * mengatur password lewat CustomerPortalService::aturPassword().
     */
    public function bisaLoginPortal(): bool
    {
        return filled($this->email) && filled($this->password);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function serviceReminders(): HasMany
    {
        return $this->hasMany(ServiceReminder::class);
    }

    public function acUnits(): HasMany
    {
        return $this->hasMany(CustomerAcUnit::class);
    }

    /**
     * Semua alamat customer (dev-plan/14) — sumber kebenaran alamat.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    /**
     * Alamat utama (yang ditandai is_utama) — fallback ke alamat pertama
     * kalau belum ada yg ditandai. Dipakai sbg default saat buat order.
     */
    public function alamatUtama(): ?CustomerAddress
    {
        return $this->addresses()
            ->orderByDesc('is_utama')
            ->orderBy('id')
            ->first();
    }

    /**
     * Alamat pertama (fallback bila belum ada yg is_utama).
     */
    public function alamatPertama(): ?CustomerAddress
    {
        return $this->addresses()->orderBy('id')->first();
    }

    public function scopeActive($query)
    {
        return $query->where('status', CustomerStatus::Aktif->value);
    }
}
