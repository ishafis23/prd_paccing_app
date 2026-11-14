<?php

return [
    // Revisi 14 Nov 2026 poin 13: dokumentasi video pengerjaan.
    // Catatan server: upload_max_filesize & post_max_size di php.ini HARUS >= maks_mb.
    'maks_mb' => (int) env('VIDEO_MAKS_MB', 50),
    'maks_per_order' => (int) env('VIDEO_MAKS_PER_ORDER', 10),
];
