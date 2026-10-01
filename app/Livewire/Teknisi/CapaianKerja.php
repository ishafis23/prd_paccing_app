<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Models\Order;
use Carbon\CarbonImmutable;
use Livewire\Attributes\Computed;
use Livewire\Component;

class CapaianKerja extends Component
{
    /** Filter bulan tabel harian (format Y-m), default bulan berjalan. */
    public string $filterBulan = '';

    public function mount(): void
    {
        $this->filterBulan = today()->format('Y-m');
    }

    /**
     * Order teknisi ini yang berstatus tertentu dan terakhir diperbarui
     * dalam rentang tanggal.
     */
    private function ordersRentang(CarbonImmutable $mulai, CarbonImmutable $selesai): \Illuminate\Support\Collection
    {
        return Order::query()
            ->untukTeknisi(auth()->id())
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::Terkendala->value])
            ->whereBetween('updated_at', [$mulai, $selesai])
            ->get();
    }

    #[Computed]
    public function hariIni(): int
    {
        return $this->ordersRentang(
            CarbonImmutable::today()->startOfDay(),
            CarbonImmutable::today()->endOfDay(),
        )->where('status', OrderStatus::Selesai)->count();
    }

    #[Computed]
    public function bulanIni(): int
    {
        return $this->ordersRentang(
            CarbonImmutable::now()->startOfMonth(),
            CarbonImmutable::now()->endOfMonth(),
        )->where('status', OrderStatus::Selesai)->count();
    }

    #[Computed]
    public function totalSelesai(): int
    {
        return Order::query()
            ->untukTeknisi(auth()->id())
            ->where('status', OrderStatus::Selesai->value)
            ->count();
    }

    /**
     * Rincian per hari pada bulan terpilih: berapa selesai & terkendala.
     *
     * @return array<int, array{tanggal: string, hari: int, selesai: int, terkendala: int}>
     */
    #[Computed]
    public function capaianHarian(): array
    {
        $bulan = CarbonImmutable::parse(($this->filterBulan ?: today()->format('Y-m')).'-01');
        $mulai = $bulan->startOfMonth();
        $selesai = $bulan->endOfMonth();

        $orders = $this->ordersRentang($mulai, $selesai)
            ->groupBy(fn (Order $order) => $order->updated_at->toDateString());

        $baris = [];

        for ($hari = 1; $hari <= $mulai->daysInMonth; $hari++) {
            $tanggal = $mulai->addDays($hari - 1);
            $grup = $orders->get($tanggal->toDateString(), collect());

            $baris[] = [
                'tanggal' => $tanggal->translatedFormat('d M Y'),
                'hari' => $hari,
                'selesai' => $grup->where('status', OrderStatus::Selesai)->count(),
                'terkendala' => $grup->where('status', OrderStatus::Terkendala)->count(),
            ];
        }

        return $baris;
    }

    public function render()
    {
        return view('livewire.teknisi.capaian-kerja')
            ->layout('layouts.teknisi', ['title' => 'Capaian Kerja']);
    }
}
