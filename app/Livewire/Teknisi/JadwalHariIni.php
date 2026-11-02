<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Models\DailyAttendance;
use App\Models\Order;
use App\Services\SyaratAdministrasiService;
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

        $administrasi = app(SyaratAdministrasiService::class);
        $penghalang = $administrasi->penghalang(auth()->user());

        // Titik berurutan: order yang belum dimulai terkunci selama Titik sebelumnya belum beres.
        $svc = app(\App\Services\TeknisiService::class);
        $terkunci = [];
        foreach ($ordersSebelumnya->concat($ordersHariIni) as $o) {
            if ($o->status === OrderStatus::Terjadwal && ($p = $svc->titikPenghalang($o, auth()->user())) !== null) {
                $terkunci[$o->id] = $p->titik->nama;
            }
        }

        return view('livewire.teknisi.jadwal-hari-ini', [
            'ordersSebelumnya' => $ordersSebelumnya,
            'ordersHariIni' => $ordersHariIni,
            'sudahAbsen' => $sudahAbsen,
            'terkunci' => $terkunci,
            'penghalang' => $penghalang,
            'tertahan' => $administrasi->tertahanSetoran($penghalang),
        ])->layout('layouts.teknisi', ['title' => 'Jadwal Saya']);
    }
}
