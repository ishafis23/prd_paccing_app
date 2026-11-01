# 15 — Unggah Foto & Perilaku Offline

Foto adalah beban terbesar (laporan per unit, absen, setoran, cuci motor) dan sumber masalah terbanyak di web (sinyal lemah, autosave foto — dev-plan/28). Pendekatan di app dibuat berbeda dari web.

## 1. Kondisi web saat ini

- `public/js/camera-upload.js`: kompres di browser (maks 1200px, kualitas 0.7), kamera via `getUserMedia`.
- Dua jalur: `PhotoController` (foto order final) dan `TemporaryPhotoUploadController` (foto sementara `teknisi.temp-photo.*` sebelum laporan dikirim, dibersihkan scheduler `foto:bersihkan`).
- Kuota storage dicek (`StorageQuotaService::pastikanCukup`), kompres server (`FotoKompresService`).

## 2. Rancangan di app

1. **Ambil foto** dengan kamera app → kompres di perangkat (`expo-image-manipulator`, sisi panjang ≈1200px, JPEG ≈0.7; sama dengan web agar konsisten dengan kuota).
2. **Simpan dulu ke penyimpanan lokal** + masukkan ke **antrean unggah** (SQLite) dengan metadata: `order_id`, `unit_id`, `slot`, `client_uuid`, jumlah percobaan.
3. **Unggah satu foto per request** (multipart) ke endpoint khusus; hasil sukses menandai item antrean selesai. Gagal → coba ulang dengan backoff (5s, 30s, 2m, …), berhenti setelah N kali dan tampilkan "Foto belum terkirim (n)" dengan tombol kirim ulang.
4. Antrean berjalan saat app dibuka & saat jaringan kembali; unggah **latar belakang** hanya bila didukung (`expo-background-fetch`/`expo-task-manager`) — jangan bergantung padanya.
5. Pemakaian jaringan seluler dibatasi opsional ("hanya Wi-Fi" pengaturan Fase 2).

## 3. Kontrak endpoint

| Method & path | Catatan |
|---|---|
| `POST /order/{id}/foto` | multipart: `file`, `slot`/`kategori`, `unit_id?`, `client_uuid`. **Idempotent**: `client_uuid` sama → kembalikan foto yang sudah ada, tidak menggandakan |
| `GET /order/{id}/foto` | daftar foto + slot wajib yang kurang |
| `DELETE /order/{id}/foto/{foto}` | hanya foto miliknya |
| Absen/setoran/cuci motor | foto dikirim bersama aksinya (multipart), tanpa antrean (butuh jam server; dokumen 10) |

Server tetap yang memutuskan "foto lengkap" (`fotoWajibKurang`); app hanya menampilkan indikator dari respons dan dari antrean lokal ("terkirim / menunggu").

## 4. Aturan offline

| Data | Offline |
|---|---|
| Jadwal hari ini & detail order yang sudah dibuka | **Cache baca** (boleh dilihat tanpa sinyal) |
| Foto laporan | **Antre & kirim ulang otomatis** |
| Isian laporan unit (teks/angka) | **Draf lokal** per order, dikirim saat online |
| Pengeluaran | Antre dengan `client_uuid` |
| Absen datang/pulang, berangkat, check-in, tutup order, setoran | **Wajib online** (bergantung waktu/validasi server) — tampilkan pesan jelas, jangan pura-pura sukses |

Prinsip: **jangan pernah menampilkan "berhasil" untuk sesuatu yang belum diterima server.** Status tiap item antrean terlihat (menunggu / mengirim / gagal).

## 5. Risiko & mitigasi

| Risiko | Mitigasi |
|---|---|
| Kuota storage server penuh | Server menolak dengan kode khusus; app menampilkan pesan & tidak mengulang tanpa henti |
| Foto besar menghabiskan kuota data teknisi | Kompres agresif (sudah), opsi hanya-Wi-Fi |
| HP kelas bawah kehabisan memori saat kompres banyak foto | Proses berurutan, bukan paralel; hapus file lokal setelah sukses |
| Duplikat foto karena kirim ulang | `client_uuid` idempotent |
| Foto sementara menumpuk | Reuse pembersihan `foto:bersihkan`; hapus entri antrean yang order-nya sudah ditutup |

## 6. Pengujian

Tes API: unggah sukses, unggah ulang `client_uuid` sama (tanpa duplikat), kuota penuh → kode khusus, file bukan gambar/terlalu besar ditolak, foto order orang lain → 404. Tes app (manual terstruktur di HP Android kelas bawah): mode pesawat di tengah antrean, kill app saat mengunggah, sinyal naik-turun.
