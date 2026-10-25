<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TeknisiExpense;
use App\Models\User;

/**
 * Satu sumber aturan penghalang administrasi sebelum teknisi bertugas
 * (dev-plan/29 C1). Dipakai TeknisiService::berangkat() dan checkIn().
 *
 * Prinsip:
 * - Menutup order tetap urusan teknisi di lokasi (keputusan B32 tidak diubah):
 *   tutupOrder() TIDAK memeriksa syarat administrasi.
 * - Tetapi menutup order tidak boleh menjadi jalan pintas. Karena itu ketiga
 *   syarat (laporan terkirim, foto/keterangan lengkap, pengeluaran tuntas) tetap
 *   berlaku untuk order yang ditutup dalam tenggang
 *   (config administrasi.tenggang_hari, default 7 hari).
 * - Order yang ditutup lebih lama dari tenggang = pekerjaan historis: tidak
 *   memblokir, tetapi tetap bisa diperiksa lewat `php artisan administrasi:periksa`.
 * - Setoran tunai tidak mengenal tenggang: uang tetap harus disetor.
 */
class SyaratAdministrasiService
{
    /** Urutan tampil jenis penghalang. */
    public const URUTAN = ['setoran' => 0, 'laporan' => 1, 'foto' => 2, 'pengeluaran' => 3];

    /**
     * Semua penghalang milik seorang teknisi.
     *
     * @return array<int, array{jenis: string, order_id: ?int, judul: string, pesan: string}>
     */
    public function penghalang(User $teknisi): array
    {
        $hasil = [];

        // 1. Setoran tunai — berlaku juga untuk order yang sudah ditutup.
        foreach (app(CashHandoverService::class)->telat($teknisi) as $order) {
            $hasil[] = $this->entri('setoran', $order, 'Setoran tunai belum dilaporkan', sprintf(
                'Order #%d (%s) memiliki setoran tunai belum dilaporkan sebesar Rp %s.',
                $order->id,
                $order->customer?->nama ?? 'tanpa customer',
                number_format((float) $order->total(), 0, ',', '.'),
            ));
        }

        // 2. Laporan / foto / pengeluaran — order yang belum ditutup, ditambah
        //    order yang ditutup dalam tenggang (celah "tutup order dulu").
        $tenggang = now()->subDays((int) config('administrasi.tenggang_hari', 7));

        $orders = Order::query()->untukTeknisi($teknisi->id)
            ->whereIn('status', [OrderStatus::Selesai->value, OrderStatus::ButuhFollowup->value])
            ->where(fn ($q) => $q->whereNull('ditutup_pada')->orWhere('ditutup_pada', '>=', $tenggang))
            ->with(['customer', 'orderItems'])
            ->get();

        foreach ($orders as $order) {
            if ($order->laporan_terkirim_pada === null) {
                $hasil[] = $this->entri('laporan', $order, 'Laporan pengerjaan belum terkirim', sprintf(
                    'Order #%d (%s): laporan pengerjaan belum dikirim ke customer.',
                    $order->id,
                    $order->customer?->nama ?? 'tanpa customer',
                ));
            }

            $hasil = [...$hasil, ...$this->penghalangFisik($order, $teknisi)];
        }

        usort($hasil, fn (array $a, array $b): int => (self::URUTAN[$a['jenis']] <=> self::URUTAN[$b['jenis']])
            ?: ($a['order_id'] <=> $b['order_id']));

        return $hasil;
    }

    /**
     * Foto/keterangan wajib + pengeluaran pending milik teknisi untuk satu order.
     *
     * @return array<int, array{jenis: string, order_id: ?int, judul: string, pesan: string}>
     */
    public function penghalangFisik(Order $order, User $teknisi): array
    {
        $hasil = [];
        $nama = $order->customer?->nama ?? 'tanpa customer';

        $kurang = app(TeknisiService::class)->fotoWajibKurang($order);
        if ($kurang !== []) {
            $hasil[] = $this->entri('foto', $order, 'Foto laporan belum lengkap', sprintf(
                'Lengkapi dulu foto pada order #%d (%s) sebelum berangkat ke order berikutnya: %s.',
                $order->id,
                $nama,
                collect($kurang)->pluck('label')->filter()->implode(', '),
            ));
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
            $hasil[] = $this->entri('pengeluaran', $order, 'Pengeluaran belum tuntas', sprintf(
                'Order #%d (%s): terdapat %d pengeluaran pending dengan total Rp %s.',
                $order->id,
                $nama,
                $expenses->count(),
                number_format((float) $expenses->sum('nominal'), 0, ',', '.'),
            ));
        }

        return $hasil;
    }

    /** @param array<int, array{pesan: string}> $penghalang */
    public function pesanRingkas(array $penghalang): string
    {
        return collect($penghalang)->pluck('pesan')->implode(' ');
    }

    /** @param array<int, array{jenis: string}> $penghalang */
    public function tertahanSetoran(array $penghalang): bool
    {
        return collect($penghalang)->contains(fn (array $item): bool => $item['jenis'] === 'setoran');
    }

    /** @return array{jenis: string, order_id: int, judul: string, pesan: string} */
    private function entri(string $jenis, Order $order, string $judul, string $pesan): array
    {
        return ['jenis' => $jenis, 'order_id' => $order->id, 'judul' => $judul, 'pesan' => $pesan];
    }
}
