@php
    /**
     * Isi FAKTUR — dipakai bersama halaman publik (HTML) dan PDF (dompdf).
     * Hanya CSS 2.1 + tabel. Gaya mengikuti contoh klien (INV110037).
     *
     * @var array<string, mixed> $inv  keluaran InvoiceService::tampilan()
     */
    $uang = fn (float $n): string => number_format($n, 2, '.', ',');
    $kop = $inv['kop'];
    $bank = $inv['bank'];
    $adaBank = filled($bank['nama']) || filled($bank['rekening']) || filled($bank['atas_nama']);
    $tgl = fn ($t): string => $t ? $t->format('d-m-y') : '—';
@endphp
<style>
    .fk { font-family: Helvetica, Arial, sans-serif; font-size: 9.5pt; color: #1f2937; }
    .fk table { border-collapse: collapse; width: 100%; }
    .fk .usaha { text-align: right; font-size: 9pt; line-height: 1.35; }
    .fk .usaha b { font-size: 11pt; }
    .fk .judul td { padding: 0.45cm 0 0.35cm 0; }
    .fk .garis { border-bottom: 1px solid #d1d5db; width: 45%; }
    .fk .judul .t { width: 3.2cm; text-align: center; font-size: 12pt; color: #374151; }
    .fk .cust { font-weight: bold; font-size: 10.5pt; vertical-align: top; }
    .fk .meta td { padding: 2px 0 2px 0.4cm; vertical-align: top; }
    .fk .meta td.k { font-weight: bold; width: 3.6cm; }
    .fk .items { margin-top: 0.6cm; border: 1px solid #d1d5db; }
    .fk .items th { background: #f3f4f6; font-size: 8.5pt; padding: 5px 6px; text-align: left; border-bottom: 1px solid #d1d5db; }
    .fk .items td { padding: 6px; vertical-align: top; border-bottom: 1px solid #e5e7eb; }
    .fk .items .n { text-align: right; white-space: nowrap; }
    .fk .tot td { padding: 4px 6px; }
    .fk .tot .k { text-align: right; width: 4.4cm; }
    .fk .tot .v { text-align: right; width: 3.6cm; white-space: nowrap; }
    .fk .tot .kuat td { font-weight: bold; }
    .fk .tot .saldo td { font-weight: bold; font-size: 10.5pt; background: #f3f4f6; border-top: 1px solid #d1d5db; }
    .fk .catatan { font-size: 9pt; color: #374151; }
    .fk .bank { margin-top: 1cm; font-size: 9pt; }
    .fk .batal { color: #b91c1c; font-weight: bold; }
</style>

<div class="fk">
    <table>
        <tr>
            <td style="width: 50%;"></td>
            <td class="usaha">
                <b>{{ $kop['nama'] }}</b><br>
                @if ($kop['alamat']) {!! nl2br(e($kop['alamat'])) !!}<br> @endif
                @if ($kop['kontak_wa']) {{ $kop['kontak_wa'] }} @endif
            </td>
        </tr>
    </table>

    <table class="judul">
        <tr>
            <td class="garis"></td>
            <td class="t">FAKTUR</td>
            <td class="garis"></td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="cust" style="width: 50%;">{{ mb_strtoupper($inv['customer']) }}</td>
            <td>
                <table class="meta">
                    <tr><td class="k">No. Invoice</td><td>{{ $inv['nomor'] }}</td></tr>
                    <tr><td class="k">Tanggal Faktur</td><td>{{ $tgl($inv['tanggal']) }}</td></tr>
                    @if ($inv['ketentuan'])
                        <tr><td class="k">Ketentuan</td><td>{{ $inv['ketentuan'] }}</td></tr>
                    @endif
                    <tr><td class="k">Tanggal Jatuh Tempo</td><td>{{ $tgl($inv['jatuh_tempo']) }}</td></tr>
                    @if ($inv['status'] === \App\Enums\InvoiceStatus::Batal)
                        <tr><td class="k">Status</td><td class="batal">DIBATALKAN</td></tr>
                    @elseif ($inv['status'] === \App\Enums\InvoiceStatus::Lunas)
                        <tr><td class="k">Status</td><td><b>LUNAS</b></td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 0.8cm;">#</th>
                <th style="width: 3.6cm;">Item</th>
                <th>Deskripsi</th>
                <th class="n" style="width: 1.5cm; text-align: right;">Jml</th>
                <th class="n" style="width: 2.6cm; text-align: right;">Tarif</th>
                <th class="n" style="width: 2.8cm; text-align: right;">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($inv['baris'] as $i => $b)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $b->nama }}</td>
                    <td>{!! nl2br(e((string) $b->deskripsi)) !!}</td>
                    <td class="n">{{ $uang((float) $b->jumlah) }}</td>
                    <td class="n">{{ $uang((float) $b->harga) }}</td>
                    <td class="n">{{ $uang((float) $b->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 0.2cm;">
        <tr>
            <td class="catatan" style="vertical-align: top;">
                Terima kasih telah berbisnis dengan kami.
                @if ($inv['catatan'])
                    <br><br>{!! nl2br(e($inv['catatan'])) !!}
                @endif
            </td>
            <td style="width: 8.4cm;">
                <table class="tot">
                    <tr><td class="k">Sub Total</td><td class="v">{{ $uang($inv['subtotal']) }}</td></tr>
                    <tr class="kuat"><td class="k">Total</td><td class="v">IDR{{ $uang($inv['total']) }}</td></tr>
                    @if ($inv['dibayar'] > 0)
                        <tr><td class="k">Sudah Dibayar</td><td class="v">(-) {{ $uang($inv['dibayar']) }}</td></tr>
                    @endif
                    <tr class="saldo"><td class="k">Saldo Jatuh Tempo</td><td class="v">IDR{{ $uang($inv['saldo']) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="bank">
        @if ($adaBank)
            Pembayaran dapat dilakukan Via Transfer melalui rekening
            {{ trim(($bank['nama'] ?? '').' '.($bank['rekening'] ?? '')) }}@if (filled($bank['atas_nama'])) a.n {{ $bank['atas_nama'] }}@endif
        @else
            <i>Informasi rekening pembayaran belum diisi (menu Manajemen > Info Usaha).</i>
        @endif
    </div>
</div>
