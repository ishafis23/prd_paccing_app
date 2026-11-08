# 33 — Laporan Eksekusi Mobile App Teknisi (8 November 2026)

> STATUS: **kode selesai & lolos pemeriksaan statis; BELUM diuji di HP/emulator.** Semua yang tertulis "berfungsi" di bawah hanya terbukti lewat tes backend (Pest), `tsc`, `expo lint`, dan `expo export --platform android` (bundle berhasil dibangun). Perilaku di perangkat nyata (GPS latar belakang, kamera, SQLite, sinyal naik-turun) harus diuji sesuai §6.

Repo app: `D:\myproject\crm_paccing_mobile` (Expo SDK 58, React Native 0.88, TypeScript, Expo Router).

## 1. Keputusan baru dari client (menimpa rencana awal)

| Topik | Rencana awal (`mobile-app/01`, `15`) | Keputusan 8 Nov 2026 |
|---|---|---|
| Pelacakan posisi | tidak ada (hanya per-order, app terbuka) | **Terus-menerus selama jam kerja** (absen datang → absen pulang), termasuk saat layar mati |
| Offline | "tidak offline-first penuh", hanya foto & form gagal-kirim | **Bekerja offline lalu sinkron otomatis** untuk aksi yang aman ditunda |
| Platform | Android dulu | Android dulu (dikonfirmasi) |

## 2. Backend (repo `crm_paccing`)

| Perubahan | Berkas | Tes |
|---|---|---|
| `POST /api/v1/lokasi/batch` — 1–100 titik, idempoten per `client_uuid`, hanya diterima antara jam datang & jam pulang, `users.last_*` hanya maju | `Api/V1/LokasiController`, `Services/LacakLokasiService`, `Models/TeknisiLokasiLog`, migrasi `2026_11_08_000001` | `tests/Feature/Api/LokasiTest.php` (6 tes) |
| `client_uuid` pada `POST /pengeluaran` (kirim ulang tidak menggandakan) | `PengeluaranController`, `TeknisiExpense`, migrasi `2026_11_08_000002` | `PengeluaranTest` (+1) |
| Peta Teknisi (admin) kini juga menampilkan teknisi yang sedang jam kerja tanpa order aktif | `Filament/Pages/PetaTeknisi`, view `peta-teknisi.blade.php` | `PetaTeknisiPageTest` (+1) |
| Kontrak endpoint baru | `dev-plan/32-api-teknisi-v1.md` | — |

**Hasil tes:** `tests/Feature/Api` + Peta = 52 lulus. Seluruh suite: 1122 lulus, **17 gagal** (`ExpenseTrackingTest` 5, `PhotoUploadTest` 5, `OmsetSplitPembayaranTest` 3, `NotifBerangkatTest` 2, `NotifPembayaranTest` 1, `OrderAcUnitLinkTest` 1). Kegagalan yang sama (17) muncul saat perubahan di atas di-stash → **sudah ada sebelumnya**, bukan regresi (sesuai catatan di `mobile-app/30`).

> **Wajib sebelum dipakai:** `php artisan migrate` (dua migrasi baru) di server dev/produksi. Belum dijalankan oleh saya di database mana pun selain database tes.

## 3. App — apa yang ada

- **Login/sesi:** token Sanctum di SecureStore; 401 → kembali ke login tanpa menghapus antrean; ganti akun → cache & antrean dibersihkan (mencegah data tercampur).
- **Jadwal** (cache-baca), banner penghalang administrasi, lencana antrean/offline.
- **Absensi:** datang (mode QR via kamera, atau mode lokasi via GPS) + foto selfie, pulang + foto; wajib online. Kartu status pelacakan + peringatan izin lokasi.
- **Order:** berangkat, check-in (foto titik pertama), kendala, isian per unit (**offline**), foto per slot (**offline**, kompres 1200px/JPEG 0.7), kirim laporan, metode bayar, bukti bayar, lapor bayar, tutup order.
- **Pengeluaran:** catat (**offline**), daftar bulan ini, ringkasan.
- **Pelengkap:** riwayat pengerjaan, pendapatan & capaian, setoran tunai, riwayat absensi, pulang cepat, cuci motor.
- **Sinkronisasi:** layar antrean (menunggu/mengirim/gagal, coba lagi, buang), status koneksi & pelacakan.

### Mekanisme offline
`src/sync/outbox.ts`: SQLite `outbox`, dikirim berurutan (tidak paralel), `client_uuid` idempoten, `dedupe_key` (isian unit/foto slot yang diedit lagi menggantikan yang menunggu), backoff 5s→30s→2m→10m. Jaringan/5xx/429/401 → coba lagi nanti; 4xx lain → status **gagal** yang terlihat & bisa diulang/dibuang. Pemicu: app dibuka, kembali aktif, jaringan kembali, tiap menit, item baru.
**Aturan:** aksi yang bergantung waktu/validasi server (absen, berangkat, check-in, kirim laporan, tutup, setoran, bukti bayar) **wajib online** dan ditolak dengan pesan jelas — tidak pernah menampilkan "berhasil" sebelum server menerima. "Kirim laporan" diblok selama masih ada isian/foto order itu yang belum terkirim.

