# Keputusan Eksekusi — One Gate System Paccing

Dokumen ini mencatat **jawaban final** atas semua pertanyaan terbuka di
[`../PRD.md`](../PRD.md) §12, [`00-overview.md`](00-overview.md), dan bagian
"Pertanyaan Terbuka" tiap dokumen modul. Disetujui oleh pemilik proyek pada
6 September 2026. Dokumen ini menjadi acuan saat implementasi; jika ada
perubahan keputusan, catat di sini dengan tanggal revisi.

## Keputusan Bisnis

| # | Pertanyaan (sumber) | Keputusan | Berlaku |
|---|---|---|---|
| 1 | Multi-item order (00-overview, schema §orders) | **TIDAK di Fase 1.** 1 order = 1 jenis layanan; `jumlah_unit` > 1 untuk unit sejenis. Tabel `order_items` ditunda Fase 2. | Fase 1 |
| 2 | Interval reminder cuci rutin (PRD §12, schema `service_reminders`) | Kolom `interval_bulan` per item di `service_catalog`. Default: cuci_ac = 3, service_ac = null, pengadaan_ac = null. | Fase 1 |
| 3 | Notifikasi WA otomatis (PRD §12, Admin §7) | **TIDAK di Fase 1.** Cukup notice dashboard admin via `service_reminders.status_notice`. Integrasi WA API (Fonnte/Wablas) Fase 2. | Fase 1 |
| 4 | Kebijakan stok minus (Admin §7, Teknisi §7) | Laporan teknisi **tetap boleh disubmit** walau `stok_saat_ini` kurang; stok boleh negatif dan muncul di alert restock admin. | Fase 1 |
| 5 | Pengakuan income dari DP (Finance §7) | Income dicatat **hanya saat `payments.status` = `lunas`** (cash basis sederhana). DP disimpan di `payments.jumlah_dibayar` tanpa trigger income. | Fase 1 |
| 6 | Harga nego per customer (Admin §7) | Harga order **mengikuti `service_catalog`**; tanpa fitur nego di Fase 1. | Fase 1 |
| 7 | Role aktif Fase 1 (PRD §3, 00-overview) | 5 role dibuat via `spatie/laravel-permission`, mendukung multi-role per user. Finance & HR dirangkap Owner/Admin. Seed 1 akun owner. | Fase 1 |
| 8 | Alur order `butuh_followup` (Teknisi §7) | Order **ditahan** pada status yang sama; teknisi dapat melanjutkan order yang sama (bukan membuat order baru). | Fase 1 |
| 9 | Satu order banyak teknisi (Teknisi §7) | **1 PIC teknisi** per order (`orders.teknisi_id`). Relasi many-to-many `order_technicians` ditunda Fase 2. | Fase 1 |
| 10 | Absensi karyawan non-lapangan (HRD §7) | Fase 1 hanya check-in/out teknisi di lokasi (terhubung `order_id`). Absen karyawan kantor masuk HRD Fase 2. | Fase 1 |
| 11 | Stok & lini pengadaan AC (PRD §12) | `stock_items` + `stock_movements` aktif Fase 1 (dipakai laporan teknisi & restock). Unit AC pengadaan dicatat sebagai transaksi order biasa; inventory/order_items penuh Fase 2. | Fase 1 |
| 12 | Skala & estimasi data | Asumsi UKM: 3–10 teknisi, ratusan customer, order puluhan/bulan. Arsitektur tidak berubah jika membesar; hanya volume. | Fase 1 |
| 13 | Peran teknisi saat pembayaran (04-usulan §B13) | Teknisi **menampilkan** channel bayar (QRIS/rekening/tunai) ke customer DAN **dapat menandai metode yang dipilih customer** (`orders.metode_dipilih`, opsional) sebagai info utk Admin. Pencatatan resmi pembayaran tetap Admin/Finance saat lunas (B5). | 7 Sep 2026 |
| 14 | Resi digital (04-usulan §B14) | **Route publik bertoken** `resi/{order}/{token}` (tanpa login, read-only): ringkasan order, foto sebelum-sesudah, status bayar + channel aktif bila belum lunas. Teknisi membuka & membagikan link/screenshot. WA otomatis tetap Fase 2 (B3). | 7 Sep 2026 |
| 15 | Jenis channel pembayaran (04-usulan §B15) | Tabel `payment_channels`, `jenis` enum `qris`/`bank` (extensible ke ewallet). Tunai tidak perlu baris data (selalu jadi opsi teks). | 7 Sep 2026 |
| 16 | Pengelola channel (04-usulan §B16) | CRUD + toggle aktif: **Admin/Owner**; Finance & Teknisi read-only. | 7 Sep 2026 |
| 17 | Landing page `/` (04-usulan §B17) | Halaman publik brand Paccing: logo, nama usaha, layanan aktif dari `service_catalog`, area layanan, kontak statis, tombol Login. Tanpa form kontak. | 7 Sep 2026 |
| 18 | Struktur menu teknisi (04-usulan §B18) | Tetap 4 tab bawah (Jadwal, Riwayat, Capaian, Akun); Info Pembayaran & galeri foto di Detail Order + Riwayat. | 7 Sep 2026 |
| 19 | Modernisasi UI teknisi (04-usulan §B19) | Header gradasi brand, kartu status berbadge, galeri foto before/after, empty-state rapi; tetap Tailwind v4 + Livewire. | 7 Sep 2026 |
| 20 | Kelola akun pengguna (pertanyaan owner 7 Sep) | Resource **Pengguna** di panel backoffice, dikelola Owner/Admin: daftar, buat akun (nama, email, HP, password, pilih role), ubah role/status, aktif/nonaktif, reset password. Tanpa hapus akun (nonaktif sbg pengganti); tanpa data karyawan HRD (Fase 2). Hanya Owner yang bisa membuat/mengubah akun ber-role Owner; Admin tidak bisa menonaktifkan/mengubah akun sendiri & akun Owner. Login & akses (panel `/admin` & mobile `/teknisi`) menolak user nonaktif. | 7 Sep 2026 |
| 21 | Multi-teknisi per order (revisi B9; arahan owner) | Order dapat di-assign ke **beberapa teknisi (tim)**. `orders.teknisi_id` tetap = **PIC pertama** (kompatibilitas/tampilan); tabel baru `order_technicians` berisi **seluruh anggota tim (termasuk PIC)**. Semua anggota melihat order di Jadwal/Riwayat/Capaian masing-masing. Aksi lapangan (slider berangkat, check-in, laporan, catat metode) boleh dilakukan **anggota mana pun**; attendance tercatat per user yang check-in; saat laporan disubmit, seluruh attendance terbuka anggota tim di-check-out; capaian dihitung per anggota tim. Non-anggota tetap dilarang (AuthorizationException). Admin: aksi "Assign Teknisi" (PIC) + "Tambah Anggota Tim"; lepas anggota menyusul. | 7 Sep 2026 |
| 22 | File manager foto & kontrol penyimpanan (06-usulan) | **Monitor + kebijakan + guard** (tanpa halaman daftar file manual): widget dashboard "Penyimpanan Foto" (progress bar terpakai/kuota), pembersihan otomatis foto tua, penolakan upload saat penuh. Arsip Foto manual: menyusul. | 7 Sep 2026 |
| 23 | Objek pembersihan foto tua (06-usulan) | Hapus **file fisik** `work_reports` berumur > ambang + **null-kan kolom foto** (riwayat laporan tetap utuh); bersihkan file yatim di folder `work-reports/`. QRIS/logo/bukti expense TIDAK dihapus otomatis. Basis umur: `work_reports.created_at`. | 7 Sep 2026 |
| 24 | Konfigurasi ambang (06-usulan) | Config + `.env` tanpa tabel baru: `FOTO_MAX_UMUR_HARI` default **60** (2 bln; bisa 90/120 dst), `FOTO_KUOTA_MB` default **1024** (1 GB; naik saat hosting besar), folder ukur/jaga = disk public. | 7 Sep 2026 |
| 25 | Blokir upload saat penuh (06-usulan) | Guard terpusat `StorageQuotaService::pastikanCukup()`: (1) form laporan teknisi sebelum file disimpan, (2) upload gambar QRIS admin (validasi), (3) `TeknisiService::submitLaporan` (lapis kedua). Pesan ramah berisi solusi. | 7 Sep 2026 |
| 26 | Jadwal pembersihan (06-usulan) | Command `php artisan foto:bersihkan` + Laravel Scheduler harian 03:00 (cron VPS didokumentasikan saat deployment). | 7 Sep 2026 |
| 27 | Halaman kelola penyimpanan (07-usulan; revisi B22a) | Widget dashboard **dipindah** jadi halaman menu **Manajemen → "Penyimpanan"** (custom Filament page): ringkasan kuota (progress bar) + daftar file + pratinjau & hapus. `PenyimpananFotoWidget` dihapus. | 8 Sep 2026 |
| 28 | Cakupan file halaman (07-usulan) | Semua file folder terkelola: `work-reports/` (foto laporan) **dan** `payment-channels/` (QRIS), termasuk file **yatim** — konsisten dengan kuota yang menghitung kedua folder. | 8 Sep 2026 |
| 29 | Sumber daftar file (07-usulan) | **Scan folder real-time** saat halaman dibuka (tanpa tabel DB baru, tanpa migrasi); referensi dicocokkan dari kolom DB (`work_reports.foto_sebelum/sesudah`, `payment_channels.gambar`) utk label. | 8 Sep 2026 |
| 30 | Aksi kelola (07-usulan) | Pratinjau besar + **hapus per file & massal**: buang file fisik + null-kan kolom DB perujuk (riwayat order/laporan tetap utuh, selaras B23a); tombol "bersihkan foto lama sekarang" menjalankan `foto:bersihkan` dari UI. | 8 Sep 2026 |
| 31 | Izin halaman (07-usulan) | Owner/Admin: lihat + hapus; Finance: lihat saja; HR/Teknisi: 403. | 8 Sep 2026 |
| 32 | Slider penutup order teknisi (klarifikasi owner 8 Sep) | Setelah order `selesai` & teknisi menandai metode pilihan customer (B13b), muncul slider **"Selesaikan Order"** sebagai langkah penutup: mencatat `orders.ditutup_pada` dan **mengunci metode** (chip & hapus pilihan tidak tampil lagi; `catatMetodeDipilih` ditolak). Syarat slider: status selesai, belum lunas, metode sudah dipilih, belum ditutup; layar sukses + tombol kembali ke Jadwal setelah digeser. | 8 Sep 2026 |
| 33 | Siklus status notice (gab H-7) | DITUNDA — tidak dieksekusi (klarifikasi timeout; prioritas B35). Rencana: scheduler harian menandai `siap_dihubungi` saat H-7 & auto-close `selesai` saat customer order/lunas lagi. | 8 Sep 2026 |
| 35 | Buat Notice Manual di admin (klarifikasi timeout 8 Sep — DIJALANKAN sbg asumsi sesuai keluhan "tidak ada tambah") | Menu Notice Servis Berikutnya mendapat tombol **"Buat Notice Manual"** (Admin/Owner; Finance tetap lihat saja): pilih customer → order acuan (terakhir, non-batal) → interval bulan (default dari katalog layanan order, bisa diubah) → tanggal servis berikutnya (default hari ini + interval, min hari ini). Service `PaymentService::buatReminderManual()`: larang duplikat per order (satu notice per order), tolak bila layanan tanpa interval. Policy: `create` & `viewAny` kini mencakup Owner (sebelumnya Owner tak bisa lihat menu ini — inkonsistensi diperbaiki). | 8 Sep 2026 |
| 36 | Import Pengguna dari Excel (08-usulan; klarifikasi timeout 8 Sep — DIJALANKAN sbg asumsi) | Halaman Pengguna: tombol **"Import Excel"** (modal upload .xlsx/.csv + password default) & **"Unduh Template"** (.xlsx: header `nama\|email\|no_hp\|role\|password\|status` + contoh + sheet petunjuk). Batas 200 baris/2MB. Role: owner/admin/finance/hr/teknisi. Password per baris opsional → default `paccing123` (bisa diubah lewat kolom/form). Duplikat email (DB atau dalam file) & baris tidak valid DILEWATI tanpa mengubah akun lama; baris role owner oleh importir Admin dilewati (aturan B20); laporan hasil ringkas + rincian ≤15 baris. Akses Admin/Owner. Implementasi: `UserImportService` + PhpSpreadsheet (terpasang). | 8 Sep 2026 |
| 37 | Manajemen Info Usaha (09-usulan; klarifikasi owner 8 Sep — B37a–f) | Tabel `business_infos` baris tunggal (id=1) + `BusinessInfoService` (cache pendek, fallback buat baris); menu **Manajemen → "Info Usaha"** (Owner/Admin edit): nama usaha*, alamat, kontak WA, email, nama pemilik, logo (PNG/JPG/SVG ≤2MB, storage public `business/`, guard kuota B25, hapus logo → fallback). Dipakai konsisten di: sidebar admin + head title + login (brandName/logo panel via closure), portal teknisi (login & header), landing (placeholder alamat/WA terganti), & kop resi. | 8 Sep 2026 |
| 38 | Landing profesional + menu Website (11-usulan; klarifikasi timeout 8 Sep — DIJALANKAN sbg asumsi B38a–g) | Grup menu **"Website"** (Owner/Admin): (1) **Hero Slider** CRUD (gambar landscape folder `hero/`, judul/subjudul/CTA, urutan, aktif; guard kuota; fallback hero gradasi bila kosong); (2) **Layanan Beranda** (baris service_catalog: toggle tampil_beranda, urutan, gambar, deskripsi — harga/interval dari katalog; landing fallback = semua layanan aktif bila belum ada pilihan); (3) **Pengaturan Beranda** (Google Maps embed URL, jam operasional, sosmed IG/FB, toggle seksi layanan/cara kerja/area/peta). Landing ditulis ulang profesional: topbar → header sticky → carousel/fallback → keunggulan → layanan kartu → cara kerja → area + peta (sembunyi bila embed kosong) → CTA → footer. Testimoni & blog: Fase 2. | 8 Sep 2026 |

