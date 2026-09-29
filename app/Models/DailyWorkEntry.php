<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyWorkEntry extends Model
{
    protected $fillable = [
        'daily_report_id',
        'job_id',
        'titik',
        'customer_name',
        'notes',
        'urutan',
    ];

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }

    public function adHocAcUnits(): HasMany
    {
        return $this->hasMany(AdHocAcUnit::class, 'work_entry_id');
    }
}
