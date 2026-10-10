@php
    /**
     * Isi laporan — DIPAKAI BERSAMA oleh preview HTML dan PDF (dompdf), supaya
     * keduanya sama. Hanya CSS 2.1 + tabel (dompdf tidak mengenal flex/grid).
     *
     * @var array<string, mixed> $dokumen  keluaran LaporanPengerjaanService::dokumen()
     * @var string $mode  'pdf' | 'html'
     */
    $svc = app(\App\Services\LaporanPengerjaanService::class);
    $kop = $dokumen['kop'];
    $logo = $svc->dataUri($kop['logo_file']);
    $pdf = $mode === 'pdf';
    $biru = '#1e40af';
@endphp
<style>
    @if ($pdf)
        @page { margin: 3.4cm 1.5cm 1.9cm 1.5cm; }
        .kop { position: fixed; top: -3.1cm; left: -1.5cm; right: -1.5cm; height: 2.6cm; }
        .footer { position: fixed; bottom: -1.5cm; left: -1.5cm; right: -1.5cm; height: 1cm; }
    @endif
    .laporan { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #111827; }
    .laporan .kop, .laporan .footer { background: {{ $biru }}; color: #ffffff; }
    .laporan .kop table { width: 100%; border-collapse: collapse; }
    .laporan .kop td { vertical-align: middle; padding: 0.35cm 1.5cm; }
    .laporan .kop img { height: 1.6cm; }
    .laporan .judul { font-size: 14pt; font-weight: bold; letter-spacing: 0.3px; }
    .laporan .subjudul { font-size: 10pt; margin-top: 3px; }
    .laporan .footer { text-align: center; font-size: 9pt; padding: 0.3cm 0; }
    .laporan .info { border-collapse: collapse; margin-bottom: 0.4cm; }
    .laporan .info td { padding: 1px 4px 1px 0; vertical-align: top; font-size: 11pt; }
    .laporan .info td.k { width: 3.6cm; font-weight: bold; }
    .laporan .kunjungan { margin-bottom: 0.6cm; }
    .laporan .kunjungan.baru { page-break-before: always; }
    .laporan .grid { width: 100%; border-collapse: collapse; }
    .laporan .grid td { width: 50%; vertical-align: top; padding: 0 0.25cm 0.4cm 0; }
    .laporan .grid td.kanan { padding: 0 0 0.4cm 0.25cm; }
    .laporan .label { font-size: 8pt; font-weight: bold; color: #6b7280; text-transform: uppercase; margin-bottom: 2px; }
    .laporan .caption { font-size: 8pt; font-weight: bold; margin-top: 3px; }
    .laporan .catatan { font-size: 8pt; color: #b91c1c; margin-top: 2px; }
    .laporan .kosong { border: 1px dashed #9ca3af; background: #f3f4f6; color: #6b7280; text-align: center; height: 3.4cm; padding-top: 1.3cm; font-size: 9pt; }
    .laporan .foto { border: 1px solid #d1d5db; }
    @if (! $pdf)
        .laporan { max-width: 18cm; margin: 0 auto; background: #ffffff; box-shadow: 0 1px 6px rgba(0,0,0,.15); }
        .laporan .kop { margin-bottom: 0.6cm; }
        .laporan .footer { margin-top: 0.4cm; }
        .laporan .isi { padding: 0 1.5cm; }
        .laporan .kunjungan.baru { border-top: 2px dashed #9ca3af; padding-top: 0.5cm; page-break-before: auto; }
    @endif
</style>

<div class="laporan">
    <div class="kop">
        <table>
            <tr>
                @if ($logo)
                    <td style="width: 2.4cm;"><img src="{{ $logo }}" alt=""></td>
                @endif
                <td>
                    <div class="judul">{{ $dokumen['judul'] }}</div>
                    @if (! empty($dokumen['subjudul']))
                        <div class="subjudul">{{ $dokumen['subjudul'] }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    @if ($pdf)
        <div class="footer">{{ $kop['nama'] }}</div>
    @endif

    <div class="isi">
        @foreach ($dokumen['kunjungan'] as $k)
            @php
                $fotos = collect($k['unit'])->flatMap(fn (array $u) => collect($u['foto'])->map(fn (array $f) => $f + ['catatan' => $u['catatan']]));
            @endphp
            <div class="kunjungan {{ $loop->first ? '' : 'baru' }}">
                <table class="info">
                    @if ($k['cabang'])
                        <tr><td class="k">Cabang</td><td>: {{ $k['cabang'] }}</td></tr>
                    @endif
                    @if ($k['tanggal_label'])
                        <tr><td class="k">Hari/Tanggal</td><td>: {{ $k['tanggal_label'] }}</td></tr>
                    @endif
                    <tr><td class="k">Keterangan</td><td>: {{ $k['keterangan'] }}</td></tr>
                </table>

                <table class="grid">
                    @foreach ($fotos->chunk(2) as $pasang)
                        <tr>
                            @foreach ($pasang->values() as $i => $f)
                                @php
                                    $src = $f['ada'] ? $svc->dataUri($f['file']) : null;
                                    if ($src) {
                                        $r = $f['lebar'] / max(1, $f['tinggi']);
                                        $w = 8.4;
                                        $h = $w / $r;
                                        if ($h > 6.2) { $h = 6.2; $w = $h * $r; }
                                    }
                                @endphp
                                <td class="{{ $i === 1 ? 'kanan' : '' }}">
                                    @if (! empty($f['label']))
                                        <div class="label">{{ $f['label'] }}</div>
                                    @endif
                                    @if ($src)
                                        <img class="foto" src="{{ $src }}" alt="" style="width: {{ number_format($w, 2, '.', '') }}cm; height: {{ number_format($h, 2, '.', '') }}cm;">
                                    @else
                                        <div class="kosong">Foto belum diunggah</div>
                                    @endif
                                    @if ($f['caption'] !== '')
                                        <div class="caption">{{ $f['caption'] }}</div>
                                    @endif
                                    @if (! empty($f['catatan']))
                                        <div class="catatan">Catatan: {{ $f['catatan'] }}</div>
                                    @endif
                                </td>
                            @endforeach
                            @if ($pasang->count() === 1)
                                <td class="kanan"></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            </div>
        @endforeach
    </div>

    @if (! $pdf)
        <div class="footer">{{ $kop['nama'] }}</div>
    @endif
</div>
