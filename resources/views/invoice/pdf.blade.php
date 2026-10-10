<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Faktur {{ $inv['nomor'] }}</title>
</head>
<body style="margin: 0;">
    {{-- Kop biru + footer (fixed, diulang tiap halaman) datang dari laporan._dokumen
         — dipakai ulang apa adanya. Faktur di depan, lampiran laporan foto di belakang;
         tiap kunjungan laporan sudah mulai di halaman baru. --}}
    <div style="font-family: Helvetica, Arial, sans-serif; {{ ! empty($dokumen['kunjungan']) ? 'page-break-after: always;' : '' }}">
        @include('invoice._faktur', ['inv' => $inv])
    </div>

    @include('laporan._dokumen', ['dokumen' => $dokumen, 'mode' => 'pdf'])
</body>
</html>
