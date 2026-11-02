<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Pendapatan jasa & capaian kerja teknisi — dipakai portal web (Keuangan,
 * CapaianKerja) dan API mobile.
 *
 * Aturan (revisi client 1 Nov, poin 1): omset JASA saja, tanggal = tanggal order
 * DIJADWALKAN (OmsetService::tanggalPendapatanSql), hanya order yang teknisi
 * jadi PIC. Capaian (jumlah order selesai/terkendala) mengikuti tim (PIC atau anggota).
 */
class TeknisiKeuanganService
{
    /**
     * Order selesai dengan teknisi ini sebagai PIC dalam rentang tanggal.
     *
     * @return Collection<int, Order>
     */
    public function orderSelesaiRentang(User $teknisi, CarbonImmutable $mulai, CarbonImmutable $selesai): Collection
    {
        return Order::query()
            ->where('teknisi_id', $teknisi->id)
            ->where('status', OrderStatus::Selesai->value)
            ->whereRaw(OmsetService::tanggalPendapatanSql().' BETWEEN ? AND ?', [$mulai->toDateString(), $selesai->toDateString()])
            ->with(['customer', 'orderItems', 'latestPayment'])
            ->orderByRaw(OmsetService::tanggalPendapatanSql())
            ->orderBy('id')
            ->get();
    }

    /** Total omset jasa kumpulan order (material tidak ikut). */
    public function totalJasa(Collection $orders): float
    {
        return (float) $orders->sum(fn (Order $order): float => $order->omsetJasa());
    }

    public function pendapatanHariIni(User $teknisi): float
    {
        return $this->totalJasa($this->orderSelesaiRentang($teknisi, CarbonImmutable::today()->startOfDay(), CarbonImmutable::today()->endOfDay()));
    }

    public function pendapatanBulanIni(User $teknisi): float
    {
        return $this->totalJasa($this->orderSelesaiRentang($teknisi, CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth()));
    }

    public function pendapatanTotal(User $teknisi): float
    {
        return (float) Order::query()
            ->where('teknisi_id', $teknisi->id)
            ->where('status', OrderStatus::Selesai->value)
            ->with(['orderItems', 'latestPayment'])
            ->get()
            ->sum(fn (Order $order): float => $order->omsetJasa());
    }

    /**
     * Rentang tanggal sesuai mode filter.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function rentang(string $mode, ?string $bulan, ?string $tanggal): array
    {
        if ($mode === 'harian') {
            $mulai = CarbonImmutable::parse($tanggal ?: today()->format('Y-m-d'))->startOfDay();

            return [$mulai, $mulai->endOfDay()];
        }

        $b = CarbonImmutable::parse(($bulan ?: today()->format('Y-m')).'-01');

        return [$b->startOfMonth(), $b->endOfMonth()];
    }

    /**
     * Daftar pendapatan sesuai filter.
     *
     * @return array<int, array{tanggal: string, tanggal_iso: ?string, order_id: int, customer: string, layanan: string, total: float}>
     */
    public function daftarPendapatan(User $teknisi, string $mode, ?string $bulan, ?string $tanggal): array
    {
        [$mulai, $selesai] = $this->rentang($mode, $bulan, $tanggal);

        return $this->orderSelesaiRentang($teknisi, $mulai, $selesai)
            ->map(fn (Order $order): array => [
                'tanggal' => ($order->tanggal_jadwal ?? $order->updated_at)->translatedFormat('d M Y'),
                'tanggal_iso' => ($order->tanggal_jadwal ?? $order->updated_at)?->toDateString(),
                'order_id' => $order->id,
                'customer' => $order->customer?->nama ?? 'Tanpa nama',
                'layanan' => $order->ringkasanLayanan(),
                'total' => $order->omsetJasa(),
            ])
            ->all();
    }

    /**
     * Games 5 & 6 tim pada tanggal tertentu (kartu mode harian).
     *
     * @return array<string, mixed>
     */
    public function gamesHarian(User $teknisi, string $tanggal): array
    {
        return app(TechnicianIncentiveService::class)->ringkasanGames56($teknisi, Carbon::parse($tanggal));
    }

    /** Order milik tim yang diselesaikan/terkendala pada rentang (berdasar updated_at, seperti web). */
    private function ordersCapaian(User $teknisi, CarbonImmutable $mulai, CarbonImmutable $selesai): Collection
    {
        return Order::query()
            ->untukTeknisi($teknisi->id)
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::Terkendala->value])
            ->whereBetween('updated_at', [$mulai, $selesai])
            ->get();
    }

    /**
     * Capaian kerja: kartu + rincian harian satu bulan.
     *
     * @return array{hari_ini: int, bulan_ini: int, total_selesai: int, bulan: string, harian: array<int, array{tanggal: string, hari: int, selesai: int, terkendala: int}>}
     */
    public function capaian(User $teknisi, ?string $bulan = null): array
    {
        $bulan = $bulan ?: today()->format('Y-m');
        $awal = CarbonImmutable::parse($bulan.'-01')->startOfMonth();

        $hariIni = $this->ordersCapaian($teknisi, CarbonImmutable::today()->startOfDay(), CarbonImmutable::today()->endOfDay())
            ->where('status', OrderStatus::Selesai)->count();
        $bulanIni = $this->ordersCapaian($teknisi, CarbonImmutable::now()->startOfMonth(), CarbonImmutable::now()->endOfMonth())
            ->where('status', OrderStatus::Selesai)->count();
        $total = Order::query()->untukTeknisi($teknisi->id)->where('status', OrderStatus::Selesai->value)->count();

        $grup = $this->ordersCapaian($teknisi, $awal, $awal->endOfMonth())
            ->groupBy(fn (Order $o): string => $o->updated_at->toDateString());

        $harian = [];
        for ($hari = 1; $hari <= $awal->daysInMonth; $hari++) {
            $tgl = $awal->addDays($hari - 1);
            $g = $grup->get($tgl->toDateString(), collect());
            $harian[] = [
                'tanggal' => $tgl->toDateString(),
                'hari' => $hari,
                'selesai' => $g->where('status', OrderStatus::Selesai)->count(),
                'terkendala' => $g->where('status', OrderStatus::Terkendala)->count(),
            ];
        }

        return ['hari_ini' => $hariIni, 'bulan_ini' => $bulanIni, 'total_selesai' => $total, 'bulan' => $bulan, 'harian' => $harian];
    }
}
