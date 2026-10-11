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

    <table>
        <thead>
            <tr><th>Keterangan</th><th style="text-align:right;">Jumlah</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>Gaji Pokok</td>
                <td class="angka">{{ $rp($run->gaji_pokok) }}</td>
            </tr>
            @foreach ($run->items as $item)
                @php $isBonus = $item->jenis === $b; @endphp
                <tr>
                    <td>{{ $item->label }}</td>
                    <td class="angka {{ $isBonus ? 'bonus' : 'potongan' }}">
                        {{ $isBonus ? '+' : '−' }} {{ $rp($item->nominal) }}
                    </td>
                </tr>
            @endforeach
            <tr class="total">
                <td>GAJI BERSIH</td>
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
