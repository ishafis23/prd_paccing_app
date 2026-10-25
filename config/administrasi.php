<?php

return [
    /*
     * dev-plan/29 C1 — berapa hari setelah order ditutup, tiga syarat administrasi
     * (laporan sudah dikirim ke customer, foto/keterangan wajib lengkap,
     * pengeluaran tuntas) masih menahan keberangkatan teknisi.
     *
     * Kenapa ada tenggang: teknisi menutup order di lokasi (keputusan B32), jadi
     * penutupan tidak boleh menunggu Admin mengirim laporan. Tetapi menutup order
     * tidak boleh menjadi jalan pintas — selama tenggang, order yang sudah ditutup
     * tetap diperiksa. Setelah tenggang lewat, order dianggap pekerjaan historis
     * (tidak memblokir) dan bisa dibersihkan lewat `php artisan administrasi:periksa`.
     */
    'tenggang_hari' => (int) env('ADMINISTRASI_TENGGANG_HARI', 7),
];
