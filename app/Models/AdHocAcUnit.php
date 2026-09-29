<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdHocAcUnit extends Model
{
    protected $fillable = [
        'daily_report_id',
        'work_entry_id',
        'customer_name',
        'jumlah_unit',
        'status',
        'catatan',
    ];

    protected $casts = [
        'jumlah_unit' => 'integer',
    ];

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    public function workEntry(): BelongsTo
    {
        return $this->belongsTo(DailyWorkEntry::class, 'work_entry_id');
    }
}
