<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Services\AkuntanService;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Halaman "Akuntan" (dev-plan/20): tampilan read-only pendapatan &
 * pengeluaran per bulan. Berjalan paralel dgn menu keuangan lama sampai
 * angkanya terverifikasi cocok.
 */
class Akuntan extends Page
{
    protected static string $view = 'filament.pages.akuntan';

    protected static ?string $navigationGroup = 'Finance';

    protected static ?string $navigationLabel = 'Akuntan';

    protected static ?string $title = 'Akuntan';

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $slug = 'akuntan';

    private const PER_HALAMAN = 25;

    /** Bulan terpilih (Y-m). */
    #[Url]
    public string $bulan = '';

    /** pendapatan | pengeluaran | semua */
    #[Url]
    public string $tab = 'pendapatan';

    /** Sub-tampilan tab "semua": pendapatan | pengeluaran. */
    public string $semua = 'pendapatan';

    /** Tanggal yg detailnya sedang dibuka (modal), null = tertutup. */
    public ?string $detailTanggal = null;

    public int $halaman = 1;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->hasAnyRole([RoleName::Owner->value, RoleName::Admin->value, RoleName::Finance->value]);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            $this->bulan = now()->format('Y-m');
        }

        if (! in_array($this->tab, ['pendapatan', 'pengeluaran', 'semua'], true)) {
            $this->tab = 'pendapatan';
        }
    }

    public function updatedBulan(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->bulan)) {
            $this->bulan = now()->format('Y-m');
        }

        $this->tutupDetail();
        $this->halaman = 1;
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['pendapatan', 'pengeluaran', 'semua'], true)) {
            $this->tab = $tab;
            $this->halaman = 1;
            $this->tutupDetail();
        }
    }

    public function setSemua(string $semua): void
    {
        if (in_array($semua, ['pendapatan', 'pengeluaran'], true)) {
            $this->semua = $semua;
            $this->halaman = 1;
        }
    }

    public function bukaDetail(string $tanggal): void
    {
        $this->detailTanggal = $tanggal;
    }

    public function tutupDetail(): void
    {
        $this->detailTanggal = null;
    }

    public function halamanSebelumnya(): void
    {
        $this->halaman = max(1, $this->halaman - 1);
    }

    public function halamanBerikutnya(): void
    {
        $this->halaman++;
    }

    protected function getViewData(): array
    {
        $service = app(AkuntanService::class);
        $bulan = CarbonImmutable::createFromFormat('!Y-m', $this->bulan);
        $awal = $bulan->startOfMonth();
        $akhir = $bulan->endOfMonth();

        $pendapatan = $service->pendapatan($awal, $akhir);
        $pengeluaran = $service->pengeluaran($awal, $akhir);

        $data = [
            'bulanLabel' => $bulan->translatedFormat('F Y'),
            'kartu' => $service->kartu($bulan),
            'rekapPendapatan' => collect(),
            'rekapPengeluaran' => collect(),
            'detailPendapatan' => collect(),
            'detailPengeluaran' => collect(),
            'daftar' => collect(),
            'totalDaftar' => 0,
            'totalHalaman' => 1,
        ];

        if ($this->tab === 'pendapatan') {
            $data['rekapPendapatan'] = $service->rekapHarian($pendapatan);
        } elseif ($this->tab === 'pengeluaran') {
            $data['rekapPengeluaran'] = $service->rekapHarian($pengeluaran);
        } else {
            /** @var Collection<int, array<string, mixed>> $sumber */
            $sumber = $this->semua === 'pendapatan' ? $pendapatan : $pengeluaran;
            $data['totalDaftar'] = $sumber->count();
            $data['totalHalaman'] = max(1, (int) ceil($sumber->count() / self::PER_HALAMAN));
            $this->halaman = min(max(1, $this->halaman), $data['totalHalaman']);
            $data['daftar'] = $sumber->forPage($this->halaman, self::PER_HALAMAN)->values();
        }

        if ($this->detailTanggal !== null) {
            $data['detailPendapatan'] = $pendapatan->where('tanggal', $this->detailTanggal)->values();
            $data['detailPengeluaran'] = $pengeluaran->where('tanggal', $this->detailTanggal)->values();
        }

        return $data;
    }
}
