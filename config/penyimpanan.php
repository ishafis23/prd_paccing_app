<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi Penyimpanan Foto (B22–B26)
|--------------------------------------------------------------------------
| Kontrol jangka panjang file foto unggahan. Nilai bisa diubah lewat .env
| tanpa mengubah kode — lalu jalankan `php artisan config:clear` bila perlu.
*/

return [
    // Disk Laravel yang diukur & dijaga kuotanya.
    'disk' => env('FOTO_DISK', 'public'),

    // Kuota maksimum pemakaian disk (dalam MB). Saat hosting diperbesar,
    // naikkan angka ini (mis. 2048, 5120, dst).
    'kuota_mb' => (int) env('FOTO_KUOTA_MB', 1024),

    // Umur maksimum foto pengerjaan (hari). Lewat dari ini, file foto pada
    // work_reports dihapus otomatis (kolom foto dikosongkan, riwayat tetap).
    // Default 60 hari (2 bulan); naikkan ke 90 (3 bulan) dst bila perlu.
    'max_umur_hari' => (int) env('FOTO_MAX_UMUR_HARI', 60),

    // Subfolder yang dibersihkan otomatis (hanya foto pengerjaan).
    'folder_foto' => 'work-reports',

    // Ambang peringatan widget (persen terpakai).
    'peringatan_persen' => 90,

    // Durasi cache ukuran terpakai (detik).
    'cache_ttl_detik' => 300,
];