## Keputusan Teknis

| # | Pertanyaan | Keputusan |
|---|---|---|
| T1 | Hosting akhir | VPS entry-level (mendukung Laravel Task Scheduler untuk reminder & queue). Deployment detail menyusul; tidak menghalangi development lokal. |
| T2 | UI toolkit | **Hybrid**: Laravel 12 + Filament 3 panel untuk backoffice (Owner/Admin/Finance/HR) + halaman mobile-first (Blade + Alpine/Livewire) khusus Teknisi: slider "mulai berangkat", check-in/out, form laporan + foto. |
| T3 | Database development | SQLite untuk dev & test lokal (cepat, tanpa dependensi server); production tetap MySQL. Migrasi ditulis portable (tanpa fitur spesifik DB). |

## Konvensi Testing (wajib, per arahan Lead QA)

1. Setiap pembuatan/ubahan kode WAJIB disertai Unit/Feature Test (Pest).
2. Setiap selesai perubahan, jalankan `php artisan test` sampai hijau (PASS).
3. Error/FAIL diperbaiki oleh engineer, bukan di-skip.
4. Laporan status test & coverage dilaporkan per milestone.

## Status

- 6 September 2026: seluruh keputusan di atas disetujui pemilik proyek.
- 7 September 2026: B13–B19 disetujui (paket landing page, channel pembayaran
  QRIS/rekening, resi publik, modernisasi UI teknisi) — usulan awal di
  `04-usulan-landing-pembayaran-ui.md`.
