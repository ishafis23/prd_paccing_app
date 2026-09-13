<?php

namespace App\Models;

use App\Enums\CustomerJenis;
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
        'customer_ac_unit_id',
        'team_id',
        'teknisi_id',
        'jumlah_unit',
        'alamat_pengerjaan',
        'jenis_pelanggan',
        'tanggal_jadwal',
        'jam_jadwal',
        'status',
        'metode_dipilih',
        'bukti_pembayaran',
        'resi_token',
        'surat_jalan_token',
        'ditutup_pada',
        'catatan_admin',
        'alasan_kendala',
        'perbaikan_menunggu_konfirmasi',
        'perbaikan_catatan',
        'perbaikan_estimasi_harga',
        'perbaikan_dilaporkan_oleh',
        'perbaikan_dilaporkan_pada',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'jenis_pelanggan' => CustomerJenis::class,
            'metode_dipilih' => PaymentMethod::class,
            'jumlah_unit' => 'integer',
            'tanggal_jadwal' => 'date:Y-m-d',
            'ditutup_pada' => 'datetime',
            'perbaikan_menunggu_konfirmasi' => 'boolean',
            'perbaikan_estimasi_harga' => 'decimal:2',
            'perbaikan_dilaporkan_pada' => 'datetime',
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

    /**
     * Unit AC utk baris order_item pertama (dev-plan/12 §3.10 lanjutan) —
     * sama pola dgn service_catalog_id/jumlah_unit. Nullable/opsional;
     * customer tanpa data Unit AC terdaftar tetap bisa order spt biasa.
     */
    public function acUnit(): BelongsTo
    {
        return $this->belongsTo(CustomerAcUnit::class, 'customer_ac_unit_id');
    }

    /**
     * Tim baku yg dipakai saat assign (dev-plan/12 §3.13) — jejak
     * traceability saja, bukan sumber kebenaran anggota (lihat
     * `orderTechnicians()`/`timTeknisi()`).
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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

    /**
     * Teknisi yang lapor "Ada Perbaikan" (dev-plan/13 §2) — hanya terisi
     * selagi perbaikan_menunggu_konfirmasi true.
     */
    public function pelaporPerbaikan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'perbaikan_dilaporkan_oleh');
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

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * Baris layanan order (dev-plan/13) — baris pertama dibuat otomatis
     * saat order dibuat (lihat booted()), baris tambahan (mis. sparepart
     * hasil "Ada Perbaikan") lewat OrderService::tambahLayanan().
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Total tagihan = jumlah seluruh order_items. Fallback ke
     * harga-katalog-lama kalau entah kenapa order belum punya order_items
     * sama sekali (mestinya tidak terjadi berkat booted(), tapi dijaga
     * agar data lama sebelum migrasi backfill tetap tampil benar).
     */
    public function total(): float
    {
        $items = $this->orderItems;

        if ($items->isNotEmpty()) {
            return (float) $items->sum(fn (OrderItem $i) => (float) $i->harga * $i->jumlah);
        }

        $price = $this->serviceCatalog?->harga ?? 0;

        return (float) $price * (int) $this->jumlah_unit;
    }

    /**
     * Baris order_items pertama dibuat otomatis dari service_catalog_id/
     * jumlah_unit saat order dibuat — supaya Order::total() konsisten
     * lintas semua jalur pembuatan order (OrderService, factory, seeder)
     * tanpa perlu tiap caller ingat bikin order_items manual.
     */
    protected static function booted(): void
    {
        static::created(function (Order $order): void {
            if ($order->orderItems()->exists()) {
                return;
            }

            $catalog = $order->serviceCatalog;

            $order->orderItems()->create([
                'service_catalog_id' => $catalog?->id,
                'customer_ac_unit_id' => $order->customer_ac_unit_id,
                'nama_layanan' => $catalog !== null ? str($catalog->jenis_layanan->value)->headline()->toString() : 'Layanan',
                'kategori' => $catalog?->jenis_layanan,
                'harga' => $catalog?->harga ?? 0,
                'jumlah' => $order->jumlah_unit ?? 1,
            ]);
        });
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
     * Pastikan order punya token Surat Jalan publik (dev-plan/12 §3.12) —
     * dibuat sekali (idempotent), dipakai admin sebelum tim berangkat.
     */
    public function pastikanSuratJalanToken(): string
    {
        if ($this->surat_jalan_token === null) {
            $this->surat_jalan_token = Str::random(40);
            $this->save();
        }

        return $this->surat_jalan_token;
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
