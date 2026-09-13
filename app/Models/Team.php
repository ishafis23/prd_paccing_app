<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tim Teknisi permanen (dev-plan/12 §3.13) — mis. 1 tim = 2 teknisi,
 * dipilih sekali lalu dipakai berulang lewat aksi "Assign Tim" di Order
 * (beda dari `order_technicians` yg ad-hoc per order).
 */
class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'aktif',
        'pic_teknisi_id',
    ];

    protected function casts(): array
    {
        return [
            'aktif' => 'boolean',
        ];
    }

    /**
     * Seluruh anggota tim (termasuk PIC).
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members', 'team_id', 'teknisi_id')
            ->withTimestamps();
    }

    public function picTeknisi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_teknisi_id');
    }

    /**
     * Order yg pernah di-assign lewat tim ini (jejak traceability saja).
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
