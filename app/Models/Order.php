<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'service_catalog_id',
        'teknisi_id',
        'jumlah_unit',
        'alamat_pengerjaan',
        'tanggal_jadwal',
        'jam_jadwal',
        'status',
        'metode_dipilih',
        'resi_token',
        'ditutup_pada',
        'catatan_admin',
        'alasan_kendala',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'metode_dipilih' => PaymentMethod::class,
            'jumlah_unit' => 'integer',
            'tanggal_jadwal' => 'date:Y-m-d',
            'ditutup_pada' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceCatalog(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class);
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    /**
     * Baris keanggotaan tim (B21) — berisi seluruh teknisi termasuk PIC.
     */
    public function orderTechnicians(): HasMany
    {
        return $this->hasMany(OrderTechnician::class);
    }

    /**
     * Seluruh teknisi dalam tim pengerjaan order (termasuk PIC).
     */
    public function timTeknisi(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'order_technicians', 'order_id', 'teknisi_id')
            ->withTimestamps();
    }

    /**
     * Apakah user merupakan anggota tim pengerjaan (PIC atau tercatat di
     * order_technicians). Legacy order tanpa baris tim tetap dikenali via PIC.
     */
    public function diassignkanKe(User $user): bool
    {
        if ((int) $this->teknisi_id === (int) $user->id) {
            return true;
        }

        return $this->orderTechnicians()
            ->where('teknisi_id', $user->id)
            ->exists();
    }

    /**
     * Order yang ditugaskan ke seorang teknisi (PIC atau anggota tim).
     */
    public function scopeUntukTeknisi(Builder $query, int $teknisiId): Builder
    {
        return $query->where(function (Builder $q) use ($teknisiId): void {
            $q->where('teknisi_id', $teknisiId)
                ->orWhereHas('orderTechnicians', fn (Builder $t) => $t->where('teknisi_id', $teknisiId));
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function workReports(): HasMany
    {
        return $this->hasMany(WorkReport::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(Payment::class)->latestOfMany();
    }

    public function serviceReminder(): HasOne
    {
        return $this->hasOne(ServiceReminder::class)->latestOfMany();
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function total(): float
    {
        $price = $this->serviceCatalog?->harga ?? 0;

        return (float) $price * (int) $this->jumlah_unit;
    }

    /**
     * Pastikan order punya token resi publik (B14a). Dibuat saat order
     * berstatus `selesai`; aman dipanggil berulang (idempotent).
     */
    public function pastikanResiToken(): string
    {
        if ($this->resi_token === null) {
            $this->resi_token = Str::random(40);
            $this->save();
        }

        return $this->resi_token;
    }

    /**
     * Order sudah ditutup teknisi lewat slider "Selesaikan Order" (B32):
     * metode pembayaran terkunci & tidak bisa diubah lagi.
     */
    public function sudahDitutup(): bool
    {
        return $this->ditutup_pada !== null;
    }
}
