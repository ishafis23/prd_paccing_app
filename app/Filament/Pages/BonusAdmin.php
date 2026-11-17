<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Models\BonusAdminSetting;
use App\Services\BonusAdminService;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * SDM/HRD → "Bonus Admin" (revisi 17 Nov 2026 poin 24): bonus Admin/HRD/Finance
 * = persen dari omset jasa periode (default 1%) dengan syarat minimal omset
 * jasa (default Rp 50.000.000). Pengaturan hanya diubah Owner; Admin/HRD/
 * Finance melihat perhitungan.
 */
class BonusAdmin extends Page
{
    protected static string $view = 'filament.pages.bonus-admin';

    protected static ?string $navigationGroup = 'SDM/HRD';

    protected static ?string $navigationLabel = 'Bonus Admin';

    protected static ?string $title = 'Bonus Admin (Admin, HRD, Finance)';

    protected static ?string $navigationIcon = 'heroicon-o-gift-top';

    protected static ?string $slug = 'bonus-admin';

    public string $dari = '';

    public string $sampai = '';

    public bool $aktif = true;

    public string $persen = '1';

    public string $minimalOmsetJasa = '50000000';

    public string $mode = BonusAdminSetting::MODE_PER_ORANG;

    /** @var array<int, string> */
    public array $peran = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole([
            RoleName::Owner->value,
            RoleName::Admin->value,
            RoleName::Hr->value,
            RoleName::Finance->value,
        ]) ?? false;
    }

    public static function bolehUbahPengaturan(): bool
    {
        return auth()->user()?->hasRole(RoleName::Owner->value) ?? false;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->dari = now()->startOfMonth()->toDateString();
        $this->sampai = now()->endOfMonth()->toDateString();

        $setting = BonusAdminSetting::saatIni();
        $this->aktif = (bool) $setting->aktif;
        $this->persen = (string) (float) $setting->persen;
        $this->minimalOmsetJasa = (string) (int) $setting->minimal_omset_jasa;
        $this->mode = $setting->mode;
        $this->peran = $setting->peranPenerima();
    }

    public function simpan(): void
    {
        abort_unless(static::bolehUbahPengaturan(), 403);

        $data = $this->validate([
            'aktif' => ['boolean'],
            'persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'minimalOmsetJasa' => ['required', 'numeric', 'min:0'],
            'mode' => ['required', 'in:'.BonusAdminSetting::MODE_PER_ORANG.','.BonusAdminSetting::MODE_DIBAGI_RATA],
            'peran' => ['required', 'array', 'min:1'],
            'peran.*' => ['in:'.implode(',', [RoleName::Admin->value, RoleName::Hr->value, RoleName::Finance->value])],
        ]);

        BonusAdminSetting::saatIni()->update([
            'aktif' => $data['aktif'],
            'persen' => $data['persen'],
            'minimal_omset_jasa' => $data['minimalOmsetJasa'],
            'mode' => $data['mode'],
            'peran' => array_values($data['peran']),
        ]);

        Notification::make()->success()->title('Pengaturan bonus admin disimpan')->send();
    }

    protected function getViewData(): array
    {
        $valid = fn (string $v): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) !== false;

        $hasil = null;
        if ($valid($this->dari) && $valid($this->sampai) && $this->dari <= $this->sampai) {
            $hasil = app(BonusAdminService::class)->hitung(
                CarbonImmutable::parse($this->dari)->startOfDay(),
                CarbonImmutable::parse($this->sampai)->endOfDay(),
                $this->settingDariForm(),
            );
        }

        return [
            'hasil' => $hasil,
            'bolehUbah' => static::bolehUbahPengaturan(),
        ];
    }

    /** Perhitungan memakai pengaturan tersimpan (bukan isian form yang belum disimpan). */
    private function settingDariForm(): BonusAdminSetting
    {
        return BonusAdminSetting::saatIni();
    }
}
