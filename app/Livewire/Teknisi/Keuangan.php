<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\TeknisiKeuanganService;
use App\Services\TechnicianIncentiveService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Keuangan extends Component
{
    /** Tab aktif: pengeluaran | pendapatan. */
    public string $tab = 'pengeluaran';

    /** Filter pendapatan: bulanan | harian. */
    public string $modePendapatan = 'bulanan';

    /** Bulan pendapatan (Y-m) — default bulan berjalan. */
    public string $filterBulan = '';

    /** Tanggal pendapatan (Y-m-d) — dipakai saat mode harian. */
    public string $filterTanggal = '';

    public function mount(): void
    {
        $this->filterBulan = today()->format('Y-m');
        $this->filterTanggal = today()->format('Y-m-d');
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['pengeluaran', 'pendapatan'], true)) {
            $this->tab = $tab;
        }
    }

    /**
     * Order selesai dengan teknisi ini sebagai PIC dalam rentang tanggal.
     * Tanggal omset = tanggal order DIJADWALKAN/dikerjakan (sama dengan admin,
     * OmsetService::tanggalPendapatanSql), bukan tanggal laporan diselesaikan
     * atau tanggal pembayaran dicatat. Order yang
     * dia hanya jadi anggota tim tidak ikut — pendapatan harus benar-benar
     * jasa miliknya sendiri.
     */
    private function orderSelesaiRentang(CarbonImmutable $mulai, CarbonImmutable $selesai): Collection
    {
        return app(TeknisiKeuanganService::class)->orderSelesaiRentang(auth()->user(), $mulai, $selesai);
    }

    /**
     * Portal teknisi hanya menampilkan omset JASA (material tidak ikut) —
     * supaya potensi bonus terbaca jelas. Admin tetap melihat total penuh.
     */
    private function totalOrder(Collection $orders): float
    {
        return (float) $orders->sum(fn (Order $order) => $order->omsetJasa());
    }

    #[Computed]
    public function pendapatanHariIni(): float
    {
        return $this->totalOrder($this->orderSelesaiRentang(
            CarbonImmutable::today()->startOfDay(),
            CarbonImmutable::today()->endOfDay(),
        ));
    }

    #[Computed]
    public function pendapatanBulanIni(): float
    {
        return $this->totalOrder($this->orderSelesaiRentang(
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        ));
    }

    #[Computed]
    public function pendapatanTotal(): float
    {
        return app(TeknisiKeuanganService::class)->pendapatanTotal(auth()->user());
    }

    /**
     * Daftar pendapatan sesuai filter terpilih (harian/bulanan).
     *
     * @return array<int, array{tanggal: string, order_id: int, customer: string, layanan: string, total: float}>
     */
    #[Computed]
    public function daftarPendapatan(): array
    {
        if ($this->modePendapatan === 'harian') {
            $mulai = CarbonImmutable::parse($this->filterTanggal ?: today()->format('Y-m-d'))->startOfDay();
            $selesai = $mulai->endOfDay();
        } else {
            $bulan = CarbonImmutable::parse(($this->filterBulan ?: today()->format('Y-m')).'-01');
            $mulai = $bulan->startOfMonth();
            $selesai = $bulan->endOfMonth();
        }

        return $this->orderSelesaiRentang($mulai, $selesai)
            ->map(fn (Order $order) => [
                'tanggal' => ($order->tanggal_jadwal ?? $order->updated_at)->translatedFormat('d M Y'),
                'order_id' => $order->id,
                'customer' => $order->customer?->nama ?? 'Tanpa nama',
                'layanan' => $order->ringkasanLayanan(),
                'total' => $order->omsetJasa(),
            ])
            ->all();
    }

    /** Games 5 & 6 untuk tanggal terpilih (hanya mode harian). */
    #[Computed]
    public function gamesHarian(): ?array
    {
        if ($this->modePendapatan !== 'harian' || ! $this->filterTanggal) {
            return null;
        }

        return app(TechnicianIncentiveService::class)
            ->ringkasanGames56(auth()->user(), \Illuminate\Support\Carbon::parse($this->filterTanggal));
    }

    /**
     * Order tim yang dihitung untuk Games 5/6 pada tanggal terpilih (mode harian),
     * supaya angka omset & unit di kartu bisa ditelusuri. Termasuk order yang
     * teknisi hanya jadi anggota (bukan PIC).
     *
     * @return array<int, array{order_id: int, customer: string, layanan: string, jasa: float, unit: int, peran: string}>
     */
    #[Computed]
    public function daftarOrderTim(): array
    {
        if ($this->modePendapatan !== 'harian' || ! $this->filterTanggal) {
            return [];
        }

        $userId = auth()->id();

        return app(TechnicianIncentiveService::class)
            ->ordersGames56(auth()->user(), \Illuminate\Support\Carbon::parse($this->filterTanggal))
            ->map(fn (Order $order) => [
                'order_id' => $order->id,
                'customer' => $order->customer?->nama ?? 'Tanpa nama',
                'layanan' => $order->ringkasanLayanan(),
                'jasa' => $order->omsetJasa(),
                'unit' => (int) $order->jumlahUnit(),
                'peran' => $order->teknisi_id === $userId ? 'PIC' : 'Anggota',
            ])
            ->all();
    }

    #[Computed]
    public function totalPendapatanFilter(): float
    {
        return (float) collect($this->daftarPendapatan)->sum('total');
    }

    public function formatRupiah(float|int $nominal): string
    {
        return 'Rp '.number_format((float) $nominal, 0, ',', '.');
    }

    public function render()
    {
        return view('livewire.teknisi.keuangan')
            ->layout('layouts.teknisi', ['title' => 'Keuangan']);
    }
}
