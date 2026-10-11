# 01 — Usulan Revisi Portal Teknisi (masukan client)

> STATUS: **DIEKSEKUSI** (Fase A, B, C) 11 Okt 2026.
> Sumber: `dev-plan/revisiteknisi/LIST Portal Teknisi (2).pdf` (4 halaman, masukan client).
>
> **Fase A** — Poin 7 (label "Titik Pertama Hari ini (Games 2)" & "Check In Pertama — foto
> bukti sampai lokasi"), Poin 2 (kartu **Catatan Admin** di detail teknisi), Poin 1 (**sekat
> order kemarin belum selesai** di jadwal), Poin 4 (**gating**: order hanya bisa dibuka setelah
> absen datang hari itu — kartu dikunci + banner "Absen Sekarang").
> **Fase B** — Poin 6 (**centang "Customer sudah bayar"** di detail teknisi → kolom
> `orders.bayar_dilaporkan_teknisi/_pada/_oleh`; admin melihat kolom + filter "Lapor bayar").
> **Fase C** — Poin 5 (**aksi "Kirim Resi (WA)"** di admin, template `WhatsappTemplate::resi`,
> tombol muncul bila ada device WA & order punya resi token); Poin 3 (**foto profil teknisi**:
> `users.foto_profil`, unggah di halaman Akun, tampil di kartu sapaan jadwal).
>
> Tes: `PortalTeknisiRevisiTest` (5) + `PortalTeknisiFaseCTest` (3).

## 1. Ringkasan masukan (7 poin)

1. **Sekat orderan kemarin yang belum selesai/terkendala** di halaman jadwal teknisi.
2. **Tampilkan "Catatan Admin"** di portal teknisi.
3. **Foto teknisi di samping nama** (+ foto profil).
4. **Menu orderan hanya bisa diakses setelah teknisi absen** (biar tidak lupa absen).
5. **Resi terkirim ke customer setelah diverifikasi admin.**
6. **Opsi centang "customer sudah bayar"** di portal teknisi (nyambung ke admin, paid/unpaid).
7. **Revisi keterangan** halaman pertama: "Titik Pertama Hari ini (Games 2)" & "Check In Pertama, foto bukti sampai lokasi".

---

## 2. Analisis per poin

### Poin 1 — Sekat orderan kemarin (belum selesai/terkendala)
- **Masalah:** tanggal di kartu order (kotak merah) terlalu kecil; teknisi luput ada kerjaan kemarin
  yang belum selesai/terkendala → admin tidak diingatkan untuk menjadwalkan ulang.
- **Kode:** `app/Livewire/Teknisi/JadwalHariIni.php` menampilkan semua order aktif
  (`terjadwal/menuju_lokasi/dikerjakan/butuh_followup/terkendala`) diurut `tanggal_jadwal`,
  **tanpa pemisah** hari.
- **Usulan:** pisah jadi grup:
  1. **"Hari Ini"** (`tanggal_jadwal = today`).
  2. **"Belum Selesai Sebelumnya"** (`tanggal_jadwal < today`, status belum selesai/terkendala)
     dengan header mencolok + badge tanggal.
- **Berkas:** `JadwalHariIni.php`, `resources/views/livewire/teknisi/jadwal-hari-ini.blade.php`.

### Poin 2 — Tampilkan "Catatan Admin"
- **Masalah:** teknisi tidak tahu detail pekerjaan/kondisi unit dari `catatan_admin`.
- **Kode:** kolom `orders.catatan_admin` (dipakai admin saat buat order & log perubahan).
- **Usulan:** tampilkan kartu "Catatan Admin" di **detail order teknisi** (`Teknisi/OrderDetail.php`
  + view) dan/atau di kartu jadwal.
- **Berkas:** `app/Livewire/Teknisi/OrderDetail.php`, `resources/views/livewire/teknisi/order-detail.blade.php`.

### Poin 3 — Foto teknisi di samping nama
- **Masalah:** ingin foto profil tampil di samping nama teknisi.
- **Kode:** tabel `users` **belum punya kolom foto**. (Absen harian punya selfie `daily_attendances.foto_datang`.)
- **Usulan:** tambah kolom `users.foto_profil` (nullable) + unggah di halaman **Akun teknisi**
  (`Teknisi/Akun`) dan/atau admin (`UserResource`). Tampilkan avatar (fallback inisial) di
  daftar teknisi/jadwal. *(Perlu keputusan: foto profil baru vs pakai selfie absen terakhir.)*
- **Berkas:** migrasi `users.foto_profil`, `Teknisi/Akun.php` + view, `UserResource`, view jadwal.

