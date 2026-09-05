<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'catatan_admin',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'jumlah_unit' => 'integer',
            'tanggal_jadwal' => 'date:Y-m-d',
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
}
