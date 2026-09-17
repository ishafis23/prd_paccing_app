<?php

namespace App\Models;

use App\Enums\DailyAttendanceStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Absensi kantor harian teknisi (dev-plan/15, B39) — 1 baris per
 * teknisi/tanggal. Terpisah dari `attendances` (absen per kunjungan order).
 */
class DailyAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'attendance_code_id',
        'tanggal',
        'jam_datang',
        'foto_datang',
        'status_datang',
        'jam_pulang',
        'foto_pulang',
        'dikecualikan_denda',
        'catatan_admin',
    ];

    protected function casts(): array
    {
        return [
            'status_datang' => DailyAttendanceStatus::class,
            'tanggal' => 'date:Y-m-d',
            'jam_datang' => 'datetime',
            'jam_pulang' => 'datetime',
            'dikecualikan_denda' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attendanceCode(): BelongsTo
    {
        return $this->belongsTo(AttendanceCode::class);
    }
}
