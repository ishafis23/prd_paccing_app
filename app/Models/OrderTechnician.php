<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keanggotaan tim pengerjaan order (B21): berisi seluruh teknisi yang
 * ditugaskan pada suatu order, termasuk PIC (orders.teknisi_id).
 */
class OrderTechnician extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'teknisi_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }
}
