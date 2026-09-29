<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyReport extends Model
{
    protected $fillable = [
        'teknisi_id',
        'tanggal_laporan',
        'status',
    ];

    protected $casts = [
        'tanggal_laporan' => 'date',
    ];

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }

    public function workEntries(): HasMany
    {
        return $this->hasMany(DailyWorkEntry::class);
    }

    public function financialEntry(): HasOne
    {
        return $this->hasOne(DailyFinancialEntry::class);
    }

    public function adHocAcUnits(): HasMany
    {
        return $this->hasMany(AdHocAcUnit::class);
    }
}
