<?php

return [
    // Revisi 14 Nov 2026 poin 17: lapor kendala/pending lewat API wajib menyertakan bukti
    // (screenshot/dokumentasi) bahwa customer sudah diberi tahu. Matikan sementara bila app lama masih beredar.
    'bukti_wajib' => (bool) env('KENDALA_BUKTI_WAJIB', true),
];
