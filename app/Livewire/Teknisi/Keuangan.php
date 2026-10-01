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
     * Order selesai milik teknisi ini dalam rentang tanggal (pakai
     * updated_at sebagai perkiraan tanggal penyelesaian).
     */
    private function orderSelesaiRentang(CarbonImmutable $mulai, CarbonImmutable $selesai): Collection
    {
        return Order::query()
            ->untukTeknisi(auth()->id())
            ->where('status', OrderStatus::Selesai->value)
            ->whereBetween('updated_at', [$mulai, $selesai])
            ->with(['customer', 'orderItems'])
            ->orderBy('updated_at')
            ->get();
    }

    private function totalOrder(Collection $orders): float
    {
        return (float) $orders->sum(fn (Order $order) => $order->total());
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
            ->untukTeknisi(auth()->id())
            ->where('status', OrderStatus::Selesai->value)
            ->with('orderItems')
            ->get()
            ->sum(fn (Order $order) => $order->total());
    }

    /**
     * Daftar pendapatan sesuai filter terpilih (harian/bulanan).
     *
     * @return array<int, array{tanggal: string, order_id: int, customer: string, total: float}>
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
                'total' => $order->total(),
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
