<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Models\Order;
use App\Models\OrderItem;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Satu sumber kebenaran omset jasa/material (dev-plan/21 §4).
 *
 * Aturan tunggal "tanggal pendapatan" order selesai = tanggal bayar lunas
 * terakhir, fallback tanggal order diperbarui. Dipakai AkuntanService
 * (→ FinanceService), DashboardPimpinanService, dan widget dashboard supaya
 * angka ketiganya sama untuk periode yang sama.
 */
class OmsetService
{
    /** Kartu yang selalu tampil walau belum ada transaksi. */
    public const KATEGORI_UTAMA = ['cuci_ac', 'service_ac', 'pengadaan_ac'];

    /** Urutan tampil kategori. */
    private const URUTAN = ['cuci_ac', 'service_ac', 'pengadaan_ac', 'tambah_freon', 'instalasi', 'relokasi', 'bongkar', 'lainnya'];

    private const LABEL = [
        'cuci_ac' => 'Cuci AC',
        'service_ac' => 'Service AC',
        'pengadaan_ac' => 'Pasang/Pengadaan',
        'tambah_freon' => 'Tambah Freon',
        'instalasi' => 'Instalasi',
        'relokasi' => 'Relokasi',
        'bongkar' => 'Bongkar',
        'lainnya' => 'Lainnya',
    ];

    /**
     * Ekspresi SQL tanggal pendapatan sebuah order (tabel `orders`).
     */
    public static function tanggalPendapatanSql(): string
    {
        $lunas = PaymentStatus::Lunas->value;

        return "COALESCE((SELECT MAX(p.tanggal_bayar) FROM payments p WHERE p.order_id = orders.id AND p.status = '{$lunas}' AND p.deleted_at IS NULL), DATE(orders.updated_at))";
    }

    /**
     * Order selesai yang tanggal pendapatannya jatuh di [dari, sampai]
     * (inklusif, per tanggal). Kolom `tanggal_pendapatan` ikut di-select.
     *
     * @return Builder<Order>
     */
    public function orderSelesai(CarbonInterface $dari, CarbonInterface $sampai): Builder
    {
        $expr = self::tanggalPendapatanSql();

        return Order::query()
            ->where('status', OrderStatus::Selesai->value)
            ->whereRaw("{$expr} BETWEEN ? AND ?", [$dari->toDateString(), $sampai->toDateString()])
            ->select('orders.*')
            ->selectRaw("{$expr} AS tanggal_pendapatan");
    }

    /**
     * Satu baris per item layanan aktif (bukan dibatalkan) pada order selesai
     * di periode. Order tanpa item (data sangat lama) jatuh ke harga katalog.
     *
     * @return Collection<int, array{order_id: int, tanggal: string, kategori: string, nama: string, komponen: string, unit: int, subtotal: float}>
     */
    public function baris(CarbonInterface $dari, CarbonInterface $sampai): Collection
    {
        $hasil = collect();

        $this->orderSelesai($dari, $sampai)
            ->with(['orderItems', 'serviceCatalog'])
            ->get()
            ->each(function (Order $order) use ($hasil): void {
                $tanggal = (string) $order->getAttribute('tanggal_pendapatan');

                if ($order->orderItems->isEmpty()) {
                    $jenis = $order->serviceCatalog?->jenis_layanan;
                    $komponen = $order->serviceCatalog?->komponenOmset()
                        ?? \App\Enums\IncomeCategory::untukLayanan($jenis);
                    $unit = max(1, (int) $order->jumlah_unit);

                    $hasil->push([
                        'order_id' => $order->id,
                        'tanggal' => $tanggal,
                        'kategori' => $jenis?->value ?? 'lainnya',
                        'nama' => $jenis !== null ? self::label($jenis->value) : 'Layanan',
                        'komponen' => $komponen->value,
                        'unit' => $unit,
                        'subtotal' => (float) ($order->serviceCatalog?->harga ?? 0) * $unit,
                    ]);

                    return;
                }

                foreach ($order->orderItems as $item) {
                    /** @var OrderItem $item */
                    if ($item->dibatalkan()) {
                        continue;
                    }

                    $hasil->push([
                        'order_id' => $order->id,
                        'tanggal' => $tanggal,
                        'kategori' => $item->kategori instanceof ServiceType ? $item->kategori->value : 'lainnya',
                        'nama' => $this->namaBaris($item),
                        'komponen' => $item->komponenOmset()->value,
                        'unit' => $item->penyesuaian ? 0 : (int) $item->jumlah,
                        'subtotal' => $item->subtotal(),
                    ]);
                }
            });

        return $hasil;
    }

