@php
    $rp = fn ($n) => number_format((float) $n, 0, ',', '.');
    $tgl = fn ($t) => \Illuminate\Support\Carbon::parse($t)->format('d/m/Y');
    $terbilang = fn ($n) => \App\Support\Terbilang::rupiah($n);
    $rincian = [
        ['judul' => 'Pengeluaran Operasional Rutin', 'kolom' => 'Operasional Rutin', 'rows' => $data['operasional']],
        ['judul' => 'Pengeluaran Material', 'kolom' => 'Pengeluaran Material', 'rows' => $data['material']],
        ['judul' => 'Pengeluaran Perawatan', 'kolom' => 'Pengeluaran Perawatan', 'rows' => $data['perawatan']],
        ['judul' => 'Omset Jasa', 'kolom' => 'Omset Jasa', 'rows' => $data['omset_jasa']],
        ['judul' => 'Omset Material', 'kolom' => 'Omset Material', 'rows' => $data['omset_material']],
    ];
    $rekap = $data['rekap'];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Bulanan Dana {{ $data['bulan'] }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 13px; margin: 0; }
        h2 { font-size: 11px; margin: 2px 0 0; }
        .meta { color: #444; margin: 2px 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 3px 5px; vertical-align: top; }
        th { background: #e7eef7; text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .kecil { font-size: 8px; font-style: italic; color: #333; }
        .halaman { page-break-after: always; }
        .halaman:last-child { page-break-after: auto; }
    </style>
</head>
<body>
@foreach ($rincian as $s)
    @php $grup = collect($s['rows'])->groupBy('tanggal'); @endphp
    <div class="halaman">
        <h1>Laporan Keuangan Bulanan {{ $namaUsaha }}</h1>
        <h2>{{ $s['judul'] }}</h2>
        <div class="meta">Periode {{ $data['bulan'] }} &middot; Dalam Rupiah (Rp)</div>
        <table>
            <thead>
                <tr>
                    <th style="width: 11%">Tanggal</th>
                    <th>Ket/Uraian</th>
                    <th style="width: 14%">{{ $s['kolom'] }}</th>
                    <th style="width: 14%">Jumlah</th>
                    <th style="width: 28%">Terbilang</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['tanggal'] as $t)
                    @php $items = $grup->get($t, collect()); $jumlah = (float) $items->sum('nominal'); @endphp
                    @forelse ($items as $i => $item)
                        <tr>
                            @if ($i === 0)
                                <td rowspan="{{ $items->count() }}">{{ $tgl($t) }}</td>
                            @endif
                            <td>{{ $item['ket'] }}</td>
                            <td class="r">{{ $rp($item['nominal']) }}</td>
                            @if ($i === 0)
                                <td class="r b" rowspan="{{ $items->count() }}">{{ $rp($jumlah) }}</td>
                                <td class="kecil" rowspan="{{ $items->count() }}">{{ $terbilang($jumlah) }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td>{{ $tgl($t) }}</td>
                            <td></td>
                            <td></td>
                            <td class="r">0</td>
                            <td class="kecil">Nol Rupiah</td>
                        </tr>
                    @endforelse
                @endforeach
                @php $total = (float) collect($s['rows'])->sum('nominal'); @endphp
                <tr class="b">
                    <td colspan="2">TOTAL</td>
                    <td class="r">{{ $rp($total) }}</td>
                    <td class="r">{{ $rp($total) }}</td>
                    <td></td>
                </tr>
                <tr>
                    <td class="b">TERBILANG</td>
                    <td colspan="4" class="kecil">{{ $terbilang($total) }}</td>
                </tr>
            </tbody>
        </table>

        @if ($loop->first && count($data['bonus']['pekerja']) > 0)
            <br>
            <h2>Bonus Pekerja yang termasuk dalam Pengeluaran Operasional Rutin</h2>
            <table style="width: auto; margin-top: 4px;">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        @foreach ($data['bonus']['pekerja'] as $nama)
                            <th>{{ $nama }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['bonus']['baris'] as $t => $per)
                        <tr>
                            <td>{{ $tgl($t) }}</td>
                            @foreach ($data['bonus']['pekerja'] as $nama)
                                <td class="r">{{ isset($per[$nama]) ? $rp($per[$nama]) : '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr class="b">
                        <td>TOTAL</td>
                        @foreach ($data['bonus']['pekerja'] as $nama)
                            <td class="r">{{ $rp(collect($data['bonus']['baris'])->sum(fn ($p) => $p[$nama] ?? 0)) }}</td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        @endif
    </div>
@endforeach

<div class="halaman">
    <h1>Laporan Keuangan Bulanan {{ $namaUsaha }}</h1>
    <h2>Total Pengeluaran &amp; Pendapatan</h2>
    <div class="meta">Periode {{ $data['bulan'] }} &middot; Dalam Rupiah (Rp)</div>
    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width: 8%">Tanggal</th>
                <th colspan="6">PENGELUARAN</th>
                <th colspan="3">PENDAPATAN</th>
            </tr>
            <tr>
                <th>Operasional Rutin</th>
                <th>Pengeluaran Material</th>
                <th>Pengeluaran Perawatan</th>
                <th>Gaji/Honor</th>
                <th>Nama</th>
                <th>Jumlah</th>
                <th>Omset Jasa</th>
                <th>Omset Material</th>
                <th>Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rekap['baris'] as $b)
                <tr>
                    <td>{{ $tgl($b['tanggal']) }}</td>
                    <td class="r">{{ $rp($b['operasional']) }}</td>
                    <td class="r">{{ $rp($b['material']) }}</td>
                    <td class="r">{{ $rp($b['perawatan']) }}</td>
                    <td class="r">{{ $rp($b['gaji']) }}</td>
                    <td>{{ $b['nama'] }}</td>
                    <td class="r b">{{ $rp($b['pengeluaran']) }}</td>
                    <td class="r">{{ $rp($b['omset_jasa']) }}</td>
                    <td class="r">{{ $rp($b['omset_material']) }}</td>
                    <td class="r b">{{ $rp($b['pendapatan']) }}</td>
                </tr>
            @endforeach
            @php $t = $rekap['total']; @endphp
            <tr class="b">
                <td>TOTAL</td>
                <td class="r">{{ $rp($t['operasional']) }}</td>
                <td class="r">{{ $rp($t['material']) }}</td>
                <td class="r">{{ $rp($t['perawatan']) }}</td>
                <td class="r">{{ $rp($t['gaji']) }}</td>
                <td></td>
                <td class="r">{{ $rp($t['pengeluaran']) }}</td>
                <td class="r">{{ $rp($t['omset_jasa']) }}</td>
                <td class="r">{{ $rp($t['omset_material']) }}</td>
                <td class="r">{{ $rp($t['pendapatan']) }}</td>
            </tr>
            <tr>
                <td class="b">JUMLAH</td>
                <td colspan="6" class="r b">Pengeluaran: {{ $rp($t['pengeluaran']) }}</td>
                <td colspan="3" class="r b">Pendapatan: {{ $rp($t['pendapatan']) }}</td>
            </tr>
            <tr>
                <td class="b">LABA</td>
                <td colspan="9" class="b">{{ $rp($rekap['laba']) }} &mdash; <span class="kecil">{{ $terbilang($rekap['laba']) }}</span></td>
            </tr>
        </tbody>
    </table>
</div>
</body>
</html>
