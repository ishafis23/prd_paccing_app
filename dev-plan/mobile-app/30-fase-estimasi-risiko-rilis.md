# 30 — Fase, Estimasi, Risiko & Rilis

Estimasi untuk **1 developer penuh waktu**; perkiraan kasar, bukan komitmen. Hitung ulang setelah keputusan #1–#3 di README terjawab.

## Fase 0 — Fondasi API (±2–3 minggu)

Isi: dokumen 02.
- Sanctum, login/logout/`me`, versi & `min_app_version`, handler error `api/*`, throttle.
- Tabel `device_tokens` (kosong dulu, dipakai Fase 1).
- Refactor prasyarat yang **diperlukan Fase 1**: `TeknisiExpenseService`, ekstrak logika `OrderDetail` (layanan, material, foto kategori), `TeknisiKeuanganService`.
- Endpoint baca: `/me`, `/jadwal`, `/order/{id}`, `/administrasi`, `/pendapatan`, `/pengeluaran`.
- Kerangka tes `tests/Feature/Api/*`.
- **Keluaran:** dokumen kontrak `dev-plan/32-api-teknisi-v1.md` (daftar endpoint + contoh JSON + kode error) yang menjadi pegangan app.

## Fase 1 — MVP kerja harian (±4–6 minggu)

1. Login, jadwal, banner administrasi (11, 13).
2. Absensi: QR/lokasi, datang, pulang, cuci motor, pulang cepat (10).
3. Alur order: berangkat → check-in → laporan per unit + foto → kendala → tutup (11) dengan antrean foto (15).
4. Pengeluaran: catat, ubah, partner, filter bulan/hari (12).
5. Push: order dijadwalkan/berubah, pengingat pengeluaran (14).
6. Pendapatan jasa + Game 5/6 (12, 13).

**Pintu keluar Fase 1:** 2–3 teknisi memakai app untuk **1 minggu kerja penuh** berdampingan dengan web tanpa selisih data; semua tes API & web lulus.

## Fase 2 — Pelengkap (±3–4 minggu)

Setoran tunai, Lengkapi laporan (lencana), Capaian, riwayat absensi, insentif (ledger), slip gaji, pengaturan notifikasi, mode hemat data (hanya Wi-Fi), kasbon baca-saja bila diminta.

## Fase 3 — Rilis & operasional (±1–2 minggu, paralel dengan akhir Fase 2)

- Distribusi **internal** (Play Console internal testing atau APK) → uji lapangan.
- Crash reporting (Sentry), log API, dasbor versi app yang aktif.
- Kebijakan privasi (lokasi, kamera, foto) & deskripsi toko bila publik.
- Panduan singkat untuk teknisi (PDF/WA).
- **Pensiun portal web teknisi bertahap:** web dibiarkan hidup ≥ 1 siklus gaji setelah 100% teknisi pindah; tidak ada fitur baru di web setelah Fase 1 stabil (fitur baru: API dulu).

## Risiko utama

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Unggah foto gagal di sinyal lemah | Laporan tidak lengkap → teknisi tertahan administrasi | Antrean + kompres + idempotensi (15); uji di lapangan sejak Fase 1 |
| Aturan bisnis sering direvisi client | Selisih web ↔ app | Semua logika di server (Service); app tipis; EAS Update untuk perbaikan UI |
| Refactor `OrderDetail` merusak perilaku web | Regresi di alur utama | Tes web yang ada jadi jaring pengaman; refactor bertahap per aksi, tiap langkah di-commit terpisah |
| Dua UI dirawat bersamaan | Biaya ganda | Bekukan fitur baru di web setelah Fase 1; API-dulu |
| OS membunuh proses latar belakang | Unggah/lokasi tertunda | Tidak bergantung latar belakang; antrean jalan saat app dibuka |
| Developer tunggal / belum biasa JS-TS | Estimasi meleset | Putuskan #2 lebih awal; pertimbangkan Capacitor sebagai Fase 0 sementara (dokumen 01) |
| Akun Play Console / review toko | Rilis tertunda | Mulai dari distribusi internal; siapkan akun lebih awal |
| HP teknisi kelas bawah | Lambat/crash saat kompres banyak foto | Uji di perangkat termurah yang dipakai teknisi; proses foto berurutan |
| Teknisi pengguna iPhone | iOS belum ada | Cek di awal; jika ada, rencanakan build iOS (akun Apple Developer) |

## Pengujian (ringkasan)

- **API:** tes fitur per proses (dokumen 10–15), pola Pest yang ada; wajib lulus sebelum endpoint dipakai app.
- **Regresi web:** `php artisan test` penuh sebelum merge; catatan: sebagian tes lama sudah gagal sebelum pekerjaan ini (`ExpenseTrackingTest`, `NotifBerangkatTest`, `OmsetSplitPembayaranTest`, `PhotoUploadTest`, dll.) — perbaiki/pensiunkan bersamaan refactor terkait, jangan dibiarkan menutupi regresi baru.
- **App:** uji komponen kunci (form pengeluaran, antrean foto), uji manual terstruktur di HP Android kelas bawah (mode pesawat, sinyal naik-turun, kill app), uji bersama teknisi nyata.
- **Keamanan:** akses data orang lain (404), token dicabut (401), versi usang (426).

## Definisi selesai (per fitur)

Endpoint + tes API lulus · layar app + state loading/kosong/error · perilaku sama dengan web (atau selisih disetujui) · dicatat di dokumen kontrak API · diuji di HP nyata.

## Dokumen turunan yang akan dibuat

- `dev-plan/32-api-teknisi-v1.md` — kontrak endpoint terperinci (setelah keputusan stack).
- `dev-plan/33-eksekusi-mobile-app.md` — laporan eksekusi per fase (pola `21-/29-laporan-eksekusi-dan-capaian.md`).