### Poin 4 — Menu orderan hanya aktif setelah absen
- **Masalah:** teknisi lupa absen datang.
- **Kode:** absen harian = `daily_attendances` (jam_datang hari ini). Belum ada gating akses order.
- **Usulan:** bila teknisi **belum absen datang hari ini**, akses **detail order** diarahkan/diingatkan
  untuk absen dulu (mis. tombol "Absen dulu" + blokir masuk). Jadwal tetap bisa dilihat.
- **Berkas:** `Teknisi/JadwalHariIni`, `Teknisi/OrderDetail` (guard), `layouts.teknisi`/nav.

### Poin 5 — Resi terkirim ke customer setelah diverifikasi admin
- **Masalah:** resi ingin langsung sampai ke customer setelah admin verifikasi laporan.
- **Kode:** resi publik = `orders.resi_token` + `ResiController` (`/resi/{order}/{token}`),
  dibuat saat order `selesai` (`Order::pastikanResiToken()`). Integrasi **WA** sudah ada
  (`WhatsappService`, halaman Perangkat WA) — dipakai untuk kirim PDF/notifikasi.
- **Usulan:** saat admin **verifikasi laporan** (atau tombol **"Kirim Resi"** di admin), kirim
  tautan resi ke `customer.no_hp` via WA (template + log WA). *(Perlu keputusan: otomatis saat
  verifikasi vs tombol manual.)*
- **Berkas:** `OrderService::verifikasiLaporan`, `InvoiceService`/`WhatsappService`, `OrderResource`.

### Poin 6 — Centang "customer sudah bayar" di portal teknisi
- **Masalah:** status bayar tidak ter-update walau teknisi sudah unggah bukti pembayaran.
- **Kode:** teknisi unggah `bukti_pembayaran` via `Teknisi/OrderDetail::uploadBuktiPembayaran`;
  status bayar sesungguhnya = `payments.status` (dikelola admin lewat `PaymentService`).
- **Usulan:** tambah **centang "Customer sudah bayar"** di `OrderDetail` teknisi → menandai order
  siap diverifikasi admin (atau langsung ubah status ke `terkirim`/menunggu ACC). Admin melihat
  & meng-ACC dari panel; sinkron ke `payments`. *(Perlu keputusan: teknisi hanya "lapor bayar"
  lalu admin ACC, atau teknisi langsung mengubah status.)*
- **Berkas:** `TechnicianService`/`OrderService`, `Teknisi/OrderDetail.php` + view, panel admin.

### Poin 7 — Revisi keterangan halaman pertama
- **Masalah:** teks kurang pas.
- **Kode:** label di `resources/views/livewire/teknisi/jadwal-hari-ini.blade.php` &
  validasi `fotoTitikPertama` di `Teknisi/OrderDetail.php:727`.
- **Usulan:** ubah keterangan menjadi **"Titik Pertama Hari ini (Games 2)"** dan
  **"Check In Pertama — foto bukti sampai lokasi"**.
- **Berkas:** view jadwal + pesan validasi order-detail.

---

## 3. Fase eksekusi (usulan)

- **Fase A (cepat, tanpa migrasi):** Poin 7, 2, 1, dan gating Poin 4.
- **Fase B:** Poin 6 (centang bayar + ACC admin).
- **Fase C:** Poin 5 (kirim resi via WA) & Poin 3 (foto profil — perlu migrasi + upload).

## 4. Berkas terdampak (ringkas)

Livewire teknisi (`JadwalHariIni`, `OrderDetail`, `Akun`), view terkait; panel admin
(`OrderResource`, `UserResource`); service (`OrderService`, `TechnicianService`,
`WhatsappService`); migrasi (`users.foto_profil`, mungkin kolom penanda "lapor bayar").

## 5. Test (rencana)

- Sekat order kemarin tampil terpisah; tanggal mencolok.
- `catatan_admin` tampil di detail teknisi.
- Akses order diblokir bila belum absen hari ini (jadwal tetap).
- Centang "sudah bayar" menandai order & terlihat di admin.
- Kirim resi via WA tercatat di log WA.
- Label baru Poin 7 tampil.

## 6. Keputusan yang diminta

1. **Prioritas**: kerjakan semua 7, atau mulai **Fase A** dulu?
2. **Poin 3**: foto profil baru per teknisi (unggah di Akun), atau pakai **selfie absen** terakhir?
3. **Poin 5**: kirim resi **otomatis saat verifikasi admin**, atau lewat tombol **"Kirim Resi"** manual?
4. **Poin 6**: teknisi hanya **lapor "sudah bayar"** lalu admin yang ACC, atau teknisi langsung
   mengubah status bayar?
5. **Poin 4**: blokir **hanya detail order**, atau seluruh menu orderan?
