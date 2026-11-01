# 10 — Proses: Absensi & Pulang Cepat

Padanan web: `AbsensiScan`, `RiwayatAbsensi`, `PulangCepat`. Service: `AttendanceService`, `MotorCleaningService`, `EarlyLeaveService`. Aturan: dev-plan/15, 19; `dev-plan/30` (hari OFF).

## 1. Alur bisnis

1. Teknisi absen **datang** (mode QR atau lokasi, sesuai `AttendanceSetting`) + foto → status `Bonus` (Games 1), `Normal`, atau `Telat` (denda) ditentukan server (`tentukanStatusDatang`).
2. Check-in order pertama hari itu memicu **Games 2** (foto titik pertama, batas jam).
3. **Cuci motor** (Games 3): foto + pilih teknisi lain yang ikut.
4. Absen **pulang** + foto → hitung **Games 4** (titik & jam).
5. **Pulang cepat**: teknisi mengajukan (tanggal, jam, alasan) → admin/HR menyetujui; bisa juga diinput admin langsung (poin 2 revisi 1 Nov), dengan nominal potongan yang bisa diatur. Dihitung "setengah hari" di gaji.
6. Hari yang tercatat **OFF** tidak menuntut catatan pengeluaran walau teknisi absen (dev-plan/30).

## 2. Endpoint

| Method & path | Service | Catatan |
|---|---|---|
| `GET /absensi/hari-ini` | `AttendanceService` (+ `tentukanModeJalan`) | status hari ini: belum absen / sudah datang / sudah pulang; mode (qr/lokasi); jam batas; daftar lokasi valid |
| `POST /absensi/datang` | `catatDatang($user, $kode, $foto, $lat, $lng)` | multipart: `foto`, `kode?`, `lat?`, `lng?`. Server memvalidasi kode/lokasi |
| `POST /absensi/pulang` | `catatPulang($user, $foto)` | multipart `foto`; mengembalikan juga hasil Games 4 bila tercapai |
| `POST /absensi/titik-pertama` | `catatTitikPertama` | dipanggil otomatis saat check-in order pertama (lihat dokumen 11), bukan layar sendiri |
| `GET /absensi/riwayat?bulan=YYYY-MM` | query `DailyAttendance` milik user | status, jam datang/pulang, flag denda dikecualikan |
| `GET /cuci-motor/rekan` | daftar teknisi lain (`getDaftarTeknisiLainProperty`) | |
| `POST /cuci-motor` | `MotorCleaningService::catat($user, $foto, $pesertaLain)` | multipart |
| `GET /pulang-cepat` | query `TechnicianEarlyLeave` milik user | termasuk `nominal_potongan` bila sudah ditetapkan admin |
| `POST /pulang-cepat` | `EarlyLeaveService::ajukan($user, $tanggal, $jam, $alasan)` | tanggal tidak boleh masa depan |

## 3. Perilaku khusus app

- **QR:** app memindai QR dan mengirim `kode` mentah; server yang memvalidasi. Tidak ada validasi kode di app.
- **Lokasi:** app mengirim koordinat saat tombol ditekan; **jangan** mengandalkan GPS latar belakang. Jika GPS mati → pesan jelas + tombol buka pengaturan.
- **Waktu server adalah acuan** (telat/bonus), bukan jam HP. App menampilkan jam server dari respons.
- **Foto absen:** wajib kamera langsung (bukan galeri) bila aturan bisnis menuntut; kompres di perangkat (dokumen 15).
- **Tanpa antrean offline untuk absen:** absen butuh waktu server yang benar; jika gagal kirim, tampilkan "belum tercatat" dan minta ulang (jangan mencatat jam lokal).

## 4. Pengujian

`tests/Feature/Api/AbsensiTest.php`: datang sukses/ditolak (kode salah, di luar lokasi), pulang tanpa datang, pulang dua kali, pulang cepat tanggal masa depan, akses riwayat orang lain (404). Tes web yang sudah ada tidak boleh berubah.

## 5. Dampak ke proses lain

- Hari absen tanpa catatan pengeluaran (kecuali OFF) menjadi penghalang administrasi → dokumen 13.
- Ledger Games 1–4 masuk gaji → dokumen 13.
