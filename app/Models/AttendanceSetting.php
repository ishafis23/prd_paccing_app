<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pengaturan absensi & skema "Games" (dev-plan/15, B42) — baris tunggal,
 * pola sama seperti `business_infos`. Diakses lewat AttendanceSettingService.
 */
class AttendanceSetting extends Model
{
    protected $fillable = [
        'jam_games1_batas',
        'nominal_games1',
        'jam_normal_selesai',
        'nominal_denda_telat',
        'jam_toleransi_lembur_mulai',
        'jam_toleransi_batas_denda',
        'jam_games2_batas',
        'nominal_games2',
        'nominal_games3',
        'jam_games4_batas',
        'minimal_titik_berdua',
        'minimal_titik_sendiri',
        'nominal_games4_berdua',
        'nominal_games4_sendiri',
        'omset_games5_minimal',
        'nominal_games5_berdua',
        'nominal_games5_sendiri',
        'unit_games6_berdua',
        'unit_games6_sendiri',
        'nominal_games6',
    ];

    protected function casts(): array
    {
        return [
            'nominal_games1' => 'decimal:2',
            'nominal_denda_telat' => 'decimal:2',
            'nominal_games2' => 'decimal:2',
            'nominal_games3' => 'decimal:2',
            'minimal_titik_berdua' => 'integer',
            'minimal_titik_sendiri' => 'integer',
            'nominal_games4_berdua' => 'decimal:2',
            'nominal_games4_sendiri' => 'decimal:2',
            'omset_games5_minimal' => 'decimal:2',
            'nominal_games5_berdua' => 'decimal:2',
            'nominal_games5_sendiri' => 'decimal:2',
            'unit_games6_berdua' => 'integer',
            'unit_games6_sendiri' => 'integer',
            'nominal_games6' => 'decimal:2',
        ];
    }
}
