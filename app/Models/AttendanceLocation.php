<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lokasi kantor utk absensi mode GPS (dev-plan/19) — bisa lebih dari 1
 * aktif sekaligus, dikelola lewat AttendanceLocationService.
 */
class AttendanceLocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'latitude',
        'longitude',
        'radius_meter',
        'aktif',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'radius_meter' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function dibuatOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function dailyAttendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class);
    }
}
