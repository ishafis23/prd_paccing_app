# 21 — Laporan Eksekusi & Capaian (Revisi Klien 10 Okt 2026)

> Berkas ini ditulis **supervisor** (sesi Hermes) setelah semua fase dieksekusi agent.
> Sumber pekerjaan: `dev-plan/21-usulan-omset-jasa-material-laporan-foto-invoice-dan-fix-unit.md`
> (STATUS di dokumen itu: ✅ DISETUJUI, siap dieksekusi per fase).
> Isi laporan ini: apa yang dikerjakan, apa yang **saya buktikan sendiri**, apa yang **menyimpang** dari
> teks plan, dan apa yang **belum** selesai. Tanggal: 10 Oktober 2026.

---

## 1. Cara kerja (supaya jelas siapa mengerjakan apa)

- **Eksekutor kode**: agent CLI Claude (model `sonnet`, satu agent per fase, print mode, keluar sendiri).
  Satu fase = satu brief tertulis di `_brief/NN-*.md` + satu commit.
- **Supervisor**: sesi Hermes ini (deepseek). Tugasnya menulis brief, mengunci daftar berkas yang boleh
  disentuh tiap agent, mengambil backup DB sebelum tiap gelombang, lalu **menjalankan ulang semua
  verifikasi sendiri** dan membaca diff sebelum commit.
- **Aturan yang dipaksakan ke semua agent**: dilarang menjalankan perintah git (commit dilakukan
  supervisor), dilarang `migrate:fresh`/`rollback`/`db:wipe`, dilarang menyentuh `.env`, dilarang
  menyentuh berkas milik agent lain, dilarang menyentuh aplikasi/proses lain di mesin.
  Satu agent dilaporkan melanggar ringan (`git status --short` sekali, fase 6) — read-only, tidak ada
  add/commit yang terjadi.
- Dua agent hanya berjalan paralel ketika daftar berkasnya benar-benar terpisah (fase 3 ∥ fase 4);
  sisanya berurutan karena berbagi berkas.

---

## 2. Status per revisi klien

| # | Revisi klien | Hasil | Status vs plan | Bukti |
|---|---|---|---|---|
| A | Order 3 unit tertulis "1 unit" | `Order::jumlahUnit()` + `ringkasanLayanan()` dari `order_items`; kolom `orders.jumlah_unit` diisi benar saat order dibuat + migrasi backfill; 4 titik tampilan diganti | **Sesuai** | commit `1b8076c`, `_brief/laporan-01.md` |
| B | Pisah omset jasa vs material | `order_items.komponen` (jasa/material) + `service_catalogs.mode_omset`; baris penyesuaian pada koreksi total; Finance/Akuntan menjumlah per komponen | **Sesuai** (+ 1 kolom tambahan, §5) | commit `1b8076c` |
| C | Dashboard omset per kategori + tabel jasa/material + filter | `OmsetService` (satu sumber), kartu per kategori, widget tabel Pekerjaan/Jasa/Material/Total + baris Total, filter Bulan ⇄ rentang + preset, ekspor CSV; Dashboard Pimpinan dapat kolom jasa/material; aturan tanggal pendapatan disatukan | **Sesuai** | commit `ccab4d6`, `_brief/laporan-02.md` |
| D | Keterangan foto per unit + menu "Lengkapi Laporan" | `order_unit_reports` (posisi/suhu/RPM/kondisi/bagian) + accordion Unit 1..N, `work_report_photos.unit_no` + FK per unit, `field_set` per slot, `fotoWajibKurang()` mencakup keterangan, halaman + menu Lengkapi Laporan, tab admin Laporan Pengerjaan | **Sesuai setelah perbaikan 4b** | commit `aec49fa` + `7736e31`, `_brief/laporan-03.md`, `laporan-05.md` |
| E | Preview & PDF laporan format Circle K (+ laporan bulanan) | `LaporanPengerjaanService` + `LaporanPdfService` (dompdf), preview HTML, unduh PDF per order, laporan bulanan per customer (job + mode sinkron), kompres foto GD ≤1000px | **Sesuai setelah perbaikan 5b** | commit `ddf06af` + `e9026ed`, `_brief/laporan-06.md`, `laporan-07.md` |
| F | Invoice per order + lampiran laporan | `invoices`/`invoice_orders`/`invoice_items`, nomor `INV-YYYYMM-0001` (urut & reset bulanan, di dalam transaksi + unique index), PDF faktur + opsi lampiran laporan dalam satu dokumen, tautan publik bertoken + tombol WA, bank di Info Usaha | **Sesuai + 1 perluasan** (§5) | commit `d674cdc`, `_brief/laporan-09.md` |

