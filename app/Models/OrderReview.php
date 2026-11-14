<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Ulasan customer atas pekerjaan teknisi (revisi 14 Nov 2026 poin 8). */
class OrderReview extends Model
{
    protected $fillable = ['order_id', 'teknisi_id', 'rating', 'komentar'];

    protected function casts(): array
    {
        return ['rating' => 'integer'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teknisi_id');
    }
}
