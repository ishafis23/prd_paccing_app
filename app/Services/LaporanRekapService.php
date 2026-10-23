<?php

namespace App\Services;

use App\Enums\IncentiveStatusVerifikasi;
use App\Enums\IncentiveTipe;
use App\Models\Order;
use App\Models\PayrollRun;
use App\Models\TechnicianIncentive;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Menyusun data "Rekap Bulanan Dana" (dev-plan/laporan rekap) dari angka yang
 * sama dgn halaman Akuntan — sumber tunggalnya AkuntanService, jadi total
 * laporan selalu cocok dgn layar. Read-only.
 *
 * Pengeluaran Teknisi kategori "material" masuk Material; kategori teknisi
 * lain (bensin, makan, transport, lainnya) masuk Operasional Rutin.
 * Gaji/Honor = payroll_runs yg dibayar pada bulan tsb (tgl dibayar_pada).
 * Bonus hanya tabel info di sheet Operasional (tidak menambah total).
 */
class LaporanRekapService
{
    public function __construct(private readonly AkuntanService $akuntan) {}

    /**
     * @return array<string, mixed>
     */
    public function susun(CarbonInterface $bulan): array
    {
        $awal = CarbonImmutable::parse($bulan)->startOfMonth();
        $akhir = $awal->endOfMonth();

        $tanggal = [];
        for ($d = $awal; $d <= $akhir; $d = $d->addDay()) {
            $tanggal[] = $d->toDateString();
        }

        $pengeluaran = $this->akuntan->pengeluaran($awal, $akhir)->where('dihitung', true);
        $baris = fn (Collection $rows): array => $rows
            ->map(fn (array $r): array => [
                'tanggal' => $r['tanggal'],
                'ket' => trim(($r['sumber'] === 'Teknisi' ? $r['pelaku'].' - ' : '').$r['keterangan'], ' -'),
                'nominal' => (float) $r['nominal'],
            ])
            ->sortBy('tanggal')->values()->all();

        $material = $pengeluaran->filter(fn (array $r): bool => $r['kategori'] === 'material');
        $perawatan = $pengeluaran->filter(fn (array $r): bool => $r['kategori'] === 'perawatan');
        $operasional = $pengeluaran->reject(fn (array $r): bool => in_array($r['kategori'], ['material', 'perawatan'], true));

        $pendapatan = $this->akuntan->pendapatan($awal, $akhir);
        $tim = $this->namaTim($pendapatan->pluck('order_id')->all());
        $omset = fn (string $kolom): array => $pendapatan
            ->filter(fn (array $r): bool => $r[$kolom] > 0)
            ->map(fn (array $r): array => [
                'tanggal' => $r['tanggal'],
                'ket' => trim(($tim[$r['order_id']] ?? '').' - '.$r['customer'], ' -'),
                'nominal' => (float) $r[$kolom],
            ])
            ->sortBy('tanggal')->values()->all();

        $data = [
            'bulan' => $awal->translatedFormat('F Y'),
            'bulan_ym' => $awal->format('Y-m'),
            'tanggal' => $tanggal,
            'operasional' => $baris($operasional),
            'material' => $baris($material),
            'perawatan' => $baris($perawatan),
            'omset_jasa' => $omset('total_jasa'),
            'omset_material' => $omset('total_material'),
            'gaji' => $this->gaji($awal, $akhir),
            'bonus' => $this->bonus($awal, $akhir),
        ];

        $data['rekap'] = $this->rekap($data);

        return $data;
    }

