<?php

namespace App\Models;

use App\Enums\AdvanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kasbon teknisi (dev-plan/28) — dipotong dari gaji per periode.
 */
class TechnicianAdvance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tanggal',
        'nominal',
        'cicilan_per_periode',
        'keterangan',
        'status',
        'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date:Y-m-d',
            'nominal' => 'decimal:2',
            'cicilan_per_periode' => 'decimal:2',
            'status' => AdvanceStatus::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dicatatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh');
    }

    public function pelunasan(): HasMany
    {
        return $this->hasMany(TechnicianAdvanceRepayment::class);
    }

    public function terbayar(): float
    {
        return (float) $this->pelunasan()->sum('nominal');
    }

    public function sisa(): float
    {
        return max(0.0, (float) $this->nominal - $this->terbayar());
    }
}
