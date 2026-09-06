# Usulan Paket Pengembangan — Landing Page, Pembayaran di Lokasi, Resi, & UI Teknisi

> STATUS: **DISETUJUI (7 September 2026)** — keputusan final: B13b, B14a,
> B15a, B16a, B17a, B18a, B19a. Sudah direkam di `02-keputusan-eksekusi.md`
> dan `database-schema.md`; dokumen ini disimpan sebagai catatan usulan awal.
> Dokumen ini menjawab arahan owner: landing page Paccing, opsi pembayaran
> QRIS/tunai di sisi teknisi saat order selesai, pengelolaan QRIS/rekening di
> admin, tampilan foto awal-selesai, konsep resi, dan modernisasi menu/UI
> teknisi. Setelah disetujui, keputusan final dipindahkan ke
> `02-keputusan-eksekusi.md` dan skema ke `database-schema.md`.

## 1. Konteks & Masalah Saat Ini

| # | Masalah | Kondisi sekarang |
|---|---|---|
| 1 | `/` masih halaman welcome Laravel | `routes/web.php` → `view('welcome')` |
| 2 | Teknisi tidak punya media pembayaran saat di lokasi | Channel QRIS/rekening belum ada di sistem |
| 3 | Admin tidak bisa kelola channel pembayaran | Tidak ada tabel/resource `payment_channels` |
| 4 | Foto before/after tidak muncul di preview selesai | `work_reports.foto_sebelum/sesudah` tersimpan tapi tidak ditampilkan di Filament (`OrderResource` infolist) maupun riwayat teknisi |
| 5 | Tidak ada resi/bukti digital | Belum ada konsep |
| 6 | UI teknisi fungsional tapi sederhana | Bottom nav 4 tab, layout 480px polos |

Keputusan lama yang TIDAK berubah: pencatatan pembayaran tetap oleh
Admin/Finance (keputusan B5 — income hanya saat `lunas`), arsitektur tetap
Filament + Livewire (T2), tidak ada endpoint REST baru.

## 2. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B13 — Peran teknisi dalam pembayaran
- **(a) Teknisi hanya MENAMPILKAN opsi bayar** (QRIS/rekening/tunai) kepada
  customer di halaman detail order yang selesai; pencatatan pembayaran tetap
  Admin/Finance. Minimal perubahan, tidak ada risiko duplikasi/inkonsistensi
  status.
- (b) Teknisi juga menandai metode yang dipilih customer (catatan opsional di
  order) — info tambahan utk admin saat konfirmasi.

Rekomendasi: **(a)** di Fase ini; (b) bisa ditambahkan belakangan tanpa migrasi.

### B14 — Bentuk "resi" saat order selesai
- **(a) Halaman resi publik bertoken**: route publik `resi/{order}/{token}`
  (token acak, tanpa login) menampilkan ringkasan: no. order, customer,
  layanan + unit, alamat, tanggal selesai, total tagihan, status pembayaran,
  foto sebelum-sesudah, dan (jika belum lunas) channel pembayaran aktif.
  Teknisi tinggal buka & share (WA/screenshot). Read-only, tanpa data sensitif
  lain.
- (b) Resi internal saja (halaman teknisi, screenshot manual), tanpa route
  publik.
- (c) Tunda resi ke Fase 2.

Rekomendasi: **(a)** — murah (1 route + 1 view), langsung menjawab "resi yang
bisa dikirim teknisi". WA otomatis tetap Fase 2 (B3).

### B15 — Jenis channel pembayaran yang dikelola
- **(a) QRIS (gambar + atas nama) dan Rekening Bank (nama bank, no. rekening,
  atas nama)**, masing-masing bisa aktif/nonaktif; tunai tidak perlu baris
  data (selalu tersedia sebagai opsi teks).
- (b) Tambah e-wallet (OVO/GoPay/dll) sejak awal.

Rekomendasi: **(a)**; kolom `jenis` enum (`qris`, `bank`) dibuat extensible
sehingga e-wallet bisa ditambah tanpa migrasi struktur.

### B16 — Siapa kelola & lihat channel
- **(a) CRUD: Admin/Owner; Finance & Teknisi read-only.** Konsisten dengan
  pola role yang ada.

### B17 — Landing page `/`
- **(a) Halaman publik brand Paccing**: logo (dari `dev-plan/UI/logo.png`
  disalin ke `public/assets/`), nama usaha, layanan aktif dari
  `service_catalog` (jenis + harga + estimasi), area layanan
  (Makassar/Gowa/Maros), kontak (telepon/WA + alamat statis dari konfigurasi),
  tombol Login. Tanpa form kontak di fase ini.
- (b) Landing + form kontak sederhana.

Rekomendasi: **(a)**. Tema mengikuti brand yang ada di `dev-plan/UI`.

### B18 — Struktur menu & navigasi teknisi
- **(a) Tetap 4 tab bawah** (Jadwal, Riwayat, Capaian, Akun); "Info
  Pembayaran" & galeri foto muncul di halaman Detail Order (tombol/aksi saat
  order selesai) + Riwayat menampilkan foto & status bayar.
