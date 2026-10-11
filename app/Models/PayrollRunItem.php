<?php

namespace App\Models;

use App\Enums\PayrollItemJenis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baris rincian penggajian (dev-plan/hrd/02) — memecah bonus/potongan agar
 * slip menampilkan item seperti contoh.
 */
class PayrollRunItem extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'jenis',
        'kategori',
        'label',
        'nominal',
        'referensi',
        'urutan',
    ];

    protected function casts(): array
    {
        return [
            'jenis' => PayrollItemJenis::class,
            'nominal' => 'decimal:2',
            'urutan' => 'integer',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
