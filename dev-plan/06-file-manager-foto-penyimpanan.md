# Usulan — File Manager Foto & Kontrol Penyimpanan

> STATUS: **DISETUJUI (7 September 2026)** — B22a, B23a, B24a, B25a, B26a.
> Tercatat di `02-keputusan-eksekusi.md`; dokumen ini disimpan sebagai
> catatan usulan awal & acuan implementasi.

## 1. Konteks & Masalah

Foto pengerjaan teknisi (`work-reports/`), gambar QRIS (`payment-channels/`),
dan bukti pengeluaran tersimpan selamanya di disk `public` — tanpa batas.
Pada hosting berkapasitas kecil, disk bisa penuh diam-diam dan menggagalkan
upload berikutnya tanpa pesan yang jelas. Belum ada indikator "berapa disk
terpakai" untuk Owner/Admin.

Tujuan (arahan owner): kontrol gambar jangka panjang —
1. foto yang sudah tua (default **> 2 bulan**) dibersihkan otomatis,
2. admin bisa melihat pemakaian penyimpanan (progress bar, sisa, dst),
3. saat penyimpanan **penuh**, upload gambar baru DITOLAK dengan pesan jelas,
4. ambang batas & umur foto mudah dinaikkan (mis. jadi 3 bulan) saat kapasitas
   hosting dinaikkan.

## 2. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B22 — Ruang lingkup "File Manager"
- **(a) Monitor + kebijakan + guard** (tanpa halaman daftar file manual):
  widget dashboard "Penyimpanan Foto" (progress bar), pembersihan otomatis
  foto tua, dan penolakan upload saat penuh. Lihat riwayat file per order
  tetap lewat Work Report seperti sekarang.
- (b) Tambah halaman admin "Arsip Foto": daftar semua file foto (order, tanggal,
  ukuran) + tombol hapus manual. Lebih besar, menyusul bila diminta.

Rekomendasi: **(a)** — langsung menjawab kontrol jangka panjang; halaman arsip
bisa ditambahkan belakangan tanpa migrasi.

### B23 — Objek pembersihan otomatis (foto tua)
- **(a) Hapus file fisik + kosongkan kolom foto** pada `work_reports` yang
  berumur > ambang (riwayat/catatan laporan TETAP ada, gambar hilang — resi/
  detail menampilkan tanpa foto). Sekaligus bersihkan file **yatim** di folder
  `work-reports/` yang tidak punya referensi di DB (sisa upload gagal).
- (b) Hapus baris `work_reports` sekalian (menghapus riwayat — TIDAK
  direkomendasikan, merusak capaian & laporan).
- (c) Pindahkan ke folder arsip (bukan hapus) — menyusul bila perlu.

Rekomendasi: **(a)**. Yang dihitung umurnya: `work_reports.created_at`.
File NON-foto (logo, QRIS channel, bukti expense) **tidak pernah** dihapus
otomatis — hanya folder `work-reports/`.

### B24 — Ambang & konfigurasi
- **(a) Konfigurasi via config + .env** (tanpa tabel baru):
  - `FOTO_MAX_UMUR_HARI` default **60** (2 bulan) — nanti tinggal ubah ke 90
    (3 bulan) dst;
  - `FOTO_KUOTA_MB` default **1024** (1 GB) — naikkan saat hosting diperbesar;
  - folder yang diukur/dijaga: seluruh disk `public` (work-reports +
    payment-channels).
- (b) Simpan di tabel pengaturan + halaman admin utk ubah tanpa deploy.

Rekomendasi: **(a)** — murah & cukup untuk tahap ini; (b) menyusul bila owner
ingin ubah angka tanpa sentuh server. Cara ubah didokumentasikan di
`03-tech-setup.md`.

### B25 — Titik penolakan saat penyimpanan penuh
- **(a) Guard terpusat** `StorageQuotaService::pastikanCukup(bytes)` dipanggil:
  (1) form laporan teknisi SEBELUM file disimpan (termasuk saat memilih foto
  → pesan "Penyimpanan foto penuh..."), (2) upload gambar QRIS di panel admin
  (validasi sebelum file tersimpan), (3) service `TeknisiService::submitLaporan`
  (lapis kedua, defense-in-depth). Pesan ramah + langkah solusi (hubungi owner /
  naikkan kuota).
- (b) Tidak pakai guard sama sekali — TIDAK direkomendasikan.

Rekomendasi: **(a)**.

