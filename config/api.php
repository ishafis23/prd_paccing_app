<?php

return [
    /*
    | Versi minimum app mobile teknisi yang diizinkan memanggil /api/v1.
    | App mengirim header X-App-Version; versi di bawah ini ditolak 426.
    */
    'min_app_version' => env('API_MIN_APP_VERSION', '1.0.0'),

    /** Masa berlaku token login (hari). */
    'token_hari' => (int) env('API_TOKEN_HARI', 90),
];
