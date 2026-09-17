<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\AttendanceSettingService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * Menu Absensi & Insentif → "Pengaturan Absensi" (dev-plan/15, B42): seluruh
 * ambang jam & nominal skema "Games 1-6" + denda telat + toleransi lembur.
 * Owner/Admin/HR mengelola.
 */
class PengaturanAbsensi extends Page
{
    protected static string $view = 'filament.pages.pengaturan-absensi';

    protected static ?string $navigationGroup = 'Absensi & Insentif';

    protected static ?string $navigationLabel = 'Pengaturan Absensi';

    protected static ?string $title = 'Pengaturan Absensi';

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $slug = 'pengaturan-absensi';

    public string $jamGames1Batas = '';

    public string $nominalGames1 = '';

    public string $jamNormalSelesai = '';

    public string $nominalDendaTelat = '';

    public string $jamToleransiLemburMulai = '';

    public string $jamToleransiBatasDenda = '';

    public string $jamGames2Batas = '';

    public string $nominalGames2 = '';

    public string $nominalGames3 = '';

    public string $jamGames4Batas = '';

    public string $minimalTitikBerdua = '';

    public string $minimalTitikSendiri = '';

    public string $nominalGames4Berdua = '';

    public string $nominalGames4Sendiri = '';

    public string $omsetGames5Minimal = '';

    public string $nominalGames5Berdua = '';

    public string $nominalGames5Sendiri = '';

    public string $unitGames6Berdua = '';

    public string $unitGames6Sendiri = '';

    public string $nominalGames6 = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value, RoleName::Hr->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $setting = app(AttendanceSettingService::class)->data();

        $this->jamGames1Batas = substr((string) $setting->jam_games1_batas, 0, 5);
        $this->nominalGames1 = (string) $setting->nominal_games1;
        $this->jamNormalSelesai = substr((string) $setting->jam_normal_selesai, 0, 5);
        $this->nominalDendaTelat = (string) $setting->nominal_denda_telat;
        $this->jamToleransiLemburMulai = substr((string) $setting->jam_toleransi_lembur_mulai, 0, 5);
        $this->jamToleransiBatasDenda = substr((string) $setting->jam_toleransi_batas_denda, 0, 5);
        $this->jamGames2Batas = substr((string) $setting->jam_games2_batas, 0, 5);
        $this->nominalGames2 = (string) $setting->nominal_games2;
        $this->nominalGames3 = (string) $setting->nominal_games3;
        $this->jamGames4Batas = substr((string) $setting->jam_games4_batas, 0, 5);
        $this->minimalTitikBerdua = (string) $setting->minimal_titik_berdua;
        $this->minimalTitikSendiri = (string) $setting->minimal_titik_sendiri;
        $this->nominalGames4Berdua = (string) $setting->nominal_games4_berdua;
        $this->nominalGames4Sendiri = (string) $setting->nominal_games4_sendiri;
        $this->omsetGames5Minimal = (string) $setting->omset_games5_minimal;
        $this->nominalGames5Berdua = (string) $setting->nominal_games5_berdua;
        $this->nominalGames5Sendiri = (string) $setting->nominal_games5_sendiri;
        $this->unitGames6Berdua = (string) $setting->unit_games6_berdua;
        $this->unitGames6Sendiri = (string) $setting->unit_games6_sendiri;
        $this->nominalGames6 = (string) $setting->nominal_games6;
    }

    public function simpan(): void
    {
        $this->validate([
            'jamGames1Batas' => ['required', 'date_format:H:i'],
            'nominalGames1' => ['required', 'numeric', 'min:0'],
            'jamNormalSelesai' => ['required', 'date_format:H:i'],
            'nominalDendaTelat' => ['required', 'numeric', 'min:0'],
            'jamToleransiLemburMulai' => ['required', 'date_format:H:i'],
            'jamToleransiBatasDenda' => ['required', 'date_format:H:i'],
            'jamGames2Batas' => ['required', 'date_format:H:i'],
            'nominalGames2' => ['required', 'numeric', 'min:0'],
            'nominalGames3' => ['required', 'numeric', 'min:0'],
            'jamGames4Batas' => ['required', 'date_format:H:i'],
            'minimalTitikBerdua' => ['required', 'integer', 'min:1'],
            'minimalTitikSendiri' => ['required', 'integer', 'min:1'],
            'nominalGames4Berdua' => ['required', 'numeric', 'min:0'],
            'nominalGames4Sendiri' => ['required', 'numeric', 'min:0'],
            'omsetGames5Minimal' => ['required', 'numeric', 'min:0'],
            'nominalGames5Berdua' => ['required', 'numeric', 'min:0'],
            'nominalGames5Sendiri' => ['required', 'numeric', 'min:0'],
            'unitGames6Berdua' => ['required', 'integer', 'min:1'],
            'unitGames6Sendiri' => ['required', 'integer', 'min:1'],
            'nominalGames6' => ['required', 'numeric', 'min:0'],
        ]);

        app(AttendanceSettingService::class)->perbarui([
            'jam_games1_batas' => $this->jamGames1Batas,
            'nominal_games1' => $this->nominalGames1,
            'jam_normal_selesai' => $this->jamNormalSelesai,
            'nominal_denda_telat' => $this->nominalDendaTelat,
            'jam_toleransi_lembur_mulai' => $this->jamToleransiLemburMulai,
            'jam_toleransi_batas_denda' => $this->jamToleransiBatasDenda,
            'jam_games2_batas' => $this->jamGames2Batas,
            'nominal_games2' => $this->nominalGames2,
            'nominal_games3' => $this->nominalGames3,
            'jam_games4_batas' => $this->jamGames4Batas,
            'minimal_titik_berdua' => $this->minimalTitikBerdua,
            'minimal_titik_sendiri' => $this->minimalTitikSendiri,
            'nominal_games4_berdua' => $this->nominalGames4Berdua,
            'nominal_games4_sendiri' => $this->nominalGames4Sendiri,
            'omset_games5_minimal' => $this->omsetGames5Minimal,
            'nominal_games5_berdua' => $this->nominalGames5Berdua,
            'nominal_games5_sendiri' => $this->nominalGames5Sendiri,
            'unit_games6_berdua' => $this->unitGames6Berdua,
            'unit_games6_sendiri' => $this->unitGames6Sendiri,
            'nominal_games6' => $this->nominalGames6,
        ], auth()->user());

        Notification::make()
            ->title('Pengaturan absensi disimpan')
            ->body('Berlaku langsung, tanpa perlu deploy ulang.')
            ->success()
            ->send();
    }
}
