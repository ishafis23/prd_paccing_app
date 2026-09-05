# Keputusan Eksekusi — One Gate System Paccing

Dokumen ini mencatat **jawaban final** atas semua pertanyaan terbuka di
[`../PRD.md`](../PRD.md) §12, [`00-overview.md`](00-overview.md), dan bagian
"Pertanyaan Terbuka" tiap dokumen modul. Disetujui oleh pemilik proyek pada
6 September 2026. Dokumen ini menjadi acuan saat implementasi; jika ada
perubahan keputusan, catat di sini dengan tanggal revisi.

## Keputusan Bisnis

| # | Pertanyaan (sumber) | Keputusan | Berlaku |
|---|---|---|---|
| 1 | Multi-item order (00-overview, schema §orders) | **TIDAK di Fase 1.** 1 order = 1 jenis layanan; `jumlah_unit` > 1 untuk unit sejenis. Tabel `order_items` ditunda Fase 2. | Fase 1 |
| 2 | Interval reminder cuci rutin (PRD §12, schema `service_reminders`) | Kolom `interval_bulan` per item di `service_catalog`. Default: cuci_ac = 3, service_ac = null, pengadaan_ac = null. | Fase 1 |
| 3 | Notifikasi WA otomatis (PRD §12, Admin §7) | **TIDAK di Fase 1.** Cukup notice dashboard admin via `service_reminders.status_notice`. Integrasi WA API (Fonnte/Wablas) Fase 2. | Fase 1 |
| 4 | Kebijakan stok minus (Admin §7, Teknisi §7) | Laporan teknisi **tetap boleh disubmit** walau `stok_saat_ini` kurang; stok boleh negatif dan muncul di alert restock admin. | Fase 1 |
| 5 | Pengakuan income dari DP (Finance §7) | Income dicatat **hanya saat `payments.status` = `lunas`** (cash basis sederhana). DP disimpan di `payments.jumlah_dibayar` tanpa trigger income. | Fase 1 |
| 6 | Harga nego per customer (Admin §7) | Harga order **mengikuti `service_catalog`**; tanpa fitur nego di Fase 1. | Fase 1 |
| 7 | Role aktif Fase 1 (PRD §3, 00-overview) | 5 role dibuat via `spatie/laravel-permission`, mendukung multi-role per user. Finance & HR dirangkap Owner/Admin. Seed 1 akun owner. | Fase 1 |
| 8 | Alur order `butuh_followup` (Teknisi §7) | Order **ditahan** pada status yang sama; teknisi dapat melanjutkan order yang sama (bukan membuat order baru). | Fase 1 |
| 9 | Satu order banyak teknisi (Teknisi §7) | **1 PIC teknisi** per order (`orders.teknisi_id`). Relasi many-to-many `order_technicians` ditunda Fase 2. | Fase 1 |
| 10 | Absensi karyawan non-lapangan (HRD §7) | Fase 1 hanya check-in/out teknisi di lokasi (terhubung `order_id`). Absen karyawan kantor masuk HRD Fase 2. | Fase 1 |
| 11 | Stok & lini pengadaan AC (PRD §12) | `stock_items` + `stock_movements` aktif Fase 1 (dipakai laporan teknisi & restock). Unit AC pengadaan dicatat sebagai transaksi order biasa; inventory/order_items penuh Fase 2. | Fase 1 |
| 12 | Skala & estimasi data | Asumsi UKM: 3–10 teknisi, ratusan customer, order puluhan/bulan. Arsitektur tidak berubah jika membesar; hanya volume. | Fase 1 |

## Keputusan Teknis

| # | Pertanyaan | Keputusan |
|---|---|---|
| T1 | Hosting akhir | VPS entry-level (mendukung Laravel Task Scheduler untuk reminder & queue). Deployment detail menyusul; tidak menghalangi development lokal. |
| T2 | UI toolkit | **Hybrid**: Laravel 12 + Filament 3 panel untuk backoffice (Owner/Admin/Finance/HR) + halaman mobile-first (Blade + Alpine/Livewire) khusus Teknisi: slider "mulai berangkat", check-in/out, form laporan + foto. |
| T3 | Database development | SQLite untuk dev & test lokal (cepat, tanpa dependensi server); production tetap MySQL. Migrasi ditulis portable (tanpa fitur spesifik DB). |

## Konvensi Testing (wajib, per arahan Lead QA)

1. Setiap pembuatan/ubahan kode WAJIB disertai Unit/Feature Test (Pest).
2. Setiap selesai perubahan, jalankan `php artisan test` sampai hijau (PASS).
3. Error/FAIL diperbaiki oleh engineer, bukan di-skip.
4. Laporan status test & coverage dilaporkan per milestone.

## Status

- 6 September 2026: seluruh keputusan di atas disetujui pemilik proyek.
- Perubahan setelah tanggal ini harus dicatat di sini.
