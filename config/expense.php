<?php

return [
    // Revisi 14 Nov 2026 poin 4: foto bukti pengeluaran wajib saat teknisi mencatat lewat API.
    // Matikan sementara (EXPENSE_FOTO_WAJIB=false) bila app versi lama masih beredar.
    'foto_wajib' => (bool) env('EXPENSE_FOTO_WAJIB', true),
];