Semua keputusan user di `dev-plan/21` §9 (10 butir) dipatuhi. Keputusan §9-8 (nomor
`INV-YYYYMM-0001`) dan §9-7 (dompdf) terbukti terlaksana di kode.

---

## 3. Perubahan skema (10 migrasi baru; 1 migrasi lama ikut jalan)

| Migrasi | Isi |
|---|---|
| `2026_10_03_000001` (lama, tadinya Pending) | kolom `dibatalkan*` di `order_items` |
| `2026_10_10_000001` | backfill `orders.jumlah_unit` dari `order_items` |
| `2026_10_10_000002` | `order_items.komponen` + `penyesuaian` (+ backfill komponen) |
| `2026_10_10_000003` | `service_catalogs.mode_omset` |
| `2026_10_11_000001` | tabel `order_unit_reports` (unique `order_id`+`unit_no`) |
| `2026_10_11_000002` | `work_report_photos.unit_no` + `order_unit_report_id` |
| `2026_10_11_000003` | `photo_report_templates.field_set` (+ seed slot Cuci AC) |
| `2026_10_12_000001` | tabel `laporan_bulanan` (status laporan bulanan) |
| `2026_10_13_000001` | `order_unit_reports.rpm` → `decimal(8,1)` (agar "RPM 7,3" bisa disimpan) |
| `2026_10_14_000001` | `invoices`, `invoice_orders`, `invoice_items`, kolom bank di `business_infos` |

**Sudah diuji pada dua mesin DB**: SQLite (dev + test `:memory:`) **dan MariaDB 10.4** — saya buat
database sekali pakai di MySQL lokal dan menjalankan seluruh migrasi di sana; semua `DONE`, kolom &
unique index terverifikasi lewat `information_schema` (termasuk `rpm decimal(8,1)`,
`invoices_nomor_unique`, `invoice_orders_invoice_id_order_id_unique`).
Tidak ada migrasi yang menghapus kolom/tabel data. Satu migrasi bersifat backfill data
(`jumlah_unit`) dan satu backfill komponen — keduanya idempotent.

---

## 4. Verifikasi (angka nyata, bukan klaim)

- **Suite penuh**: `13 failed, 873 passed (2784 assertions)`. Ke-13 kegagalan itu **sudah ada sebelum
  revisi ini** — saya buktikan dengan mengembalikan working tree ke commit terakhir sebelum pekerjaan
  (`f00510d`) lalu menjalankan ulang test yang sama: hasilnya identik 13 gagal.
  Rincian: `ExpenseTrackingTest` ×7 (`Class "App\Http\Controllers\TeknisExpense" not found` di
  `TeknisExpenseController.php:296` — import kelas salah), `PhotoUploadTest` ×5 (endpoint foto lama),
  `OrderAcUnitLinkTest` ×1 (test mencari teks "Unit AC" di infolist).
- **Test baru**: 8 (JumlahUnit) + 10 (OmsetKomponen) + 12 (OmsetDashboard) + 16 (UnitReport) +
  8 (UnitReportFotoPerUnit) + 2 (tambahan LaporanPengerjaan) + 20 (Invoice) = **76 test baru**.
