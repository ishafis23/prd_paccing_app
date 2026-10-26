<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Models\Order;
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
     * Order selesai dengan teknisi ini sebagai PIC dalam rentang tanggal
     * (pakai updated_at sebagai perkiraan tanggal penyelesaian). Order yang
     * dia hanya jadi anggota tim tidak ikut — pendapatan harus benar-benar
     * jasa miliknya sendiri.
     */
    private function orderSelesaiRentang(CarbonImmutable $mulai, CarbonImmutable $selesai): Collection
    {
        return Order::query()
            ->where('teknisi_id', auth()->id())
            ->where('status', OrderStatus::Selesai->value)
            ->whereBetween('updated_at', [$mulai, $selesai])
            ->with(['customer', 'orderItems', 'latestPayment'])
            ->orderBy('updated_at')
            ->get();
    }

    /**
     * Omset JASA satu order. Bila admin sudah mencatat pembayaran dengan split
     * omset (dev-plan/24), angka itulah yang dipakai — supaya tampilan teknisi
     * ikut angka final yang dicatat admin. Tanpa pembayaran, fallback ke
     * rincian order_items. Material tidak pernah ikut dihitung.
     */
    private function omsetJasa(Order $order): float
    {
        $split = $order->latestPayment?->splitOmset();

        return $split !== null ? $split['jasa'] : $order->totalPerKomponen()['jasa'];
    }

    /**
     * Portal teknisi hanya menampilkan omset JASA (material tidak ikut) —
     * supaya potensi bonus terbaca jelas. Admin tetap melihat total penuh.
     */
    private function totalOrder(Collection $orders): float
    {
        return (float) $orders->sum(fn (Order $order) => $this->omsetJasa($order));
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
        return (float) Order::query()
            ->where('teknisi_id', auth()->id())
            ->where('status', OrderStatus::Selesai->value)
            ->with(['orderItems', 'latestPayment'])
            ->get()
            ->sum(fn (Order $order) => $this->omsetJasa($order));
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
                'tanggal' => $order->updated_at->translatedFormat('d M Y'),
                'order_id' => $order->id,
                'customer' => $order->customer?->nama ?? 'Tanpa nama',
                'layanan' => $order->ringkasanLayanan(),
                'total' => $this->omsetJasa($order),
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