### B26 — Jadwal pembersihan
- **(a) Laravel Scheduler harian** (mis. 03:00) lewat command
  `php artisan foto:bersihkan`; di dev/test bisa dijalankan manual;
  dokumentasi cron VPS menyusul di bab deployment.
- (b) Pembersihan manual saja.

Rekomendasi: **(a)**.

## 3. Desain Teknis (ringkas)

### Komponen baru
1. `config/penyimpanan.php` — ambang dari env (quota_mb, max_umur_hari, disk).
2. `app/Services/StorageQuotaService.php`
   - `pakaiBytes(): int` — total ukuran folder disk public (cache pendek,
     mis. 5 menit; invalidasi setelah tulis);
   - `kuotaBytes(): int`, `persenTerpakai(): float`, `sisaBytes(): int`;
   - `pastikanCukup(int $tambahBytes): void` — lempar `BusinessRuleException`
     bila `pakai + tambah > kuota` (pesan Indonesia + sisa yang tersedia);
   - `formatBytes()` helper utk tampilan.
3. `app/Console/Commands/BersihkanFotoTua.php` (`foto:bersihkan`)
   - Hapus file `work_reports` dengan `created_at <= now - max_umur_hari`
     (foto_sebelum/sesudah): unlink bila ada + null-kan kolom;
   - Hapus file yatim di folder `work-reports/` yang tidak dirujuk DB;
   - Laporan ringkas jumlah dihapus/dibersihkan (log).
   - Registrasi schedule harian di `routes/console.php`.
4. `app/Filament/Widgets/PenyimpananFotoWidget.php` (blade kustom dengan
   progress bar): terpakai (X MB / Y MB • Z%), sisa, jumlah file foto, umur
   maksimal kebijakan saat ini, peringatan warna saat ≥90%; `canView` =
   Owner/Admin/Finance (Finance lihat? — default Owner/Admin; Finance read
   saja boleh, keputusan: Owner/Admin + Finance lihat).
5. Guard pemanggilan:
   - `OrderDetail.php` (Livewire teknisi) `submitLaporan()` → cek quota
     sebelum `->store(...)`; flash error bila penuh;
   - `PaymentChannelResource` FileUpload → aturan validasi quota;
   - `TeknisiService::submitLaporan()` → panggil quota (pertahanan kedua);
   - `StorageQuotaService` di-inject (bukan singleton state).

### Tabel/skema
TIDAK ada tabel baru. Perubahan hanya nilai kolom `work_reports.foto_sebelum/
foto_sesudah` → null saat dibersihkan.

### Definisi "Selesai"
1. Widget dashboard admin menampilkan progress bar pemakaian penyimpanan foto
   + sisa + kebijakan umur (angka real dari folder, bukan estimasi).
2. `php artisan foto:bersihkan` menghapus foto `work_reports` berumur > ambang
   dan file yatim; laporan riwayat tetap utuh (foto null); file muda tidak
   tersentuh; QRIS/logo tidak tersentuh.
3. Saat pemakaian ≥ kuota, teknisi TIDAK bisa mengirim foto laporan (pesan
   jelas), admin TIDAK bisa upload gambar QRIS; data lain tetap jalan.
4. Ambang & kuota bisa diubah lewat `.env` (60→90 hari; 1024→2048 MB dst)
   tanpa perubahan kode.
5. Test Pest hijau (unit quota/guard + feature cleanup + widget), laporan file
   & status test.

### Test yang direncanakan
- `StorageQuotaService`: hitung pakai/kuota/persen; `pastikanCukup` lolos &
  lempar saat penuh (Storage::fake, kuota kecil via config).
- `BersihkanFotoTua`: Carbon::setTestNow; report tua (foto dihapus + kolom
  null), report muda utuh, file yatim hilang, file di luar folder tidak
  tersentuh, QRIS channel tidak tersentuh.
- Teknisi submit laporan saat penuh → ditolak, tidak ada file/work_report baru.
- Widget render: tampil utk Owner/Admin; `canView` role lain false.

## 4. Batas & Non-Tujuan (versi ini)
- Bukan "file explorer" umum; fokus foto kerja + kontrol penyimpanan.
- Tidak ada kompresi/optimasi gambar & tidak ada object storage (S3).
- Arsip (bukan hapus) & pengaturan via UI: opsional, menyusul.
- Halaman "Arsip Foto" (B22b) menyusul bila diminta.
