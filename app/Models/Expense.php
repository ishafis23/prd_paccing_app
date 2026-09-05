<?php

namespace App\Models;

use App\Enums\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'kategori',
        'nominal',
        'tanggal',
        'keterangan',
        'bukti',
        'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => ExpenseCategory::class,
            'nominal' => 'decimal:2',
            'tanggal' => 'date:Y-m-d',
        ];
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }
}
