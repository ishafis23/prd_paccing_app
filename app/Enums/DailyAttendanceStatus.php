<?php

namespace App\Enums;

/**
 * Status absen datang (dev-plan/15, B47): hasil pencocokan jam_datang ke
 * `attendance_settings`.
 */
enum DailyAttendanceStatus: string
{
    case Bonus = 'bonus';
    case Normal = 'normal';
    case Telat = 'telat';
    case TelatToleransi = 'telat_toleransi';
}
