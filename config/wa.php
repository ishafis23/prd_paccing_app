<?php

/**
 * Konfigurasi gateway WhatsApp (dev-plan/23, fase 23-A).
 *
 * Semua nilai RAHASIA (token/secret) hanya dari .env — JANGAN disimpan di
 * database. Base URL boleh dioverride per ProviderAccount (kolom
 * base_url_*), kalau kosong dipakai nilai di sini.
 *
 * Konsep diadopsi dari arm_app (vendor/wablasted/WablastedService.php):
 * device = satu nomor WA yang sudah discan QR; kirim teks/dokumen lewat
 * endpoint induk gateway.
 */
return [
    // Kill-switch: bila false, seluruh aksi kirim ditolak (QR/status tetap boleh).
    'aktif' => (bool) env('WA_AKTIF', false),

    // Batas kirim per menit per user (0 = tanpa batas). Dipakai fase 23-B.
    'rate_limit' => (int) env('WA_RATE_LIMIT_SEND', 10),

    // dev-plan/27: batas kirim notifikasi OTOMATIS (sistem) per menit.
    'rate_limit_sistem' => (int) env('WA_RATE_LIMIT_SISTEM', 20),

    // dev-plan/27: pemicu notifikasi otomatis ke customer.
    'notif_berangkat' => (bool) env('WA_NOTIF_BERANGKAT', true),
    'notif_lunas' => (bool) env('WA_NOTIF_LUNAS', false),
    'notif_laporan' => (bool) env('WA_NOTIF_LAPORAN', true),

    // MODE UJI: bila diisi, SEMUA notifikasi otomatis ke customer diarahkan
    // ke nomor ini (mis. nomor admin) — untuk uji coba. Kosongkan di produksi.
    'test_target' => env('WA_TEST_TARGET'),

    // Secret untuk menandatangani URL publik PDF lampiran (fase 23-C).
    'pdf_secret' => env('WA_PDF_SECRET'),

    // Token bridge api_document.php (fase 23-C).
    'document_token' => env('WA_DOCUMENT_TOKEN'),

    // Token bridge getgroupwa.php (menu "Cek ID Group WA").
    'groups_token' => env('WA_GROUPS_TOKEN'),

    // Verifikasi sertifikat SSL saat memanggil gateway. Beberapa hosting
    // tanpa CA bundle perlu false; default true demi keamanan.
    'verify_ssl' => (bool) env('WA_VERIFY_SSL', true),

    'timeout' => (int) env('WA_TIMEOUT', 30),

    // Base URL endpoint induk (fallback bila ProviderAccount tidak mengisinya).
    'base_url' => [
        'qr' => env('WA_BASE_URL_QR'),
        'send' => env('WA_BASE_URL_SEND'),
        'group' => env('WA_BASE_URL_GROUP'),
        // Bila diakhiri ".php" = bridge JSON (mis. api_document.php).
        'document' => env('WA_BASE_URL_DOCUMENT'),
        // Override endpoint reset session (opsional; default turunan document).
        'reset' => env('WA_BASE_URL_RESET'),
        // Endpoint "daftar grup WA" (bridge getgroupwa.php, menu "Cek ID Group WA").
        'groups' => env('WA_BASE_URL_GROUPS'),
    ],
];
