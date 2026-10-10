<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Invoice {{ $inv['nomor'] }} — {{ $inv['kop']['nama'] }}</title>
    <style>
        body { margin: 0; background: #e5e7eb; font-family: Helvetica, Arial, sans-serif; }
        .bar { background: #111827; color: #fff; padding: 10px 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; font-size: 14px; }
        .bar a { color: #fff; background: #2563eb; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-weight: bold; }
        .bar a.abu { background: #4b5563; }
        .kertas { max-width: 19cm; margin: 16px auto; background: #fff; padding: 1.2cm; box-shadow: 0 1px 6px rgba(0,0,0,.15); box-sizing: border-box; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="bar">
        <strong>Invoice {{ $inv['nomor'] }}</strong>
        <a href="{{ $urlPdf }}">Unduh PDF</a>
        <a class="abu" href="{{ $urlPdfLampiran }}">Unduh PDF + Laporan Pengerjaan</a>
    </div>
    <div class="kertas">
        @include('invoice._faktur', ['inv' => $inv])
    </div>
</body>
</html>
