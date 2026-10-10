<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $dokumen['judul'] }}</title>
</head>
<body style="margin: 0;">
    @include('laporan._dokumen', ['dokumen' => $dokumen, 'mode' => 'pdf'])
</body>
</html>