    /**
     * Omset per pekerjaan. `$mode`: 'kategori' (Cuci AC, Service AC, …) atau
     * 'transaksi' (rinci per nama layanan, mis. "Ganti Kapasitor").
     *
     * @return array<int, array{key: string, label: string, kategori: string, unit: int, jasa: float, material: float, total: float, transaksi: int}>
     */
    public function ringkasan(CarbonInterface $dari, CarbonInterface $sampai, string $mode = 'kategori'): array
    {
        $baris = $this->baris($dari, $sampai);
        $transaksi = $mode === 'transaksi';

        $rows = $baris
            ->groupBy(fn (array $b): string => $transaksi ? $b['kategori'].'|'.$b['nama'] : $b['kategori'])
            ->map(function (Collection $grup, string $key) use ($transaksi): array {
                $pertama = $grup->first();
                $jasa = (float) $grup->where('komponen', 'jasa')->sum('subtotal');
                $material = (float) $grup->where('komponen', 'material')->sum('subtotal');

                return [
                    'key' => $key,
                    'label' => $transaksi ? $pertama['nama'] : self::label($pertama['kategori']),
                    'kategori' => $pertama['kategori'],
                    'unit' => (int) $grup->sum('unit'),
                    'jasa' => $jasa,
                    'material' => $material,
                    'total' => $jasa + $material,
                    'transaksi' => $grup->count(),
                ];
            })
            ->values();

        return $rows
            ->sortBy(fn (array $r): string => str_pad((string) (array_search($r['kategori'], self::URUTAN, true) ?: 0), 2, '0', STR_PAD_LEFT).'|'.mb_strtolower($r['label']))
            ->values()
            ->all();
    }

    /**
     * Baris kartu per kategori: tiga kategori utama selalu ada; kategori lain
     * hanya bila ada transaksi pada periode.
     *
     * @return array<int, array{key: string, label: string, kategori: string, unit: int, jasa: float, material: float, total: float, transaksi: int}>
     */
    public function kartu(CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $rows = collect($this->ringkasan($dari, $sampai))->keyBy('kategori');

        foreach (self::KATEGORI_UTAMA as $kategori) {
            if (! $rows->has($kategori)) {
                $rows->put($kategori, [
                    'key' => $kategori, 'label' => self::label($kategori), 'kategori' => $kategori,
                    'unit' => 0, 'jasa' => 0.0, 'material' => 0.0, 'total' => 0.0, 'transaksi' => 0,
                ]);
            }
        }

        return $rows
            ->sortBy(fn (array $r): int => (int) array_search($r['kategori'], self::URUTAN, true))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{unit: int, jasa: float, material: float, total: float}
     */
    public function total(array $rows): array
    {
        $rows = collect($rows);

        return [
            'unit' => (int) $rows->sum('unit'),
            'jasa' => (float) $rows->sum('jasa'),
            'material' => (float) $rows->sum('material'),
            'total' => (float) $rows->sum('total'),
        ];
    }

    /**
     * Terjemahkan state filter dashboard (URL) jadi rentang tanggal.
     * `mode` = 'bulan' (pakai `bulan` Y-m) atau 'rentang' (pakai `dari`/`sampai`).
     * Input tidak valid jatuh ke bulan ini.
     *
     * @param  array<string, mixed>|null  $filters
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}  [dari, sampai, label]
     */
    public function rentangDariFilter(?array $filters): array
    {
        $filters ??= [];

        if (($filters['mode'] ?? 'bulan') === 'rentang') {
            $dari = $this->parseTanggal($filters['dari'] ?? null);
            $sampai = $this->parseTanggal($filters['sampai'] ?? null);

            if ($dari !== null || $sampai !== null) {
                $dari ??= $sampai;
                $sampai ??= $dari;

                if ($dari->gt($sampai)) {
                    [$dari, $sampai] = [$sampai, $dari];
                }

                $label = $dari->equalTo($sampai)
                    ? $dari->translatedFormat('j F Y')
                    : $dari->translatedFormat('j M Y').' – '.$sampai->translatedFormat('j M Y');

                return [$dari->startOfDay(), $sampai->endOfDay(), $label];
            }
        }

        $bulan = null;
        if (is_string($filters['bulan'] ?? null) && preg_match('/^\d{4}-\d{2}$/', $filters['bulan'])) {
            try {
                $bulan = CarbonImmutable::createFromFormat('!Y-m', $filters['bulan']);
            } catch (\Throwable) {
                $bulan = null;
            }
        }
        $bulan ??= CarbonImmutable::now();

        return [$bulan->startOfMonth(), $bulan->endOfMonth(), $bulan->translatedFormat('F Y')];
    }

    public static function label(string $kategori): string
    {
        return self::LABEL[$kategori] ?? str($kategori)->headline()->toString();
    }

    private function parseTanggal(mixed $nilai): ?CarbonImmutable
    {
        if (! is_string($nilai) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai)) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('!Y-m-d', $nilai);
        } catch (\Throwable) {
            return null;
        }
    }

    private function namaBaris(OrderItem $item): string
    {
        $nama = (string) $item->nama_layanan;

        if ($nama === '') {
            return 'Layanan';
        }

        // Data lama menyimpan nilai enum mentah (cuci_ac).
        return str_contains($nama, '_') ? str($nama)->headline()->toString() : $nama;
    }
}
