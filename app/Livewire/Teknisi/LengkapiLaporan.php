<?php

namespace App\Livewire\Teknisi;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\TeknisiService;
use Livewire\Component;

/**
 * Menu "Lengkapi Laporan" (Fase 4, dev-plan/21 §5 + dev-plan/18 §A): daftar
 * order milik teknisi yang laporannya sudah disubmit tetapi foto ATAU
 * keterangan unitnya belum lengkap. Memakai ulang
 * TeknisiService::fotoWajibKurang() — satu sumber kebenaran yang sama
 * dengan gerbang berangkat().
 */
class LengkapiLaporan extends Component
{
    public function render()
    {
        $service = app(TeknisiService::class);

        $orders = Order::query()
            ->untukTeknisi(auth()->id())
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::ButuhFollowup->value])
            ->whereNull('ditutup_pada')
            ->with(['customer', 'orderItems'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function (Order $order) use ($service): array {
                $kurang = collect($service->fotoWajibKurang($order));
                $ketPertama = $kurang->firstWhere('jenis', 'keterangan');

                return [
                    'order' => $order,
                    'foto' => $kurang->where('jenis', 'foto')->count(),
                    'keterangan' => $kurang->where('jenis', 'keterangan')->count(),
                    'rincian' => $kurang->pluck('label')->implode(', '),
                    // Tap -> langsung ke unit pertama yang kurang; kalau hanya
                    // foto yang kurang, ke blok "Lengkapi Foto".
                    'tautan' => $ketPertama !== null
                        ? \App\Support\Url::absolute('teknisi.order', ['order' => $order->id]).'?unit='.$ketPertama['unit_no'].'#unit-'.$ketPertama['unit_no']
                        : \App\Support\Url::absolute('teknisi.order', ['order' => $order->id]).'#lengkapi-foto',
                    'kosong' => $kurang->isEmpty(),
                ];
            })
            ->reject(fn (array $baris): bool => $baris['kosong'])
            ->values();

        return view('livewire.teknisi.lengkapi-laporan', ['daftar' => $orders])
            ->layout('layouts.teknisi', ['title' => 'Lengkapi Laporan']);
    }
}