### Mekanisme pelacakan
`expo-location` + `expo-task-manager`: foreground service (notifikasi tetap "Jam kerja berjalan"), akurasi seimbang, tiap ±60 dtk / 25 m. Task didefinisikan di `src/tracking/task.ts` dan diimpor dari `index.ts` agar terdaftar sebelum app dimuat. Titik → SQLite `lokasi` → `POST /lokasi/batch` (maks 5000 titik tersimpan, yang tertua dibuang). Mulai/berhenti diselaraskan dengan status absensi server (`siap_pulang` = aktif), memakai cache hari ini bila offline. Sebelum absen pulang, sisa titik dikirim dulu.

## 4. Deviasi dari rencana & hal yang sengaja tidak dikerjakan

| Item | Status | Alasan |
|---|---|---|
| Notifikasi push (`mobile-app/14`) | ❌ belum | Butuh akun Firebase/FCM + pengirim di server; belum ada keputusan kredensial. `expo-notifications` terpasang tapi tidak dipakai |
| Tambah/hapus layanan, batalkan item, material stok di laporan | ❌ belum | Endpoint ada; UI-nya menyusul (Fase 2 lanjutan) |
| Slip gaji, kasbon, insentif, Games, Capaian harian detail | ❌ belum | Endpoint ada; hanya ringkasan capaian/pendapatan yang ditampilkan |
| Laporan Harian (`DailyReport`), pengaturan notifikasi | ❌ belum | Belum diputuskan di kontrak v1 (§7) |
| Crash reporting (Sentry), dasbor versi app | ❌ belum | Butuh akun/DSN |
| Mode hemat data (hanya Wi-Fi) | ❌ belum | Fase 2 |
| iOS | ❌ | Keputusan: Android dulu. Konfigurasi iOS ada di `app.json` tapi tidak diuji |
| Foto bukti bayar / setoran / absensi diantre offline | ❌ sengaja | Sesuai `mobile-app/15` §4: butuh waktu/validasi server |

## 5. Risiko yang perlu diketahui

1. **Pelacakan latar belakang di Android tidak dijamin.** Vendor tertentu (Xiaomi/Oppo/Vivo/Samsung) mematikan service saat app di-swipe dari recent apps atau penghemat baterai aktif (lihat dontkillmyapp.com). Mitigasi di app: foreground service + peringatan izin "Selalu" + tips baterai; **tetap perlu panduan ke teknisi** dan uji di HP asli mereka. Jika app dimatikan paksa, pelacakan berhenti sampai app dibuka lagi.
2. **Izin lokasi "Selalu"** wajib; Play Store mensyaratkan deklarasi penggunaan lokasi latar belakang + kebijakan privasi bila dirilis publik (draf: `crm_paccing_mobile/docs/kebijakan-privasi.md`). Distribusi internal (APK) tidak melalui review.
3. **Privasi/ketenagakerjaan:** pelacakan terus-menerus data lokasi karyawan perlu **persetujuan tertulis teknisi** dan pembatasan ke jam kerja (sudah ditegakkan di server: titik di luar absen datang–pulang ditolak). Konsultasikan ke HR/legal sebelum rollout.
4. Konsumsi baterai/kuota belum diukur.
5. `usesCleartextTraffic` aktif untuk pengembangan (HTTP). Matikan untuk build produksi dan pakai HTTPS.
6. Belum ada tes otomatis di sisi app (tidak ada Jest); logika antrean/pelacakan divalidasi hanya lewat typecheck/lint/bundle dan tes backend untuk kontraknya.

## 6. Daftar uji lapangan (wajib sebelum dipakai teknisi)

Pada HP Android kelas bawah yang dipakai teknisi, dengan *development/preview build*:

1. Login → jadwal tampil → mode pesawat → buka ulang app: jadwal & order tetap tampil, banner "Tanpa koneksi".
2. Absen datang (mode aktif di server) → notifikasi "Jam kerja berjalan" muncul → izin lokasi "Selalu" diminta.
3. Layar mati 30 menit sambil berjalan → admin buka Peta Teknisi: marker bergerak (cek `teknisi_lokasi_logs`).
4. Mode pesawat 15 menit sambil bergerak → matikan mode pesawat: titik terkumpul terkirim (`Lainnya → Sinkronisasi`: "0 titik menunggu").
5. Offline: isi unit, ambil foto slot, catat pengeluaran → status "Menunggu dikirim" → online: terkirim otomatis, tidak ada duplikat (kill app di tengah kirim lalu buka lagi).
6. Foto gagal permanen (mis. kuota penuh) → muncul "Gagal" dengan pesan server, bisa dicoba lagi/dibuang.
7. Absen pulang → notifikasi hilang, pelacakan berhenti, sisa titik terkirim.
8. Token dicabut admin → app kembali ke login, antrean tidak hilang; login akun lain → antrean akun lama dibersihkan.
9. Uji di perangkat Xiaomi/Oppo/Samsung dengan penghemat baterai aktif & app di-swipe: catat kapan pelacakan berhenti.
10. Pengamatan baterai 1 hari kerja penuh.

## 7. Cara menjalankan

Lihat `crm_paccing_mobile/README.md`. Ringkas: `npm install` → `npx expo prebuild --platform android` → `npx expo run:android` (butuh Android Studio/SDK atau `eas build --profile development`). **Tidak bisa di Expo Go** (pelacakan latar belakang).
