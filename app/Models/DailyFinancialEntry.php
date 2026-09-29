<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyFinancialEntry extends Model
{
    protected $fillable = [
        'daily_report_id',
        'saldo_awal_dari_base',
        'pendapatan_dari_customer',
        'total_pengeluaran',
        'jumlah_setoran',
    ];

    protected $casts = [
        'saldo_awal_dari_base' => 'integer',
        'pendapatan_dari_customer' => 'integer',
        'total_pengeluaran' => 'integer',
        'jumlah_setoran' => 'integer',
    ];

    public function dailyReport(): BelongsTo
    {
        return $this->belongsTo(DailyReport::class);
    }

    public function expenseItems(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    public function getTotalPendapatanAttribute(): int
    {
        return ($this->saldo_awal_dari_base ?? 0) + ($this->pendapatan_dari_customer ?? 0);
    }

    public function getTotalPengeluaranAttribute(): int
    {
        return $this->expenseItems()->sum('nominal') ?? 0;
    }

    public function getJumlahSetoran(): int
    {
        return $this->getTotalPendapatanAttribute() - $this->getTotalPengeluaranAttribute();
    }
}