- **Verifikasi independen supervisor** (skrip sendiri, transaksi di-rollback, file uji dihapus):
  - jumlah unit & omset: order 3 unit → "3 unit" 300rb (jasa) → +1 baris material 55rb → 355rb
    (jasa 300rb/material 55rb) → koreksi −20rb → 335rb. Persis keluhan klien.
  - foto per unit: unggah foto ke Unit 2 → tersimpan `unit_no=2`, `order_unit_report_id=2`;
    Unit 1 & 3 tidak berubah.
  - PDF laporan: 6 foto = **1 halaman**, 201.942 byte; kop + judul + footer tampil; caption
    `CK DADI · UNIT DUA · DI DEPAN KASIR · CUCI STANDAR · RPM 7,3 · SUHU 17,5°C`.
  - invoice: `INV-202610-0001`, `INV-202610-0002`, November mulai `INV-202611-0001`; 2 order dalam
    1 invoice, total 530.000; saldo 430.000 setelah pembayaran 100.000; faktur 1 halaman
    (32 KB), dengan lampiran 3 halaman; token benar 200, token salah 404, invoice draft 404;
    token tidak bocor ke isi PDF.
  - **Halaman PDF saya lihat sendiri** (rasterisasi `pypdfium2` + pemeriksaan gambar), bukan hanya
    membaca laporan agent.
- **Bersih setelah diuji**: DB dev kembali `orders=1, invoices=0, order_unit_reports=0`; tidak ada
  berkas foto/PDF uji tertinggal di `storage/`.
- Satu kali angka suite sempat 16 gagal: penyebabnya **file cache hasil resize milik skrip verifikasi
  saya sendiri** (bukan kode produk). Setelah dibersihkan, angka kembali 13 (baseline).

---

## 5. Penyimpangan / keputusan di luar teks plan

| Hal | Alasan |
|---|---|
| Kolom tambahan `order_items.penyesuaian` (boolean) | menandai baris koreksi total agar tidak dihitung sebagai "unit" di `jumlahUnit()`, tetapi tetap masuk total & split omset |
| Default Jasa/Material pakai daftar kata barang (`IncomeCategory::KATA_BARANG`) | plan hanya menyebut "Pengadaan/Sparepart → material"; daftar kata ini bisa diubah kapan saja |
| **Fase 4 pertama belum menempelkan foto per unit** (foto hanya di unit pertama baris) | saya temukan sendiri dari kode + uji nyata; diperbaiki di fase 4b (`7736e31`) |
| **Tata letak PDF Fase 5 pertama salah** (kop hilang di halaman lanjutan, halaman terbuang, ukuran foto px→cm 1:1) | saya temukan dengan merasterisasi PDF dan melihatnya; diperbaiki di fase 5b (`e9026ed`) |
| Kolom `rpm` diubah ke `decimal(8,1)` | contoh klien menulis "RPM 7,3", kolom lama bulat; form teknisi ikut diperbaiki (fase 5c) |
| **Invoice langsung mendukung multi-order**, bukan 1 order dulu | invoice contoh klien (`INV110037`) memuat 4 tanggal kunjungan dalam 1 lembar; pivot `invoice_orders` memang sudah dirancang untuk ini |
| Kirim via WA = tautan `wa.me` (bukan integrasi API) | tidak ada integrasi WA di kode; plan §7 memang menyebut pola ini |
| Satu test lama disesuaikan (`PhotoReportTemplateTest`) | "lengkap" kini berarti foto **dan** keterangan; assertion asli tidak dilemahkan |
| `.gitignore` + `/​_brief/`, `/​_backup/`, `/​_log/` | artefak kerja agent jangan ikut masuk repo |

---

## 6. Yang belum selesai / perlu keputusan Anda