- (b) Tambah tab ke-5 "Bayar" khusus order selesai belum lunas.

Rekomendasi: **(a)** — order yang relevan hanya yang sedang/sudah dikerjakan,
tetap nyaman diakses dari detail/riwayat.

### B19 — Cakupan modernisasi UI teknisi
Header bergradasi brand, kartu status dengan badge warna konsisten, galeri
foto before/after, empty-state yang rapi, aksesibilitas sentuh (target ≥44px),
tetap Tailwind v4 + Livewire (tanpa framework JS baru). Tidak mengubah
arsitektur.

## 3. Skema (usulan, menunggu persetujuan → akan masuk `database-schema.md`)

### Tabel baru: `payment_channels` (Fase 1)

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| nama | string | label tampil, mis. "QRIS Paccing", "BCA 1234567890" |
| jenis | enum: qris, bank | extensible ke ewallet |
| atas_nama | string, nullable | pemilik rekening/QRIS |
| nomor_rekening | string, nullable | khusus bank |
| nama_bank | string, nullable | khusus bank |
| gambar | string (path), nullable | foto QRIS (jenis qris) |
| aktif | boolean | default true; nonaktif tidak ditampilkan teknisi |
| dicatat_oleh | FK → users | |
| timestamps | | |

Catatan: `payments.metode` sudah punya nilai `qris`/`cash` — tabel channel
hanya "master media bayar", tidak mengubah tabel pembayaran sama sekali.

### Perubahan lain
- Tidak ada perubahan kolom tabel existing. `work_reports` foto sudah ada.
- Seeder: 1 contoh QRIS + 1 contoh rekening (data demo).

## 4. Breakdown Pengerjaan (setelah keputusan disetujui)

Urutan & pembagian agent (arsitektur tetap Livewire/Filament):

**Fase A — Fondasi (orchestrator/utama, wajib duluan agar dua sisi tidak bentrok):**
- Migrasi `payment_channels` + model + enum `PaymentChannelType` +
  `PaymentChannelService` (CRUD + query aktif utk teknisi) — mengikuti pola
  Service + RestrictsByRole + BusinessRuleException yang ada.
- Seeder data demo.
- Test fondasi (Pest) hijau.

**Fase B — Agent 1: sisi ADMIN (Filament/backoffice)**
- Resource `PaymentChannelResource` (CRUD + toggle aktif, upload gambar QRIS).
- Tampilkan `foto_sebelum`/`foto_sesudah` di infolist Order (ImageEntry) —
  memperbaiki gap #4.
- (Jika B14a disetujui) route publik resi + controller/view-nya.
- Test Pest sisi admin (resource CRUD, otorisasi role, tampil/tidaknya channel
  nonaktif).

**Fase C — Agent 2: sisi TEKNISI (Livewire mobile)**
- Halaman detail order: seksi "Info Pembayaran" saat order selesai — daftar
  channel aktif (gambar QRIS tampil besar, rekening dengan tombol salin) +
  opsi tunai.
- Galeri foto sebelum/sesudah di detail order selesai & riwayat.
- Modernisasi menu/UI teknisi (B18/B19): header, kartu, badge, empty-state.
- Test Pest sisi teknisi.

**Fase D — Landing page `/` (orchestrator/utama, bisa paralel dgn B/C)**
- Route `/` → view landing; salin aset logo dari `dev-plan/UI/` ke
  `public/assets/`; layanan diambil dari `service_catalog` aktif.
- Test: route `/` 200 + konten inti (smoke, tanpa login).

Catatan orchestrasi: B dan C menyentuh file berbeda (Filament vs Livewire) —
aman dijalankan paralel. Fase A mendefinisikan kontrak `PaymentChannelService`
supaya tidak ada duplikasi. Integrasi akhir + `php artisan test` penuh oleh
orchestrator.

## 5. Definisi "Selesai" untuk Paket Ini

1. `/` menampilkan landing page Paccing berlogo (bukan welcome Laravel).
2. Admin bisa tambah/edit/aktif-nonaktifkan QRIS & rekening bank.
3. Teknisi yang menyelesaikan order melihat channel aktif & tinggal
   memperlihatkannya ke customer; gambar QRIS & no. rekening tampil jelas.
4. Preview order selesai (admin & teknisi) menampilkan foto awal & selesai.
5. (Jika B14a) resi publik bertoken bisa dibuka & dibagikan teknisi.
6. UI teknisi lebih modern: header brand, kartu status, empty-state rapi.
7. Seluruh perubahan disertai test Pest; `php artisan test` hijau penuh;
   laporan file & status test dilaporkan per agent/milestone.

## 6. Batas & Non-Tujuan (fase ini)

- TIDAK membuat REST API/endpoint JSON baru (arsitektur T2 dipertahankan).
- TIDAK mengubah alur pencatatan pembayaran (tetap Admin/Finance, B5).
- TIDAK ada WA otomatis / payment gateway eksternal (tetap Fase 2, B3).
- TIDAK commit ke git dulu (sesuai arahan owner); setelah fitur + test hijau,
  keputusan commit dibahas terpisah.
