<?php

namespace App\Models;

use App\Enums\PayrollStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu hitungan gaji teknisi per periode (dev-plan/hrd/02).
 */
class PayrollRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'periode_mulai',
        'periode_selesai',
        'gaji_pokok',
        'bonus_bulanan',
        'bonus_umum',
        'bonus_mingguan',
        'jumlah_hari_telat',
        'total_denda_telat',
        'jumlah_hari_off',
        'total_potongan_off',
        'jumlah_setengah_hari',
        'total_potongan_setengah_hari',
        'potongan_bpjs_tk',
        'gaji_bersih',
        'status',
        'catatan',
        'dihitung_oleh',
        'disetujui_oleh',
        'disetujui_pada',
        'dibayar_pada',
    ];

    protected function casts(): array
    {
        return [
            'periode_mulai' => 'date:Y-m-d',
            'periode_selesai' => 'date:Y-m-d',
            'status' => PayrollStatus::class,
            'gaji_pokok' => 'decimal:2',
            'bonus_bulanan' => 'decimal:2',
            'bonus_umum' => 'decimal:2',
            'bonus_mingguan' => 'decimal:2',
            'total_denda_telat' => 'decimal:2',
            'total_potongan_off' => 'decimal:2',
            'total_potongan_setengah_hari' => 'decimal:2',
            'potongan_bpjs_tk' => 'decimal:2',
            'gaji_bersih' => 'decimal:2',
            'disetujui_pada' => 'datetime',
            'dibayar_pada' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class)->orderBy('urutan')->orderBy('id');
    }

    public function dihitungOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dihitung_oleh');
    }

    public function disetujuiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function draft(): bool
    {
        return $this->status === PayrollStatus::Draft;
    }

    /** Slip boleh dilihat teknisi setelah di-ACC. */
    public function slipAktif(): bool
    {
        return in_array($this->status, [PayrollStatus::Disetujui, PayrollStatus::Dibayar], true);
    }
}
