<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Permintaan Laporan Bulanan per Customer (dev-plan/21 §6). Lihat
 * App\Services\LaporanBulananService untuk alur status.
 */
class LaporanBulanan extends Model
{
    public const MENUNGGU = 'menunggu';

    public const DIPROSES = 'diproses';

    public const SELESAI = 'selesai';

    public const GAGAL = 'gagal';

    public const STATUS = [
        self::MENUNGGU => 'Menunggu',
        self::DIPROSES => 'Diproses',
        self::SELESAI => 'Selesai',
        self::GAGAL => 'Gagal',
    ];

    protected $table = 'laporan_bulanan';

    protected $fillable = [
        'customer_id',
        'bulan',
        'customer_address_id',
        'path',
        'status',
        'pesan_error',
        'jumlah_order',
        'ukuran_bytes',
        'dibuat_oleh',
        'selesai_pada',
    ];

    protected function casts(): array
    {
        return [
            'jumlah_order' => 'integer',
            'ukuran_bytes' => 'integer',
            'selesai_pada' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function alamat(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'customer_address_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function selesai(): bool
    {
        return $this->status === self::SELESAI && filled($this->path);
    }

    public function masihBerjalan(): bool
    {
        return in_array($this->status, [self::MENUNGGU, self::DIPROSES], true);
    }
}
