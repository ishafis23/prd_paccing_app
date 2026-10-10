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
        /* Kop/footer = elemen fixed (diulang dompdf di tiap halaman) yang HARUS muat
           di dalam margin halaman: kop 2.7cm di margin atas 3.2cm, footer 0.9cm di
           margin bawah 1.8cm. Isinya div + posisi absolut (bukan tabel: tabel di
           dalam elemen fixed hilang di halaman lanjutan pada dompdf). */
        @page { margin: 3.2cm 1.5cm 1.8cm 1.5cm; }
        .lp-kop { position: fixed; top: -3.2cm; left: -1.5cm; right: -1.5cm; height: 2.7cm; }
        .lp-footer { position: fixed; bottom: -1.8cm; left: -1.5cm; right: -1.5cm; height: 0.9cm; }
    @endif
    .laporan { font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #111827; }
    .lp-kop, .lp-footer { font-family: Helvetica, Arial, sans-serif; background: {{ $biru }}; color: #ffffff; }
    .lp-kop img { position: absolute; left: 1.5cm; top: 0.55cm; height: 1.6cm; }
    .lp-kop .teks { position: absolute; left: 1.5cm; right: 1.5cm; top: 0.75cm; }
    .lp-kop .teks.dgn-logo { left: 4.3cm; }
    .lp-kop .judul { font-size: 14pt; font-weight: bold; letter-spacing: 0.3px; }
    .lp-kop .subjudul { font-size: 10pt; margin-top: 3px; }
    .lp-footer { height: 0.9cm; line-height: 0.9cm; text-align: center; font-size: 9pt; }
    .laporan .info { border-collapse: collapse; margin-bottom: 0.3cm; }
    .laporan .info td { padding: 1px 4px 1px 0; vertical-align: top; font-size: 11pt; }
    .laporan .info td.k { width: 3.6cm; font-weight: bold; }
    .laporan .kunjungan { margin-bottom: 0.6cm; }
    .laporan .kunjungan.baru { page-break-before: always; }
    .laporan .grid { width: 100%; border-collapse: collapse; }
    /* 2 kolom x 9cm (konten 18cm). Tinggi baris dikunci 7.2cm => 3 baris (6 foto)
       per halaman: 3 x 7.2 + blok info ±2.2cm < 24.7cm area isi. */
    .laporan .grid td { width: 50%; height: 7.2cm; vertical-align: top; padding: 0 0.3cm 0 0; }
    .laporan .grid td.kanan { padding: 0 0 0 0.3cm; }
    .laporan .caption, .laporan .catatan { width: 8.4cm; }
    .laporan .label { font-size: 8pt; font-weight: bold; color: #6b7280; text-transform: uppercase; margin-bottom: 2px; }
    .laporan .caption { font-size: 8pt; font-weight: bold; margin-top: 3px; }
    .laporan .catatan { font-size: 8pt; color: #b91c1c; margin-top: 2px; }
    .laporan .kosong { border: 1px dashed #9ca3af; background: #f3f4f6; color: #6b7280; text-align: center; height: 5cm; padding-top: 2.2cm; font-size: 9pt; }
    .laporan .foto { border: 1px solid #d1d5db; }
    @if (! $pdf)
        .laporan { max-width: 18cm; margin: 0 auto; background: #ffffff; box-shadow: 0 1px 6px rgba(0,0,0,.15); }
        .lp-kop { position: relative; height: 2.7cm; margin-bottom: 0.6cm; }
        .lp-footer { margin-top: 0.4cm; }
        .laporan .isi { padding: 0 1.5cm; }
        .laporan .kunjungan.baru { border-top: 2px dashed #9ca3af; padding-top: 0.5cm; page-break-before: auto; }
    @endif
</style>

{{-- PDF: kop & footer fixed HARUS anak langsung body (di dalam div pembungkus dompdf
     tidak mengulangnya di halaman lanjutan), jadi dirender SEBELUM .laporan. --}}
@if ($pdf)
    @include('laporan._kop')
    <div class="lp-footer">{{ $kop['nama'] }}</div>
@endif

<div class="laporan">
    @if (! $pdf)
        @include('laporan._kop')
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
                                        // Muat ke kotak kolom 8.4 x 5.0 cm dengan rasio aspek dijaga
                                        // (skala = min(8.4/lebar, 5.0/tinggi)); piksel hanya dipakai
                                        // untuk rasio, BUKAN ukuran cetak. Potret ekstrem dibatasi
                                        // tinggi 5.0cm (lebarnya mengecil), tidak digepengkan.
                                        $skala = min(8.4 / max(1, $f['lebar']), 5.0 / max(1, $f['tinggi']));
                                        $w = $f['lebar'] * $skala;
                                        $h = $f['tinggi'] * $skala;
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
        <div class="lp-footer">{{ $kop['nama'] }}</div>
    @endif
</div>