- 7 September 2026: B20 disetujui (resource Pengguna di panel — kelola akun,
  role, aktif/nonaktif, reset password).
- 7 September 2026: B21 disetujui (multi-teknisi per order — revisi B9;
  lihat `05-multi-teknisi-tim.md`).
- 7 September 2026: B22–B26 disetujui (file manager foto & kontrol
  penyimpanan — lihat `06-file-manager-foto-penyimpanan.md`).
- 8 September 2026: B27–B31 disetujui (widget penyimpanan dipindah jadi menu
  Manajemen → "Penyimpanan" dengan daftar file, pratinjau, hapus per
  file/massal, & tombol bersihkan — revisi B22a; lihat
  `07-penyimpanan-jadi-menu.md`).
- 8 September 2026: B32 disetujui (slider "Selesaikan Order" teknisi setelah
  pilih metode; kunci metode + catat `orders.ditutup_pada` — klarifikasi owner).
- 8 September 2026: B35 dijalankan atas dasar asumsi (klarifikasi timeout;
  sesuai keluhan "di admin tidak ada tambah") — tombol "Buat Notice Manual"
  di menu Notice Servis Berikutnya; B33 (siklus status otomatis) ditunda.
- 8 September 2026: B36 dijalankan atas dasar asumsi (klarifikasi timeout) —
  Import Pengguna dari Excel (.xlsx/.csv) + Unduh Template di halaman
  Pengguna; lihat `08-usulan-import-excel-pengguna.md`.
