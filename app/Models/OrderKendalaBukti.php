<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bukti kendala order (revisi 14 Nov 2026 poin 17): foto/screenshot bahwa kendala
 * (dari sisi customer atau teknisi) sudah disampaikan ke customer.
 */
class OrderKendalaBukti extends Model
{
    public const PIHAK = ['customer', 'teknisi'];

    protected $fillable = ['order_id', 'user_id', 'jenis', 'pihak', 'alasan', 'foto_path'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fotoUrl(): ?string
    {
        return filled($this->foto_path) ? asset('storage/'.ltrim($this->foto_path, '/')) : null;
    }
}
