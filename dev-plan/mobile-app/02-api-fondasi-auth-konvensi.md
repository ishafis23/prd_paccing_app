# 02 — Fondasi API, Autentikasi & Konvensi

Prasyarat semua dokumen proses (10–15). Dikerjakan di **Fase 0**.

## 1. Autentikasi (Laravel Sanctum, token)

- Pasang: `php artisan install:api` (Sanctum + `routes/api.php`).
- `POST /api/v1/login` — body: `email`, `password`, `device_name`. Hanya user ber-role **teknisi** yang diterima; role lain → 403.
- Respons: `{ "token": "...", "user": { id, name, email, foto_url } }`.
- Token disimpan di secure store HP; setiap request: `Authorization: Bearer <token>`.
- `POST /api/v1/logout` — cabut token perangkat ini. Admin dapat mencabut semua token teknisi (HP hilang) — tambahkan aksi di panel Filament.
- `GET /api/v1/me` — profil + `min_app_version` + flag fitur.
- Throttle login (mis. 5/menit per email+IP). Token punya masa berlaku panjang (mis. 90 hari) dan diperbarui saat dipakai.

## 2. Konvensi URL & versi

- Prefix `/api/v1`. Perubahan yang tidak kompatibel → `/api/v2`.
- Header app: `X-App-Version: 1.0.0`, `X-Platform: android`. Server menolak versi di bawah `min_app_version` dengan `426 Upgrade Required` + pesan yang tampil di app.
- Sumber daya jamak, huruf kecil, bahasa Indonesia konsisten dengan istilah di UI (`/jadwal`, `/pengeluaran`, `/setoran`).

## 3. Format respons

Sukses:
```json
{ "data": { ... } }                       // objek
{ "data": [ ... ], "meta": { "page": 1, "per_page": 15, "total": 42 } }   // daftar
```
Tanggal: ISO 8601 dengan zona waktu (`2026-10-26T13:00:00+08:00`) untuk waktu; `Y-m-d` untuk tanggal murni. Uang: **integer rupiah** (bukan string berformat). Pemformatan tampilan dilakukan app.

## 4. Format error (selaras dengan kode yang sudah ada)

```json
{ "message": "Catatan pengeluaran tanggal 30 Oct 2026 belum diisi ...", "code": "administrasi_belum_tuntas", "errors": { "field": ["..."] } }
```

| HTTP | Kapan | Sumber di backend |
|---|---|---|
| 401 | Token tidak valid/kedaluwarsa → app ke layar login | Sanctum |
| 403 | Bukan haknya / role salah | `AuthorizationException` (`RestrictsByRole`) |
| 404 | Data tidak ada **atau bukan milik teknisi ini** | scope `untukTeknisi` / `teknisi_id` |
| 409 | Konflik status (mis. order sudah ditutup) | — |
| 422 | Validasi atau aturan bisnis; `message` siap tampil | `ValidationException`, `BusinessRuleException` |
| 426 | Versi app terlalu lama | middleware versi |
| 429 | Terlalu banyak request | throttle |

Penerjemahan `BusinessRuleException` → 422 dilakukan di satu tempat (handler pengecualian untuk route `api/*`), bukan per controller.

## 5. Pembatasan akses data

Semua query dibatasi ke data milik teknisi login: `Order::untukTeknisi($id)` (PIC **atau** anggota tim), `TeknisiExpense::byTeknisi / tercatatUntuk`, dst. Pelajaran dari kasus order tim vs PIC: tentukan eksplisit per endpoint apakah yang dimaksud "milik PIC" (pendapatan) atau "tim" (jadwal, laporan, Game 5/6). Tiap dokumen proses mencatat aturan ini.

## 6. Refactor prasyarat (logika yang masih di Livewire)

Controller API **tidak boleh** menyalin logika dari komponen Livewire. Logika berikut harus dipindah ke Service dulu, lalu Livewire dan API sama-sama memanggilnya:

| Komponen Livewire | Logika yang dipindah | Service tujuan |
|---|---|---|
| `LaporanPengeluaran` (387 baris) | catat, ubah (aturan status saat ubah), hapus, validasi partner, ringkasan & filter periode | `TeknisiExpenseService` (baru) |
| `OrderDetail` (1338 baris) | tambah/hapus layanan & material, laporan per unit (`simpanUnit`, `salinPosisiSebelumnya`), foto kategori (ganti/hapus), toggle item batal | perluas `TeknisiService` / `UnitReportService`; pecah ke service bila terlalu besar |
| `DailyReport` (305 baris) | entri kerja/keuangan harian, unit AC ad-hoc (membuat model langsung) | `DailyReportService` (baru) — **pastikan dulu masih dipakai** (menu "Laporan Harian" tidak ada di navigasi bawah) |
| `Keuangan`, `CapaianKerja` | query pendapatan & capaian | `TeknisiKeuanganService` (baru, bungkus `OmsetService` + `TechnicianIncentiveService::ringkasanGames56`) |
| `AbsensiScan`, `SetoranTunai`, `PulangCepat`, `Akun` | sudah memanggil Service | tidak perlu refactor besar |

Urutan: refactor **per proses** tepat sebelum endpoint proses itu dibuat, dan tutup dengan tes yang membuktikan perilaku web tidak berubah.

## 7. Keamanan

- HTTPS wajib; token tidak pernah di URL.
- Unggahan: validasi MIME & ukuran, nama file dibuat server, simpan lewat `FotoKompresService` + `StorageQuotaService` yang sudah ada.
- Rate limit per token pada endpoint tulis (foto, absen).
- Jangan kembalikan data pelanggan di luar order milik teknisi.
- Catat `device_name`/IP terakhir token untuk audit (Sanctum `personal_access_tokens`).
- Lokasi & kamera: izin diminta saat dipakai, bukan saat install; kebijakan privasi disiapkan sebelum rilis publik.

## 8. Pengujian API

Satu file tes per proses di `tests/Feature/Api/`, pola yang sama dengan `tests/Feature` sekarang (Pest + factory). Tiap endpoint minimal: berhasil, tanpa token (401), data milik teknisi lain (404), aturan bisnis ditolak (422). Untuk logika yang dipindah dari Livewire: tes web yang sudah ada harus tetap lulus.

## 9. Tabel baru yang dibutuhkan

- `personal_access_tokens` (Sanctum).
- `device_tokens` (push, lihat `14-...md`): `user_id`, `token`, `platform`, `last_seen_at`.
- Opsional: `app_settings` / config untuk `min_app_version` dan flag fitur.
