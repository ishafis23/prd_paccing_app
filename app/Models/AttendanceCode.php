<?php

namespace App\Models;

use App\Enums\AttendanceCodeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kode/QR absensi kantor (dev-plan/15, B39-B41). Hanya 1 baris `aktif`
 * berlaku di satu waktu — dijaga oleh AttendanceCodeService.
 */
class AttendanceCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode',
        'status',
        'lokasi',
        'berlaku_sampai',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'status' => AttendanceCodeStatus::class,
            'berlaku_sampai' => 'datetime',
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
