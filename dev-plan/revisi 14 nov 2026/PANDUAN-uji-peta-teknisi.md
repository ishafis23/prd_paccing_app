# Panduan Uji: Pelacakan & Peta Teknisi (Revisi 14 Nov 2026, poin 7 dan 9a)

Halaman admin: **Customer & Order -> Peta Teknisi** (`/admin/peta-teknisi`).
Bisa dibuka Admin, Finance, dan HR.

## Cara kerja singkat
1. App teknisi merekam titik GPS di perangkat (juga saat offline), lalu mengirimnya berkelompok ke `POST /api/v1/lokasi/batch`.
2. Server menyimpan tiap titik ke `teknisi_lokasi_logs` dan memperbarui posisi terakhir teknisi (`users.last_latitude/last_longitude/last_location_at`).
3. Peta Teknisi membaca posisi terakhir itu. Penanda berwarna menunjukkan status:
   - **Live**: update terakhir kurang dari 5 menit lalu.
   - **Basi**: lebih dari 5 menit tanpa update.
   - **Terakhir / Di luar jam kerja**: teknisi belum absen datang atau sudah absen pulang.
4. **Aturan 9a:** app hanya melacak ketika `GET /api/v1/absensi/hari-ini` mengembalikan `lacak_aktif: true`, yaitu setelah absen datang dan sebelum absen pulang. Server tetap menerima titik yang terlambat tiba (maks. 7 hari, tidak boleh di masa depan) supaya antrean offline tidak hilang.

## Skenario uji (butuh 1 HP teknisi + 1 laptop admin)

| # | Langkah | Hasil yang diharapkan |
|---|---|---|
| 1 | Admin buka Peta Teknisi sebelum teknisi login. | Teknisi muncul di daftar tanpa titik, atau pesan "Belum ada teknisi yang pernah mengirim GPS". |
| 2 | Teknisi login di app dan **izinkan lokasi**. | Setelah beberapa menit, penanda muncul di peta. |
| 3 | Teknisi **absen datang**. | `lacak_aktif` menjadi `true`. Penanda berstatus **Live**, kelompok "bertugas". |
| 4 | Teknisi berjalan 100–200 m, tunggu 1–2 menit, refresh halaman admin. | Penanda bergeser, label "Update: x menit lalu" ikut berubah. |
| 5 | Matikan data seluler HP 3 menit sambil berjalan, lalu nyalakan lagi. | Titik yang tertunda terkirim sekaligus. Posisi terakhir loncat ke titik terbaru, bukan titik lama. |
| 6 | Teknisi tutup app 6 menit lebih. | Penanda menjadi **Basi**. |
| 7 | Teknisi **absen pulang**. | `lacak_aktif` menjadi `false`. App berhenti melacak. Teknisi pindah ke kelompok "Di luar jam kerja / Terakhir". |
| 8 | Cabut izin lokasi di pengaturan HP, buka app. | App harus menolak berjalan dan meminta izin (poin 1, sisi app). |

## Cek lewat data (admin teknis)
- Titik masuk: `SELECT user_id, latitude, longitude, direkam_pada FROM teknisi_lokasi_logs WHERE user_id = ? ORDER BY id DESC LIMIT 20;`
- Posisi terakhir: `SELECT last_latitude, last_longitude, last_location_at FROM users WHERE id = ?;`
- Titik ganda tidak tersimpan dua kali (kunci `user_id + client_uuid`).

## Cek lewat API (opsional)
```
GET  /api/v1/absensi/hari-ini      -> data.lacak_aktif (true/false)
POST /api/v1/lokasi/batch          -> data.diterima / duplikat / ditolak / uuid_selesai
```

## Bila penanda tidak muncul
1. Izin lokasi app belum diberikan atau mode hemat baterai mematikan GPS latar.
2. Teknisi tidak aktif (status nonaktif) atau login di perangkat lain (satu akun satu perangkat).
3. Jaringan terputus: titik menunggu di antrean app, lalu terkirim saat online.
4. Titik lebih tua dari 7 hari ditolak server (`ditolak`).
