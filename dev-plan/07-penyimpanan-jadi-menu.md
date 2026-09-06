# Usulan — Halaman "Penyimpanan" di Menu Manajemen (revisi B22)

> STATUS: **DISETUJUI (8 September 2026)** — B27a, B28a, B29a, B30a, B31a.
> Tercatat di `02-keputusan-eksekusi.md`; dokumen ini disimpan sebagai
> catatan usulan awal & acuan implementasi. B22a bagian "widget dashboard"
> digantikan B27 (widget dihapus, jadi halaman menu); B23–B26 tidak berubah.

## 1. Konteks

Arahan owner (8 Sep): jangan taruh pemantauan penyimpanan di dashboard —
pindahkan jadi **menu di Manajemen → "Penyimpanan"**, dan di halaman itu admin
bisa **melihat gambar, mengelola, dan menghapus file** yang memenuhi storage.

Kondisi sekarang: `PenyimpananFotoWidget` tampil di dashboard `/admin`
(Owner/Admin/Finance) berupa progress bar tanpa daftar file. Hapus manual file
belum ada — hanya pembersihan otomatis foto tua (B23) & hapus via resource
aslinya (QRIS bisa dihapus lewat Channel Pembayaran).

## 2. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B27 — Lokasi & bentuk
- **(a) Halaman menu "Penyimpanan" di grup navigasi Manajemen** (satu halaman
  Filament: ringkasan kuota + tabel file + aksi hapus). **Widget dashboard
  PenyimpananFotoWidget dihapus** (statistik pindah ke halaman ini).
- (b) Halaman baru TAPI widget dashboard tetap dipertahankan juga.

Rekomendasi: **(a)** — sesuai arahan "jangan simpan di dashboard".

### B28 — Cakupan file yang dikelola di halaman
- **(a) Semua file di folder penyimpanan terkelola**: foto laporan
  (`work-reports/`) **dan** gambar QRIS (`payment-channels/`), termasuk file
  **yatim** (tanpa referensi DB). Konsisten dengan kuota yang menghitung
  kedua folder itu; admin bisa lihat "isi" storage secara utuh.
- (b) Hanya foto laporan (`work-reports/`).

Rekomendasi: **(a)**.

### B29 — Cara menampilkan daftar file
- **(a) Scan folder langsung saat halaman dibuka** (real-time; tanpa tabel DB
  baru; tanpa migrasi). Referensi tiap file dicocokkan dari kolom DB
  (`work_reports.foto_sebelum/foto_sesudah`, `payment_channels.gambar`) →
  label: "Foto #Order X (laporan tgl …)" / "QRIS: nama channel" / "Tanpa
  referensi". Dilengkapi pencarian, filter folder/status, urut ukuran & umur,
  pagination.
- (b) Tabel DB baru untuk tiap file (migrasi + sinkronisasi di setiap titik
  upload/hapus) — lebih berat, rawan beda data antara DB & disk.

Rekomendasi: **(a)** — skala file kecil (±ratusan), data selalu akurat.

### B30 — Aksi hapus & pratinjau
- **(a) Lihat pratinjau besar (modal) + hapus per file + hapus massal
  (checkbox)**. Hapus = buang file fisik **dan** kosongkan kolom DB yang
  merujuknya (foto laporan/QRIS channel) — riwayat order/laporan **tetap
  utuh** (selaras B23a). Ada tombol "Jalankan pembersihan otomatis sekarang"
  (menjalankan `foto:bersihkan` dari UI).
- (b) Hapus sekalian baris laporan/order — TIDAK direkomendasikan.

Rekomendasi: **(a)**.

### B31 — Izin akses
- **(a) Owner & Admin: lihat + hapus. Finance: lihat saja** (statistik &
  daftar file tanpa tombol hapus) — selaras pola B16a.
- (b) Hanya Owner & Admin yang bisa membuka halaman (Finance tidak).
- (c) Finance juga boleh hapus.

Rekomendasi: **(a)**.

## 3. Desain Teknis (ringkas, jika B27a–B31a disetujui)

### Komponen baru
1. `app/Services/FilePenyimpananService.php` (atau metode baru di
   `StorageQuotaService` — diputuskan saat eksekusi):
   - `daftarFile(): Collection` — scan `work-reports/` + `payment-channels/`
     via `Storage::disk('public')`; per file: path, nama, folder, ukuran,
     mtime, status referensi (`foto_sebelum`/`foto_sesudah`/`qris`/`yatim`),
     label (nomor order + tanggal laporan, atau nama channel);
   - `hapusFile(string $path): void` — hapus file fisik; null-kan kolom
     DB yang merujuk (bisa 0–2 kolom); panggil
     `StorageQuotaService::lupakanCache()`. Throw `BusinessRuleException`
     bila gagal.
2. `app/Filament/Pages/KelolaPenyimpanan.php` — custom page Filament
   (`navigationGroup = 'Manajemen'`, label "Penyimpanan",
   icon heroicon-o-photo / hard-drive; `canAccess()` role):
   - ringkasan: progress bar terpakai/kuota/sisa/jumlah file/umur maks
     (dipindah dari widget);
   - tabel file (Tailwind konsisten Filament): thumbnail, nama, asal,
     ukuran, umur, status; search + filter; aksi hapus per baris
     (konfirmasi), checkbox massal, modal pratinjau besar;
   - tombol "Bersihkan foto lama sekarang" (menjalankan command, menampilkan
     ringkasannya).
3. Hapus: `app/Filament/Widgets/PenyimpananFotoWidget.php` +
   `resources/views/filament/widgets/penyimpanan-foto-widget.blade.php`.

### Tidak berubah
- B23–B26 (command `foto:bersihkan`, jadwal 03:00, guard upload, config/env).
- Resource lain & portal teknisi.

### Definisi "Selesai"
1. Sidebar admin grup Manajemen punya menu "Penyimpanan"; dashboard tidak
   lagi menampilkan widget penyimpanan.
2. Halaman menampilkan ringkasan kuota + tabel semua file terkelola dengan
   pratinjau, pencarian, filter, pagination.
3. Hapus (per file & massal) membuang file + mengosongkan kolom DB terkait;
   file yatim bisa dihapus; riwayat order/laporan tetap utuh; kuota cache
   di-reset.
4. Tombol "bersihkan sekarang" menjalankan pembersihan otomatis.
5. Owner/Admin bisa hapus; Finance lihat saja; role lain tidak bisa membuka
   halaman.
6. Test Pest hijau & laporan file/status test.

### Test yang direncanakan
- `daftarFile()`: memetakan file → status/label benar (foto laporan, QRIS,
  yatim); hitung ukuran.
- `hapusFile()`: file + kolom null; yatim hilang; cache di-reset.
- Halaman: `canAccess` per role; render list (Storage::fake) menampilkan
  file & ringkasan; aksi hapus via HTTP (Livewire) bekerja; Finance tidak
  melihat tombol hapus; hapus massal.
- Regresi: command `foto:bersihkan` tetap hijau; widget lama dihapus dari
  test.
