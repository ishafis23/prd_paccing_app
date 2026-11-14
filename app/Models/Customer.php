<?php

namespace App\Models;

use App\Enums\CustomerArea;
use App\Enums\CustomerJenis;
use App\Enums\CustomerStatus;
use App\Enums\CustomerTipe;
use App\Enums\LeadSource;
use App\Enums\OrderStatus;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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
        'username',
        'alamat',
        'latitude',
        'longitude',
        'area',
        'sumber_lead',
        'status',
        'tipe_customer',
        'tipe_diubah_at',
        'tipe_diubah_oleh',
        'catatan',
        'wa_group_id',
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
            'tipe_customer' => CustomerTipe::class,
            'tipe_diubah_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Order terakhir yang benar-benar dikerjakan (selesai) — dasar CRM follow-up.
     */
    public function orderTerakhir(): HasOne
    {
        return $this->hasOne(Order::class)
            ->where('status', OrderStatus::Selesai->value)
            ->latestOfMany('tanggal_jadwal');
    }

    /** Reminder servis terbaru customer (satu customer bisa punya beberapa riwayat). */
    public function reminderTerbaru(): HasOne
    {
        return $this->hasOne(ServiceReminder::class)->latestOfMany();
    }

    public function isLama(): bool
    {
        return $this->tipe_customer === CustomerTipe::Lama;
    }

    /** Tandai customer baru/lama (manual oleh admin) + catat siapa & kapan. */
    public function tandaiTipe(CustomerTipe $tipe, ?User $oleh = null): void
    {
        $this->forceFill([
            'tipe_customer' => $tipe->value,
            'tipe_diubah_at' => now(),
            'tipe_diubah_oleh' => $oleh?->id,
        ])->save();
    }

    /** Alamat WA wa.me dari no_hp (62xxx), null bila kosong. */
    public function waUrl(): ?string
    {
        $nomor = preg_replace('/\D+/', '', (string) $this->no_hp);

        if ($nomor === '') {
            return null;
        }

        if (str_starts_with($nomor, '0')) {
            $nomor = '62'.substr($nomor, 1);
        }

        return 'https://wa.me/'.$nomor;
    }

    /**
     * Portal aktif utk customer ini (dev-plan/12 §3.6, dev-plan/22 Plan A)
     * kalau admin sudah mengatur password + minimal salah satu identitas
     * login (username atau email) lewat CustomerPortalService.
     */
    public function bisaLoginPortal(): bool
    {
        return filled($this->password) && (filled($this->username) || filled($this->email));
    }

    /**
     * Portal Corporate (dev-plan/22 §9-7) hanya untuk customer jenis
     * `company`. Customer perorangan tetap memakai resi publik.
     */
    public function isCompany(): bool
    {
        return $this->jenis === CustomerJenis::Company;
    }

    /**
     * Identitas login yang dipakai PIC (username bila ada, jika tidak email).
     */
    public function identitasPortal(): ?string
    {
        return $this->username ?: $this->email;
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
