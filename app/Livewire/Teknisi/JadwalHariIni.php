<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Models\DailyAttendance;
use App\Models\Order;
use Livewire\Component;

class JadwalHariIni extends Component
{
    public function render()
    {
        $hariIni = now()->toDateString();

        $base = Order::query()
            ->untukTeknisi(auth()->id())
            ->whereIn('status', [
                OrderStatus::Terjadwal->value,
                OrderStatus::MenujuLokasi->value,
                OrderStatus::Dikerjakan->value,
                OrderStatus::ButuhFollowup->value,
                OrderStatus::Terkendala->value,
            ])
            ->with(['customer', 'serviceCatalog', 'orderItems', 'titik'])
            // Urut tanggal lalu jam kunjungan (tanpa jam di akhir) — id sebagai
            // pemutus seri supaya order yang diedit tidak pindah ke posisi terakhir.
            ->orderBy('tanggal_jadwal')
            ->orderByRaw('jam_jadwal is null')
            ->orderBy('jam_jadwal')
            ->orderBy('id');

        // Order dari hari-hari sebelumnya yang belum selesai/terkendala — dibuat
        // sekat terpisah supaya teknisi notice & ingatkan admin (masukan client).
        $ordersSebelumnya = (clone $base)
            ->whereDate('tanggal_jadwal', '<', $hariIni)
            ->get();

        $ordersHariIni = (clone $base)
            ->whereDate('tanggal_jadwal', '>=', $hariIni)
            ->get();

        // Gating (masukan client): menu orderan hanya bisa dibuka setelah absen datang.
        $sudahAbsen = DailyAttendance::query()
            ->where('user_id', auth()->id())
            ->whereDate('tanggal', $hariIni)
            ->whereNotNull('jam_datang')
            ->exists();

        return view('livewire.teknisi.jadwal-hari-ini', [
            'ordersSebelumnya' => $ordersSebelumnya,
            'ordersHariIni' => $ordersHariIni,
            'sudahAbsen' => $sudahAbsen,
        ])->layout('layouts.teknisi', ['title' => 'Jadwal Saya']);
    }
}
