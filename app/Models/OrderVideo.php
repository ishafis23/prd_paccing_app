<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dokumentasi video pengerjaan order (revisi 14 Nov 2026 poin 13). */
class OrderVideo extends Model
{
    protected $fillable = ['order_id', 'user_id', 'path', 'nama_asli', 'mime', 'ukuran', 'keterangan'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function url(): string
    {
        return asset('storage/'.ltrim($this->path, '/'));
    }
}