- 8 September 2026: B37 disetujui (Manajemen Info Usaha — nama, alamat,
  kontak, owner, logo; dipakai admin/teknisi/landing/resi; lihat
  `09-usulan-info-usaha.md`).
- 8 September 2026: B38 dijalankan atas dasar asumsi (klarifikasi timeout) —
  landing profesional + menu Website (Hero Slider, Layanan Beranda,
  Pengaturan Beranda); lihat `11-usulan-landing-profesional-konten.md`.
- 17–18 September 2026: B39–B55 disetujui & DIEKSEKUSI (absensi kantor
  teknisi via QR + skema insentif "Games 1–4" dari Bendahara YDF): menu
  Absensi & Insentif (Kode Absensi, Pengaturan Absensi, Rekap Absensi,
  Rekap Insentif, Cuci Motor); Games 1 (hadir) + denda telat + toleransi
  lembur, Games 2 (titik pertama via slider check-in), Games 3 (cuci
  motor), Games 4 (kepulangan: titik & mode jalan); order klaim
  dikecualikan dari hitungan titik/mode jalan (B50); verifikasi foto
  Setujui/Tolak sebelum masuk total gaji (B55). Games 5 & 6 (omset & unit)
  masih entri manual — menunggu definisi omset final dari Bendahara YDF
  (§7 dokumen di bawah). 61 test Pest baru. Lihat
  `15-usulan-absensi-barcode-teknisi.md`.
- 18 September 2026: perbaikan `route()`/`url()` supaya ikut `APP_URL`
  (aman untuk hosting subfolder, mis. `domain.com/paccing/public`) —
  sebelumnya redirect setelah login teknisi lompat ke root domain, hilang
  subfolder-nya. Perlu `APP_URL` di `.env` produksi diisi lengkap dengan
  subfolder + `php artisan config:clear` di server.
- Perubahan setelah tanggal ini harus dicatat di sini.
