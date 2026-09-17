<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Cuci/perawatan motor (dev-plan/15, Games 3 — B49) — berdiri sendiri
 * dari Order/Customer, maks 2 teknisi per baris.
 */
class MotorCleaning extends Model
{
    use HasFactory;

    protected $fillable = [
        'tanggal',
        'foto',
        'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
        ];
    }

    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function teknisis(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'motor_cleaning_technicians', 'motor_cleaning_id', 'teknisi_id')
            ->withTimestamps();
    }
}
