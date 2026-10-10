<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Preview {{ $dokumen['judul'] }}</title>
    <style>
        body { margin: 0; background: #e5e7eb; font-family: Helvetica, Arial, sans-serif; }
        .bar { position: sticky; top: 0; z-index: 10; background: #111827; color: #fff; padding: 10px 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap; font-size: 14px; }
        .bar a { color: #fff; background: #2563eb; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .bar a.abu { background: #4b5563; }
        .bar span { opacity: .8; }
        .halaman { padding: 16px 8px 32px; }
    </style>
</head>
<body>
    <div class="bar">
        <strong>Preview Laporan</strong>
        <span>Tampilan di bawah sama dengan isi PDF.</span>
        <a href="{{ $urlPdf }}">Unduh PDF</a>
        @if (! empty($urlKembali))
            <a class="abu" href="{{ $urlKembali }}">Kembali</a>
        @endif
    </div>
    <div class="halaman">
        @include('laporan._dokumen', ['dokumen' => $dokumen, 'mode' => 'html'])
    </div>
</body>
</html>
