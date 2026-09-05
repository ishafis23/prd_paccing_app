<?php

namespace App\Models;

use App\Enums\IncomeCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'kategori',
        'nominal',
        'tanggal',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'kategori' => IncomeCategory::class,
            'nominal' => 'decimal:2',
            'tanggal' => 'date:Y-m-d',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