    /**
     * Satu baris per tanggal + total + laba.
     *
     * @param  array<string, mixed>  $data
     * @return array{baris: array<int, array<string, mixed>>, total: array<string, float>, laba: float}
     */
    private function rekap(array $data): array
    {
        $per = fn (array $rows): array => collect($rows)->groupBy('tanggal')->map(fn (Collection $g) => (float) $g->sum('nominal'))->all();
        $op = $per($data['operasional']);
        $mat = $per($data['material']);
        $raw = $per($data['perawatan']);
        $gaji = $per($data['gaji']);
        $jasa = $per($data['omset_jasa']);
        $omat = $per($data['omset_material']);
        $namaGaji = collect($data['gaji'])->groupBy('tanggal')->map(fn (Collection $g) => $g->pluck('ket')->implode(', '))->all();

        $baris = [];
        foreach ($data['tanggal'] as $t) {
            $row = [
                'tanggal' => $t,
                'operasional' => $op[$t] ?? 0.0,
                'material' => $mat[$t] ?? 0.0,
                'perawatan' => $raw[$t] ?? 0.0,
                'gaji' => $gaji[$t] ?? 0.0,
                'nama' => $namaGaji[$t] ?? '',
                'omset_jasa' => $jasa[$t] ?? 0.0,
                'omset_material' => $omat[$t] ?? 0.0,
            ];
            $row['pengeluaran'] = $row['operasional'] + $row['material'] + $row['perawatan'] + $row['gaji'];
            $row['pendapatan'] = $row['omset_jasa'] + $row['omset_material'];
            $baris[] = $row;
        }

        $total = [];
        foreach (['operasional', 'material', 'perawatan', 'gaji', 'pengeluaran', 'omset_jasa', 'omset_material', 'pendapatan'] as $k) {
            $total[$k] = (float) array_sum(array_column($baris, $k));
        }

        return ['baris' => $baris, 'total' => $total, 'laba' => $total['pendapatan'] - $total['pengeluaran']];
    }

    /**
     * @return array<int, array{tanggal: string, ket: string, nominal: float}>
     */
    private function gaji(CarbonImmutable $awal, CarbonImmutable $akhir): array
    {
        return PayrollRun::query()
            ->whereNotNull('dibayar_pada')
            ->whereBetween('dibayar_pada', [$awal->startOfDay(), $akhir->endOfDay()])
            ->with('user')
            ->get()
            ->map(fn (PayrollRun $p): array => [
                'tanggal' => $p->dibayar_pada->toDateString(),
                'ket' => $p->user?->name ?? '-',
                'nominal' => (float) $p->gaji_bersih,
            ])
            ->sortBy('tanggal')->values()->all();
    }

    /**
     * Bonus per pekerja per tanggal (bonus saja; denda & entri ditolak tidak ikut, sama dgn hitungan gaji).
     *
     * @return array{pekerja: array<int, string>, baris: array<string, array<string, float>>}
     */
    private function bonus(CarbonImmutable $awal, CarbonImmutable $akhir): array
    {
        $rows = TechnicianIncentive::query()
            ->where('tipe', IncentiveTipe::Bonus->value)
            ->whereBetween('tanggal', [$awal->toDateString(), $akhir->toDateString()])
            ->where('status_verifikasi', '!=', IncentiveStatusVerifikasi::Ditolak->value)
            ->with('user')
            ->get();

        $baris = [];
        foreach ($rows as $r) {
            $nama = $r->user?->name ?? '-';
            $t = $r->tanggal->toDateString();
            $baris[$t][$nama] = ($baris[$t][$nama] ?? 0.0) + (float) $r->nominal;
        }

        $pekerja = collect($baris)->flatMap(fn (array $p) => array_keys($p))->unique()->sort()->values()->all();

        return ['pekerja' => $pekerja, 'baris' => $baris];
    }

    /**
     * @param  array<int, int>  $orderIds
     * @return array<int, string> order_id => "HUSAEN & BAYU"
     */
    private function namaTim(array $orderIds): array
    {
        if ($orderIds === []) {
            return [];
        }

        return Order::query()
            ->whereIn('id', $orderIds)
            ->with(['teknisi', 'timTeknisi'])
            ->get()
            ->mapWithKeys(function (Order $o): array {
                $anggota = $o->timTeknisi->isNotEmpty() ? $o->timTeknisi : collect([$o->teknisi])->filter();

                return [$o->id => mb_strtoupper($anggota->pluck('name')->implode(' & '))];
            })
            ->all();
    }
}
