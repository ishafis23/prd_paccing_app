# Ringkasan Progres Revisi 14 Nov 2026

Status sisi **backend, API, admin, dan portal web**. Sisi **app mobile** ada di repo terpisah (daftar di bagian bawah).

| # | Revisi | Status | Catatan |
|---|---|---|---|
| 1 | Izin lokasi wajib | App | Murni sisi app. |
| 2a/2b | Reminder servis 3 bulan lalu bulanan, interval dari teknisi | Backend selesai | Cron `reminder:kirim` 09:00, retry maks 3x, status kirim 0/1/2. Kolom Status Kirim + "Kirim Sekarang" di admin. Input interval di UI (app/web) belum. |
| 3 | Timestamp pada dokumentasi | Selesai | Cap tanggal/jam server di pojok foto semua unggahan API (`FOTO_TIMESTAMP`). Pengeluaran dicap setelah hash kembar dihitung. Foto profil dikecualikan. Upload Livewire lain (laporan foto di web) belum dicap. |
| 4 | Foto bukti pengeluaran + tolak foto kembar; Rp NaN | Selesai | API dan web wajib foto (`EXPENSE_FOTO_WAJIB`). Kembar: sha256 + hash perseptual, 120 hari, semua teknisi. `ringkasan.*.total` kini ada di API. |
| 5 | Koneksi langsung ke WA customer | Selesai (API) | `customer.wa_url` (https://wa.me/62...) di semua order. |
| 6 | Capaian Games 5/6 | App | API `GET /capaian` dan `games()` sudah ada. |
| 7 | Cara cek posisi teknisi | Selesai | Lihat `PANDUAN-uji-peta-teknisi.md`. |
| 8 | Ulasan customer | Selesai | Form bintang + komentar di halaman resi publik (link yang sama dengan dokumentasi). Satu ulasan per order. Admin: menu **Ulasan Customer**. API: `GET /ulasan`. Link resi ikut di WA laporan (`{tautan_resi}`). |
| 9a | Pelacakan hanya antara absen datang dan pulang | Selesai (API) | `absensi/hari-ini` mengirim `lacak_aktif`. App harus mengikutinya. Server tetap menerima titik terlambat (keputusan commit sebelumnya). |
| 9b/14 | Kunci urutan titik | Diverifikasi | Sudah berfungsi dan kini diuji termasuk pending-sore. API mengirim `terkunci`, `terkunci_oleh`, `pesan_terkunci`. |
| 10 | Tanggal di Riwayat Pengerjaan | Selesai (API) | `tanggal_tampil`; filter dan urutan menurut tanggal pengerjaan. |
| 11 | Foto setoran tunai | Sudah ada | Foto wajib, `foto_url` di API, tampil di admin. Tinggal app menampilkannya. |
| 12 | Tata letak menu Beranda | App | Murni sisi app. |
| 13 | Dokumentasi video | Selesai | API `POST/DELETE /order/{id}/video`, form di portal web (galeri atau rekam langsung), tampil di admin. Maks 50 MB dan 10 video per order (`VIDEO_MAKS_MB`). |
| 15 | Urutan titik di app | Selesai (API) | `titik.urutan` + status kunci. Tinggal tampilan badge di app. |
| 16 | Catatan pengerjaan teknisi | Selesai (API) | `catatan_pengerjaan` di riwayat. Web dan admin sudah menampilkan. |
| 17 | Bukti kendala sudah diberitahu ke customer | Selesai | API dan web wajib bukti + pihak (`KENDALA_BUKTI_WAJIB`). Riwayat tersimpan, tampil di detail order admin. |
| 18 | Auto-WA laporan + invoice/kuitansi | Selesai | Default MATI (`WA_NOTIF_OTOMATIS_SELESAI`). Idempoten. |

## Kontrak API baru/berubah (untuk tim app)
- `POST /pengeluaran`, `PUT /pengeluaran/{id}` (kirim POST + `_method=PUT`): multipart dengan `foto`. Respons: `foto_url`, `foto_diunggah_pada`, `dicatat_pada`. `ringkasan.hari_ini.total`, `ringkasan.bulan_ini.total`.
- `POST /order/{id}/kendala`: `alasan`, `jenis` (besok|sore), `pihak` (customer|teknisi), `bukti` (gambar). Detail order: `kendala_bukti`.
- `POST /order/{id}/interval-servis`: `interval_bulan` 1..12.
- `POST /order/{id}/video` (multipart `video`, `keterangan`), `DELETE /order/{id}/video/{video}`. Detail order: `video[]`.
- `GET /absensi/hari-ini`: `lacak_aktif`.
- `GET /jadwal`: tiap order punya `titik.urutan`, `terkunci`, `terkunci_oleh`, `pesan_terkunci`, `customer.wa_url`. `GET /order/{id}` juga memuat kunci.
- `GET /riwayat`: `tanggal_tampil`, `catatan_pengerjaan`.
- `GET /ulasan`: `ringkasan{rata_rata,jumlah}`, `daftar[]`.

## Sisa di app mobile (repo terpisah)
1 (izin lokasi wajib), 12 (tata letak menu), 6 (tampilan Games 5/6), 15 (badge TITIK n), 10 (tampilkan `tanggal_tampil`), 2b (layar interval cuci), 9a (ikuti `lacak_aktif`), 4/11/17/13 (UI unggah foto pengeluaran, bukti kendala, video, tampil foto setoran), 5 (tombol WA memakai `wa_url`), 8 (tampilan rating teknisi).

## Catatan deploy
1. `php artisan migrate` (6 migrasi baru bertanggal 2026_11_14).
2. `npm run build`: halaman resi dan form web memakai kelas Tailwind baru.
3. Scheduler (`schedule:run` tiap menit) dan queue worker harus aktif (reminder, auto-WA).
4. `php.ini`: `upload_max_filesize` dan `post_max_size` minimal 50M agar video bisa diunggah.
5. Env baru (opsional): `EXPENSE_FOTO_WAJIB`, `KENDALA_BUKTI_WAJIB`, `FOTO_TIMESTAMP`, `VIDEO_MAKS_MB`, `VIDEO_MAKS_PER_ORDER`, `WA_NOTIF_OTOMATIS_SELESAI`. Selama app lama masih beredar, set dua yang pertama ke `false`.
6. Kosongkan `WA_TEST_TARGET` di produksi sebelum menyalakan notifikasi otomatis.
