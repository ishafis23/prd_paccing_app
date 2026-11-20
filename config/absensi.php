<?php

return [
    /*
    | Masukan client (20 Nov 2026): teknisi wajib absen datang hari ini sebelum
    | bisa mengakses/memulai pekerjaan (Titik 1 dst.). Matikan hanya di test.
    */
    'wajib_sebelum_berangkat' => (bool) env('ABSEN_WAJIB_SEBELUM_BERANGKAT', true),
];
