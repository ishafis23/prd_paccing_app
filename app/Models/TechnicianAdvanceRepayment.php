<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Potongan kasbon yang sudah final pada satu gaji (dev-plan/28).
 */
class TechnicianAdvanceRepayment extends Model
{
    protected $fillable = [
        'technician_advance_id',
        'payroll_run_id',
        'nominal',
    ];

    protected function casts(): array
    {
        return ['nominal' => 'decimal:2'];
    }

    public function advance(): BelongsTo
    {
        return $this->belongsTo(TechnicianAdvance::class, 'technician_advance_id');
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }
}
