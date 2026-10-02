<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Expense;
use App\Models\Order;
use App\Models\TeknisiExpense;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Agregasi data keuangan utk halaman admin "Akuntan" (dev-plan/20).
 * Read-only: tidak menulis apa pun, tidak mengubah menu keuangan lama.
 *
 * Pendapatan = order berstatus selesai, nominal = Order::total() (layanan
 * utama + item tambahan), tanggal = tanggal bayar lunas, fallback tanggal
 * order diperbarui terakhir.
 *
 * Pengeluaran = expenses (admin) + teknisi_expenses. Teknisi: hanya
 * `approved` yang dihitung ke total; `pending` ditampilkan terpisah;
 * `rejected` diabaikan.
 */
class AkuntanService
{
    /**
     * @return Collection<int, array{order_id: int, tanggal: string, customer: string, layanan: string, total: float, items: array<int, array<string, mixed>>}>
     */
    public function pendapatan(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        $tanggalExpr = "COALESCE((SELECT MAX(p.tanggal_bayar) FROM payments p WHERE p.order_id = orders.id AND p.status = ? AND p.deleted_at IS NULL), DATE(orders.updated_at))";

        return Order::query()
            ->where('status', OrderStatus::Selesai->value)
            ->whereRaw("{$tanggalExpr} BETWEEN ? AND ?", [PaymentStatus::Lunas->value, $dari->toDateString(), $sampai->toDateString()])
            ->select('orders.*')
            ->selectRaw("{$tanggalExpr} AS tanggal_pendapatan", [PaymentStatus::Lunas->value])
            ->with(['customer', 'serviceCatalog', 'orderItems'])
            ->get()
            ->map(function (Order $order): array {
                $items = $order->orderItems->sortBy('id')->values();
                $utama = $items->first();

                return [
                    'order_id' => $order->id,
                    'tanggal' => (string) $order->getAttribute('tanggal_pendapatan'),
                    'customer' => $order->customer?->nama ?? 'Tanpa nama',
                    'layanan' => $utama?->nama_layanan ?? 'Layanan',
                    'total' => $order->total(),
                    'items' => $items->map(fn ($item, int $i) => [
                        'nama' => $item->nama_layanan,
                        'jumlah' => (int) $item->jumlah,
                        'harga' => (float) $item->harga,
                        'subtotal' => (float) $item->harga * (int) $item->jumlah,
                        'tambahan' => $i > 0,
                        'catatan' => $item->catatan,
                    ])->all(),
                ];
            })
            ->sortByDesc(fn (array $r) => $r['tanggal'].'-'.str_pad((string) $r['order_id'], 10, '0', STR_PAD_LEFT))
            ->values();
    }

    /**
     * @return Collection<int, array{id: string, tanggal: string, sumber: string, pelaku: string, kategori: string, keterangan: string, qty: ?int, harga: ?float, nominal: float, order_id: ?int, status: string, dihitung: bool}>
     */
    public function pengeluaran(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        $admin = Expense::query()
            ->whereBetween('tanggal', [$dari->toDateString(), $sampai->toDateString()])
            ->with('recordedBy')
            ->get()
            ->map(fn (Expense $e): array => [
                'id' => 'a'.$e->id,
                'tanggal' => $e->tanggal->toDateString(),
                'sumber' => 'Admin',
                'pelaku' => $e->recordedBy?->name ?? '-',
                'kategori' => $e->kategori->value,
                'keterangan' => (string) $e->keterangan,
                'qty' => $e->qty,
                'harga' => $e->harga !== null ? (float) $e->harga : null,
                'nominal' => (float) $e->nominal,
                'order_id' => $e->order_id,
                'status' => 'dicatat',
                'dihitung' => true,
            ]);

        $teknisi = TeknisiExpense::query()
            ->whereBetween('tanggal_input', [$dari->toDateString(), $sampai->toDateString()])
            ->whereIn('status', ['approved', 'pending'])
            ->with('teknisi')
            ->get()
            ->map(fn (TeknisiExpense $e): array => [
                'id' => 't'.$e->id,
                'tanggal' => $e->tanggal_input->toDateString(),
                'sumber' => 'Teknisi',
                'pelaku' => $e->teknisi?->name ?? '-',
                'kategori' => (string) $e->kategori,
                'keterangan' => (string) $e->keterangan,
                'qty' => $e->qty,
                'harga' => $e->harga !== null ? (float) $e->harga : null,
                'nominal' => (float) $e->nominal,
                'order_id' => $e->order_id,
                'status' => $e->status,
                'dihitung' => $e->status === 'approved',
            ]);

        return $admin->concat($teknisi)
            ->sortByDesc(fn (array $r) => $r['tanggal'].'-'.$r['id'])
            ->values();
    }

    /**
     * Rekap per tanggal (desc).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array{tanggal: string, jumlah: int, total: float, pending: float}>
     */
    public function rekapHarian(Collection $rows): Collection
    {
        return $rows->groupBy('tanggal')
            ->map(fn (Collection $g, string $tanggal): array => [
                'tanggal' => $tanggal,
                'jumlah' => $g->count(),
                'total' => (float) $g->filter(fn ($r) => $r['dihitung'] ?? true)->sum(fn ($r) => $r['total'] ?? $r['nominal']),
                'pending' => (float) $g->filter(fn ($r) => ! ($r['dihitung'] ?? true))->sum('nominal'),
            ])
            ->sortByDesc('tanggal')
            ->values();
    }

    /**
     * Total yang dihitung (pending tidak ikut).
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function totalPengeluaran(Collection $rows): float
    {
        return (float) $rows->where('dihitung', true)->sum('nominal');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    public function totalPendapatan(Collection $rows): float
    {
        return (float) $rows->sum('total');
    }

    /**
     * Angka 4 card ringkasan. "Hari ini" selalu hari ini; "bulan" ikut
     * bulan terpilih.
     *
     * @return array{pendapatan_hari: float, pendapatan_bulan: float, pengeluaran_hari: float, pengeluaran_bulan: float, pengeluaran_pending_bulan: float}
     */
    public function kartu(CarbonInterface $bulan): array
    {
        $awal = $bulan->copy()->startOfMonth();
        $akhir = $bulan->copy()->endOfMonth();
        $hari = now();

        $pendapatanBulan = $this->pendapatan($awal, $akhir);
        $pengeluaranBulan = $this->pengeluaran($awal, $akhir);

        $hariDalamBulan = $hari->betweenIncluded($awal, $akhir);
        $pendapatanHari = $hariDalamBulan
            ? $pendapatanBulan->where('tanggal', $hari->toDateString())
            : $this->pendapatan($hari, $hari);
        $pengeluaranHari = $hariDalamBulan
            ? $pengeluaranBulan->where('tanggal', $hari->toDateString())
            : $this->pengeluaran($hari, $hari);

        return [
            'pendapatan_hari' => $this->totalPendapatan($pendapatanHari),
            'pendapatan_bulan' => $this->totalPendapatan($pendapatanBulan),
            'pengeluaran_hari' => $this->totalPengeluaran($pengeluaranHari),
            'pengeluaran_bulan' => $this->totalPengeluaran($pengeluaranBulan),
            'pengeluaran_pending_bulan' => (float) $pengeluaranBulan->where('dihitung', false)->sum('nominal'),
        ];
    }
}
