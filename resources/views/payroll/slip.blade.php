@php
    $pdf = $pdf ?? false;
    $bisnis = app(\App\Services\BusinessInfoService::class);
    $namaUsaha = $bisnis->namaUsaha();
    $rp = fn ($n) => 'Rp '.number_format((float) $n, 0, ',', '.');
    $b = \App\Enums\PayrollItemJenis::Bonus;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Slip Gaji {{ $run->user?->name }} — {{ $run->periode_mulai->format('d M Y') }}</title>
    <style>
        body { margin: 0; background: #e5e7eb; font-family: Helvetica, Arial, sans-serif; color: #111827; }
        .bar { background: #111827; color: #fff; padding: 10px 16px; display: flex; gap: 10px; align-items: center; font-size: 14px; }
        .bar a { color: #fff; background: #2563eb; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .kertas { max-width: 19cm; margin: 16px auto; background: #fff; padding: 1.2cm; box-shadow: 0 1px 6px rgba(0,0,0,.15); box-sizing: border-box; }
        h1 { font-size: 16pt; margin: 0 0 2px; }
        .muted { color: #6b7280; font-size: 11pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 14px; font-size: 11pt; }
        th, td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; text-align: left; }
        th { background: #f3f4f6; font-size: 9pt; text-transform: uppercase; letter-spacing: .03em; color: #6b7280; }
        td.angka { text-align: right; white-space: nowrap; }
        tr.total td { border-top: 2px solid #111827; font-weight: bold; font-size: 12pt; }
        .info td { border: none; padding: 2px 6px 2px 0; }
        tr.grup td { background: #f3f4f6; font-size: 9pt; font-weight: bold; letter-spacing: .04em; color: #374151; }
        tr.sub td { font-weight: bold; border-top: 1px solid #9ca3af; }
        .bonus { color: #047857; }
        .potongan { color: #b91c1c; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #dbeafe; color: #1e40af; font-size: 9pt; font-weight: bold; }
    </style>
</head>
<body>
@unless ($pdf)
    <div class="bar">
        <strong>Slip Gaji {{ $run->user?->name }}</strong>
        @if (! empty($urlPdf))
            <a href="{{ $urlPdf }}">Unduh PDF</a>
        @endif
        <a href="javascript:window.print()" style="background:#4b5563">Cetak</a>
    </div>
@endunless

<div class="kertas">
    <div style="text-align:center; border-bottom: 2px solid #1e40af; padding-bottom: 8px;">
        <h1>{{ $namaUsaha }}</h1>
        <p class="muted" style="margin:0;">SLIP GAJI TEKNISI</p>
    </div>

    <table class="info" style="margin-top:12px;">
        <tr><td style="width:140px;" class="muted">Nama</td><td><strong>{{ $run->user?->name }}</strong></td>
            <td style="width:120px;" class="muted">Periode</td><td>{{ $run->periode_mulai->format('d M Y') }} – {{ $run->periode_selesai->format('d M Y') }}</td></tr>
        <tr><td class="muted">Status</td><td><span class="badge">{{ $run->status->label() }}</span></td>
            <td class="muted">Tgl ACC</td><td>{{ $run->disetujui_pada?->format('d M Y') ?? '—' }}</td></tr>
    </table>

    @php
        $bonusItems = $run->items->filter(fn ($i) => $i->jenis === $b && $i->kategori !== 'bonus_mingguan');
        $mingguan = $run->items->firstWhere('kategori', 'bonus_mingguan');
        $potItems = $run->items->filter(fn ($i) => $i->jenis !== $b && $i->kategori !== 'dibayar_mingguan');
        $dibayarMingguan = $run->items->firstWhere('kategori', 'dibayar_mingguan');
        $kuota = \App\Services\PayrollService::KUOTA_OFF;
        $totalBonus = $bonusItems->sum('nominal');
        $totalPot = $potItems->sum('nominal');
    @endphp
    <table>
        <thead>
            <tr><th style="width:42%">Komponen</th><th style="width:26%">Keterangan</th><th style="width:32%;text-align:right;">Jumlah</th></tr>
        </thead>
        <tbody>
            <tr class="grup"><td colspan="3">PENDAPATAN</td></tr>
            <tr><td>Gaji Pokok</td><td class="muted">per periode</td><td class="angka">{{ $rp($run->gaji_pokok) }}</td></tr>
            @forelse ($bonusItems as $item)
                <tr><td>{{ $item->label }}</td><td class="muted">bonus</td><td class="angka bonus">+ {{ $rp($item->nominal) }}</td></tr>
            @empty
                <tr><td class="muted">Bonus</td><td class="muted">—</td><td class="angka muted">{{ $rp(0) }}</td></tr>
            @endforelse
            <tr class="sub"><td colspan="2">Total Pendapatan</td><td class="angka">{{ $rp((float) $run->gaji_pokok + $totalBonus) }}</td></tr>

            <tr class="grup"><td colspan="3">POTONGAN</td></tr>
            <tr>
                <td>Denda keterlambatan</td>
                <td class="muted">{{ $run->jumlah_hari_telat }} hari</td>
                <td class="angka potongan">− {{ $rp($run->total_denda_telat) }}</td>
            </tr>
            <tr>
                <td>Potongan OFF</td>
                <td class="muted">{{ $run->jumlah_hari_off }} hari (jatah {{ $kuota }})</td>
                <td class="angka potongan">− {{ $rp($run->total_potongan_off) }}</td>
            </tr>
            <tr>
                <td>Pulang cepat / setengah hari</td>
                <td class="muted">{{ $run->jumlah_setengah_hari }}×</td>
                <td class="angka potongan">− {{ $rp($run->total_potongan_setengah_hari) }}</td>
            </tr>
            <tr>
                <td>BPJS TK</td>
                <td class="muted">—</td>
                <td class="angka potongan">− {{ $rp($run->potongan_bpjs_tk) }}</td>
            </tr>
            <tr class="sub"><td colspan="2">Total Potongan</td><td class="angka">− {{ $rp((float) $run->total_denda_telat + (float) $run->total_potongan_off + (float) $run->total_potongan_setengah_hari + (float) $run->potongan_bpjs_tk) }}</td></tr>

            @if ($mingguan)
                <tr class="grup"><td colspan="3">BONUS MINGGUAN (SUDAH DIBAYAR PEKANAN)</td></tr>
                <tr><td>{{ $mingguan->label }}</td><td class="muted">info</td><td class="angka">{{ $rp($mingguan->nominal) }}</td></tr>
            @endif

            <tr class="total">
                <td colspan="2">TOTAL GAJI DITERIMA</td>
                <td class="angka">{{ $rp($run->gaji_bersih) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($run->catatan)
        <p class="muted" style="margin-top:12px;">Catatan: {{ $run->catatan }}</p>
    @endif

    <p class="muted" style="margin-top:24px; font-size:9pt;">
        Slip ini dibuat otomatis. Bonus mingguan (Games 4,5,6) ditampilkan sebagai info & pengurang karena telah dibayarkan per pekan.
    </p>
</div>
</body>
</html>
