<?php

namespace App\Models;

use App\Enums\PayrollItemJenis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bonus / potongan lain yang diinput admin pada sebuah gaji (revisi 17 Nov
 * 2026 poin 29), mis. "Potongan (ganti pipa) Rp240.000" atau "Bonus (Game 5)
 * 4 Nov". Bertahan saat gaji dihitung ulang; dibangun menjadi payroll_run_items.
 */
class PayrollRunAdjustment extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'jenis',
        'keterangan',
        'tanggal',
        'nominal',
        'catatan',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => PayrollItemJenis::class,
            'tanggal' => 'date:Y-m-d',
            'nominal' => 'decimal:2',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
