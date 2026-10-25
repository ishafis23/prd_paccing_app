<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TeknisiExpense;
use App\Models\User;

/** Satu sumber aturan penghalang administrasi sebelum teknisi bertugas. */
class SyaratAdministrasiService
{
    /** @return array<int, array{jenis: string, order_id: ?int, judul: string, pesan: string}> */
    public function penghalang(User $teknisi): array
    {
        $orders = Order::query()->untukTeknisi($teknisi->id)
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::ButuhFollowup->value])
            ->whereNull('ditutup_pada')->with(['customer', 'orderItems'])->get();
        $hasil = [];

        foreach (app(CashHandoverService::class)->telat($teknisi) as $order) {
            $hasil[] = $this->entri('setoran', $order, 'Setoran tunai belum dilaporkan', sprintf('Order #%d (%s) memiliki setoran tunai belum dilaporkan sebesar Rp %s.', $order->id, $order->customer?->nama ?? 'tanpa customer', number_format((float) $order->total(), 0, ',', '.')));
        }

        $fotoService = app(TeknisiService::class);
        foreach ($orders as $order) {
            if ($order->laporan_terkirim_pada === null) {
                $hasil[] = $this->entri('laporan', $order, 'Laporan pengerjaan belum terkirim', sprintf('Order #%d (%s): laporan pengerjaan belum dikirim ke customer.', $order->id, $order->customer?->nama ?? 'tanpa customer'));
            }
            $kurang = $fotoService->fotoWajibKurang($order);
            if ($kurang !== []) {
                $hasil[] = $this->entri('foto', $order, 'Foto laporan belum lengkap', sprintf('Order #%d (%s): lengkapi foto/keterangan wajib: %s.', $order->id, $order->customer?->nama ?? 'tanpa customer', collect($kurang)->pluck('label')->filter()->implode(', ')));
            }
            $batas = $order->updated_at?->toDateString() ?? $order->tanggal_jadwal?->toDateString();
            $expenses = TeknisiExpense::query()->byTeknisi($teknisi)->pending()
                ->where(function ($query) use ($order, $batas): void {
                    $query->where('order_id', $order->id);
                    if ($batas !== null) {
                        $query->orWhere(fn ($q) => $q->whereNull('order_id')->whereDate('tanggal_input', '<=', $batas));
                    }
                })->get();
            if ($expenses->isNotEmpty()) {
                $hasil[] = $this->entri('pengeluaran', $order, 'Pengeluaran belum tuntas', sprintf('Order #%d (%s): terdapat %d pengeluaran pending dengan total Rp %s.', $order->id, $order->customer?->nama ?? 'tanpa customer', $expenses->count(), number_format((float) $expenses->sum('nominal'), 0, ',', '.')));
            }
        }

        $urut = ['setoran' => 0, 'laporan' => 1, 'foto' => 2, 'pengeluaran' => 3];
        usort($hasil, fn (array $a, array $b): int => ($urut[$a['jenis']] <=> $urut[$b['jenis']]) ?: ($a['order_id'] <=> $b['order_id']));
        return $hasil;
    }

    /** @param array<int, array{pesan: string}> $penghalang */
    public function pesanRingkas(array $penghalang): string { return collect($penghalang)->pluck('pesan')->implode(' '); }

    /** @param array<int, array{jenis: string}> $penghalang */
    public function tertahanSetoran(array $penghalang): bool { return collect($penghalang)->contains(fn (array $item): bool => $item['jenis'] === 'setoran'); }

    /** @return array{jenis: string, order_id: int, judul: string, pesan: string} */
    private function entri(string $jenis, Order $order, string $judul, string $pesan): array { return ['jenis' => $jenis, 'order_id' => $order->id, 'judul' => $judul, 'pesan' => $pesan]; }
}