1. **13 test merah pra-eksisting** (bukan dari revisi ini) — dua kelompok bug nyata:
   `TeknisExpenseController.php:296` memanggil kelas `App\Http\Controllers\TeknisExpense` yang tidak
   ada (seharusnya model `TeknisiExpense`), dan endpoint foto lama (`order_photos`) balas 404/500.
   Belum diperbaiki karena di luar plan 21. **Sarankan jadi plan berikutnya** (kecil, jelas).
2. **Admin belum punya UI unggah foto per unit** — hanya lewat service; teknisi sudah bisa.
3. **Queue worker di hosting**: laporan bulanan punya tombol "Buat sekarang (sinkron)", jadi tetap
   jalan tanpa worker, tetapi mode antrean belum pernah diuji dengan worker sungguhan.
4. **MySQL yang saya uji = MariaDB 10.4** (XAMPP di laptop). Kalau hosting memakai MySQL 8 atau
   MariaDB versi lain, migrasinya tetap portabel (tidak ada SQL mentah khusus), tetapi ini bukan
   uji pada versi hosting Anda.
5. **Database uji MySQL saya tertinggal**: `crm_migrasi_cek_hermes` (62 tabel kosong) di MariaDB
   lokal port 3306. Perintah `DROP DATABASE` saya butuh persetujuan Anda dan tidak dijalankan —
   silakan hapus sendiri atau beri izin.
6. **`dev-plan/revisi/` belum di-commit** (screenshot & PDF dari klien; repo ini sengaja mengabaikan
   ekspor chat WA karena memuat nomor pribadi, jadi saya tidak memasukkannya tanpa izin).
7. **Plan 22 (portal corporate)** sudah ditulis agent lain: `dev-plan/22-usulan-portal-corporate-customer.md`,
   status USULAN, menunggu keputusan Anda (§9 dokumen itu: akun per PIC, undangan bertoken, batas
   per cabang, tampil setelah verifikasi admin, dsb.). Dua hal yang saya minta ditambal dulu sebelum
   eksekusi: langkah invalidasi sesi portal saat migrasi provider guard, dan estimasi hari per fase.

---

## 7. Langkah pasang di server (live)

1. `git pull` (repo sudah berisi 9 commit revisi ini).
2. `php artisan migrate --force` — 10 migrasi baru + 1 migrasi lama yang tadinya Pending.
   Dua di antaranya backfill data; aman dijalankan pada data existing, tidak menghapus apa pun.
3. `php artisan optimize:clear`.
4. **Tidak perlu** `npm run build` — tidak ada perubahan `resources/js` atau CSS.
5. Isi **Info Usaha → kolom bank** (nama bank, no. rekening, atas nama) supaya instruksi transfer
   muncul di faktur. Selama kosong, faktur menampilkan catatan "belum diisi".
6. Tinjau **Template Foto Laporan**: migrasi mengisi `field_set` = `indoor_lengkap` untuk slot Cuci AC
   yang berakhiran `_indoor`, dan `outdoor` untuk `_outdoor`. Konsekuensinya **teknisi wajib mengisi
   keterangan unit (posisi/suhu/kondisi) dan tidak bisa berangkat ke order berikutnya sebelum lengkap**.
   Kalau Anda mau lebih longgar, aturan itu bisa diubah tanpa migrasi.

---

## 8. Berkas bukti

- Brief & pertanggungjawaban agent: `_brief/01-…` s/d `_brief/09-…`, laporan tiap fase `_brief/laporan-01.md`…`laporan-09.md`.
- Log suite penuh: `_log/fullsuite-setelah-fase12.txt`, `…fase34.txt`, `…fase5.txt`, `…fase5b.txt`, `…fase6.txt`.
- PDF/PNG bukti visual: `_brief/scratch/sup06-hal1.png` (laporan), `sup09-invoice-saja-hal1.png`
  (faktur), `sup09-invoice-lampiran-hal{2,3}.png` (lampiran laporan di belakang faktur).
- Backup DB sebelum tiap gelombang: `_backup/database-pre-fase*.sqlite`.
