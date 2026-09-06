# Checklist Deployment — Penyimpanan File (storage) & Lingkungan

> STATUS: **CATATAN** (referensi go-live). Menjawab T1 ("deployment detail
> menyusul") khusus bagian storage; bagian lain deployment (web server, queue,
> dll.) menyusul saat VPS disiapkan.

## 1. Model penyimpanan saat ini (ringkas)

| Aspek | Kondisi |
|---|---|
| Lokasi file | `storage/app/public/` (disk `public`) |
| Subfolder | `work-reports/` (foto laporan teknisi), `payment-channels/` (gambar QRIS), `business/` (logo usaha) |
| Akses web | symlink `public/storage` → `storage/app/public` |
| DB menyimpan | path relatif (mis. `business/x.png`) — URL ditampilkan relatif `/storage/...` (aman lintas host/port, tidak bergantung APP_URL) |
| Kuota | config `penyimpanan.kuota_mb` (env `FOTO_KUOTA_MB`, default 1024 MB) — dihitung dari SELURUH isi disk public |
| Pembersihan otomatis | command `foto:bersihkan` (B23/B26) — hapus foto `work-reports` > `FOTO_MAX_UMUR_HARI` (default 60) + file yatim; QRIS/logo TIDAK dihapus otomatis |
| Jadwal | Laravel Scheduler harian 03:00 (routes/console.php) |
| Guard upload | `StorageQuotaService::pastikanCukup()` di form laporan teknisi, upload QRIS admin, & logo Info Usaha |
| Kelola manual | Admin → Manajemen → Penyimpanan (daftar file, hapus, bersihkan sekarang) |

## 2. Wajib dilakukan saat go-live (server VPS)

1. **Symlink storage** — satu kali:
   ```
   php artisan storage:link
   ```
   Tanpa ini semua file gambar (logo, foto, QRIS) 404.

2. **Scheduler (cron)** — mutlak agar `foto:bersihkan` (03:00) & perintah
   terjadwal lain berjalan:
   ```
   * * * * * cd /path/proyek && php artisan schedule:run >> /dev/null 2>&1
   ```
   Verifikasi: `php artisan schedule:list`.

3. **Hak akses & ownership** — pastikan proses web (www-data/nginx) bisa tulis:
   ```
   storage/  (app, framework, logs)  → writable oleh user web
   bootstrap/cache/                  → writable
   ```
   Jangan `chmod 777`; pakai ownership benar (`chown -R www-data:www-data storage`).

4. **`.env` produksi**:
   - `APP_URL=https://domain-resmi` (https; URL relatif kami tetap aman apa pun nilainya)
   - `FOTO_KUOTA_MB=` → isi ≤ 60–70% kapasitas partisi (sisakan ruang utk DB,
     log, backup, update). Contoh: partisi 10 GB → kuota 6 GB (6144).
   - `FOTO_MAX_UMUR_HARI=60` (atau 90/120 sesuai kebijakan)
   - `APP_ENV=production`, `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`
   - Cache config: `php artisan config:cache` (baca ulang bila ubah .env)

5. **Backup rutin (DUA-duanya, bukan cuma DB)**:
   - Database (mysqldump / spatie laravel-backup / cron)
   - Folder `storage/app/public` (rsync/restic) — foto & logo adalah data.
   Uji restore minimal sekali sebelum go-live.

6. **Keamanan folder publik**:
   - Pastikan listing direktori MATI di web server
     (nginx: `autoindex off` default; apache: `Options -Indexes`).
   - Nama file acak (hash) sudah cukup membuat URL tidak bisa ditebak;
     JANGAN taruh dokumen sensitif (KTP/kontrak) di disk public — pakai disk
     private bila ada.

## 3. Saat kapasitas hosting dinaikkan

- Ubah `FOTO_KUOTA_MB` di `.env` → `php artisan config:cache` → selesai,
  tanpa ubah kode. Opsional perpanjang `FOTO_MAX_UMUR_HARI` (60→90/120).

## 4. Cek cepat setelah deploy

1. `curl -I https://domain/storage/business/<logo>.png` → 200.
2. Login admin → Manajemen → Penyimpanan: angka terpakai muncul & wajar.
3. `php artisan foto:bersihkan` manual → laporan "0 dihapus" (belum ada yang tua).
4. Kirim laporan teknisi berfoto → foto tampil di detail/resi.
5. `php artisan schedule:list` menampilkan `foto:bersihkan` harian 03:00.
