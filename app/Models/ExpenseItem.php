<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseItem extends Model
{
    protected $fillable = [
        'daily_financial_entry_id',
        'kategori',
        'nominal',
        'keterangan',
    ];

    protected $casts = [
        'nominal' => 'integer',
    ];

    public function dailyFinancialEntry(): BelongsTo
    {
        return $this->belongsTo(DailyFinancialEntry::class);
    }
}
