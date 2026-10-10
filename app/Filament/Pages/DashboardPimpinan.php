<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\DashboardPimpinanService;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Livewire\Attributes\Url;

/**
 * "Dashboard Pimpinan" — ringkasan lintas aspek (Customer, Keuangan,
 * Performa) khusus owner/admin. Read-only; angka diambil dari
 * DashboardPimpinanService.
 */
class DashboardPimpinan extends Page
{
    protected static string $view = 'filament.pages.dashboard-pimpinan';

    protected static ?string $navigationGroup = 'Laporan';

    protected static ?string $navigationLabel = 'Dashboard Pimpinan';

    protected static ?string $title = 'Dashboard Pimpinan';

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    protected static ?string $slug = 'dashboard-pimpinan';

    protected static ?int $navigationSort = -1;

    /** Bulan terpilih (Y-m). */
    #[Url]
    public string $bulan = '';

    /** Tab aspek aktif: customer | keuangan | performa. */
    #[Url]
    public string $tab = 'customer';

    /** Sub-tab Aspek Keuangan: pendapatan | pengeluaran | laba_rugi | neraca | arus_kas. */
    #[Url]
    public string $keuangan = 'pendapatan';

    /** Sub-tab Aspek Performa: teknisi | kehadiran | klasifikasi. */
    #[Url]
    public string $performa = 'teknisi';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            $this->bulan = now()->format('Y-m');
        }

        if (! in_array($this->tab, ['customer', 'keuangan', 'performa'], true)) {
            $this->tab = 'customer';
        }

        if (! in_array($this->keuangan, ['pendapatan', 'pengeluaran', 'laba_rugi', 'neraca', 'arus_kas'], true)) {
            $this->keuangan = 'pendapatan';
        }

        if (! in_array($this->performa, ['teknisi', 'kehadiran', 'klasifikasi'], true)) {
            $this->performa = 'teknisi';
        }
    }

    public function updatedBulan(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            $this->bulan = now()->format('Y-m');
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['customer', 'keuangan', 'performa'], true)) {
            $this->tab = $tab;
        }
    }

    public function setKeuangan(string $sub): void
    {
        if (in_array($sub, ['pendapatan', 'pengeluaran', 'laba_rugi', 'neraca', 'arus_kas'], true)) {
            $this->keuangan = $sub;
        }
    }

    public function setPerforma(string $sub): void
    {
        if (in_array($sub, ['teknisi', 'kehadiran', 'klasifikasi'], true)) {
            $this->performa = $sub;
        }
    }

    protected function getViewData(): array
    {
        $service = app(DashboardPimpinanService::class);
        $bulan = CarbonImmutable::createFromFormat('!Y-m', $this->bulan);
        $awal = $bulan->startOfMonth();
        $akhir = $bulan->endOfMonth();

        return [
            'bulanLabel' => $bulan->translatedFormat('F Y'),
            'customer' => $service->ringkasanCustomer($awal, $akhir),
            'unit' => $service->unitPerCustomer(),
            'followUp' => $service->followUp($awal, $akhir),
            'keuanganRingkas' => $service->keuangan($awal, $akhir),
            'pendapatanHarian' => $service->pendapatanHarian($awal, $akhir),
            'pendapatanPekanan' => $service->pendapatanPekanan($awal, $akhir),
            'pengeluaranHarian' => $service->pengeluaranHarian($awal, $akhir),
            'pengeluaranPekanan' => $service->pengeluaranPekanan($awal, $akhir),
            'labaRugiHarian' => $service->labaRugiHarian($awal, $akhir),
            'labaRugiPekanan' => $service->labaRugiPekanan($awal, $akhir),
            'neraca' => $service->neraca(),
            'arusKas' => $service->arusKas($awal, $akhir),
            'performaTeknisi' => $service->performaTeknisi($awal, $akhir),
            'kehadiran' => $service->kehadiranTeknisi($awal, $akhir),
            'klasifikasi' => $service->klasifikasiPengerjaan($awal, $akhir),
        ];
    }
}
