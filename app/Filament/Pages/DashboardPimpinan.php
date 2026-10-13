<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\DashboardPimpinanService;
use App\Services\OmsetService;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    /** Sub-tab Aspek Keuangan: pendapatan | pengeluaran | laba_rugi | neraca | arus_kas | omset. */
    #[Url]
    public string $keuangan = 'pendapatan';

    /** Sub-tab Aspek Performa: teknisi | kehadiran | klasifikasi. */
    #[Url]
    public string $performa = 'teknisi';

    /** Toggle tabel Omset Jasa/Material: false = per kategori, true = rinci per transaksi. */
    public bool $rinciOmset = false;

    /** Key baris omset yg detailnya sedang dibuka (modal), null = tertutup. */
    public ?string $detailOmsetKey = null;

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

        if (! in_array($this->keuangan, ['pendapatan', 'pengeluaran', 'laba_rugi', 'neraca', 'arus_kas', 'omset'], true)) {
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

        $this->tutupDetailOmset();
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['customer', 'keuangan', 'performa'], true)) {
            $this->tab = $tab;
        }
    }

    public function setKeuangan(string $sub): void
    {
        if (in_array($sub, ['pendapatan', 'pengeluaran', 'laba_rugi', 'neraca', 'arus_kas', 'omset'], true)) {
            $this->keuangan = $sub;
            $this->tutupDetailOmset();
        }
    }

    public function toggleRinciOmset(): void
    {
        $this->rinciOmset = ! $this->rinciOmset;
        $this->tutupDetailOmset();
    }

    /** `$key` format sama dgn OmsetService::ringkasan(): "kategori" atau "kategori|nama". */
    public function bukaDetailOmset(string $key): void
    {
        $this->detailOmsetKey = $key;
    }

    public function tutupDetailOmset(): void
    {
        $this->detailOmsetKey = null;
    }

    public function exportOmsetCsv(): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        [$awal, $akhir] = $this->periodeOmset();
        $omset = app(OmsetService::class);
        $rows = $omset->ringkasan($awal, $akhir, $this->rinciOmset ? 'transaksi' : 'kategori');
        $total = $omset->total($rows);
        $nama = 'omset-pimpinan-'.($this->rinciOmset ? 'transaksi-' : '').$awal->toDateString().'_'.$akhir->toDateString().'.csv';

        return response()->streamDownload(function () use ($rows, $total): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Pekerjaan', 'Unit', 'Omset Jasa', 'Omset Material', 'Total']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['label'], $r['unit'], $r['jasa'], $r['material'], $r['total']]);
            }
            fputcsv($out, ['Total', $total['unit'], $total['jasa'], $total['material'], $total['total']]);
            fclose($out);
        }, $nama, ['Content-Type' => 'text/csv']);
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function periodeOmset(): array
    {
        $bulan = CarbonImmutable::createFromFormat('!Y-m', $this->bulan);

        return [$bulan->startOfMonth(), $bulan->endOfMonth()];
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
        [$awal, $akhir] = $this->periodeOmset();

        $omsetRows = [];
        $omsetTotal = ['unit' => 0, 'jasa' => 0.0, 'material' => 0.0, 'total' => 0.0];
        $omsetDetail = collect();

        if ($this->keuangan === 'omset') {
            $omset = app(OmsetService::class);
            $omsetRows = $omset->ringkasan($awal, $akhir, $this->rinciOmset ? 'transaksi' : 'kategori');
            $omsetTotal = $omset->total($omsetRows);

            if ($this->detailOmsetKey !== null) {
                [$kategori, $nama] = str_contains($this->detailOmsetKey, '|')
                    ? explode('|', $this->detailOmsetKey, 2)
                    : [$this->detailOmsetKey, null];
                $omsetDetail = $omset->detailOrder($awal, $akhir, $kategori, $nama);
            }
        }

        return [
            'bulanLabel' => $bulan->translatedFormat('F Y'),
            'omsetRows' => $omsetRows,
            'omsetTotal' => $omsetTotal,
            'omsetDetail' => $omsetDetail,
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
