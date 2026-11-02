# Mobile App Teknisi — Indeks Rencana

Status (2026-11-02): **Fase 0 API selesai** (`dev-plan/32-api-teknisi-v1.md`, tes di `tests/Feature/Api`). Scaffold app: repo terpisah `D:\myproject\crm_paccing_mobile` (Expo + TypeScript). Keputusan yang masih terbuka ada di bagian bawah file ini.

## Tujuan

Aplikasi Android (iOS menyusul bila perlu) untuk teknisi, menggantikan portal web teknisi (`/teknisi/*`) sebagai alat kerja harian: absensi, jadwal & laporan order, pengeluaran, setoran, capaian. Admin/HR/owner tetap memakai panel web Filament.

## Prinsip

1. **Tidak ada logika bisnis di app.** Aturan (syarat administrasi, hari OFF, Games 1–6, partner pengeluaran, split omset) dihitung server. App hanya menampilkan dan mengirim.
2. **API memanggil Service yang sudah ada** (`app/Services/*`), bukan menyalin logika. Web dan app selalu satu aturan.
3. **Portal web teknisi dipertahankan sebagai spesifikasi UI/UX** dan cadangan selama transisi (lihat `20-adopsi-ui-ux-portal-web.md`).

## Daftar dokumen

| File | Isi |
|---|---|
| `01-keputusan-stack-dan-arsitektur.md` | Pilihan teknologi, alasan, arsitektur, struktur repo |
| `02-api-fondasi-auth-konvensi.md` | Sanctum, login/logout, format respons & error, versi API, keamanan, refactor prasyarat |
| `10-proses-absensi-dan-pulang-cepat.md` | Absen datang/pulang (QR, lokasi, foto), titik pertama, pulang cepat, cuci motor |
| `11-proses-order-dan-laporan.md` | Jadwal, berangkat, check-in, laporan per unit, foto, layanan/material tambahan, kendala, tutup order |
| `12-proses-keuangan-pengeluaran-setoran.md` | Pengeluaran (partner, ubah, filter), pendapatan jasa, setoran tunai, kasbon |
| `13-proses-administrasi-capaian-insentif.md` | Syarat administrasi (penghalang), capaian, Games 1–6, riwayat absensi, slip gaji |
| `14-notifikasi-push.md` | Push FCM, jenis notifikasi, token perangkat |
| `15-upload-foto-dan-offline.md` | Unggah foto tahan sinyal lemah, antrean, kompresi, aturan offline |
| `20-adopsi-ui-ux-portal-web.md` | Pemetaan halaman web → layar app, design token, navigasi |
| `30-fase-estimasi-risiko-rilis.md` | Fase, estimasi, risiko, pengujian, rilis, pensiun portal web |
| `../32-api-teknisi-v1.md` | **Kontrak endpoint v1** (dibuat saat Fase 0 selesai) |

Aturan penamaan: `0x` fondasi, `1x` proses bisnis, `2x` UI/UX, `3x` pelaksanaan.

## Keputusan terbuka (perlu jawaban sebelum Fase 0)

| # | Pertanyaan | Rekomendasi sementara |
|---|---|---|
| 1 | Stack app: React Native/Expo atau lainnya? | React Native (Expo) + TypeScript |
| 2 | Siapa developer sisi app? | — (menentukan stack & estimasi) |
| 3 | Platform: Android saja dulu atau iOS juga? | Android dulu; cek HP teknisi |
| 4 | Distribusi: Play Store publik atau internal testing/APK? | Internal dulu, publik setelah stabil |
| 5 | Cakupan fase 1 sesuai `30-fase-...md`? | Ya, kecuali client minta lain |
| 6 | Notifikasi apa saja yang diinginkan client? | Lihat `14-notifikasi-push.md` |
| 7 | Navigasi: 5 tab + "Lainnya" (web sekarang 9 tab)? | Ya (lihat dokumen 20) |

## Sumber fakta di repo

- Halaman web teknisi: `routes/web.php` (grup `/teknisi`), `app/Livewire/Teknisi/*`, `resources/views/livewire/teknisi/*`, layout `resources/views/layouts/teknisi.blade.php`, navigasi `resources/views/components/teknisi-bottom-nav.blade.php`.
- Aturan bisnis: `app/Services/*`. Revisi terbaru client: `dev-plan/req dev 1 nov 2026/`, `dev-plan/30-revisi-pengeluaran-hari-off-dan-edit-riwayat.md`.
