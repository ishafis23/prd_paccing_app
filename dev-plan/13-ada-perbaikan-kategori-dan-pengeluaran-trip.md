# Ada Perbaikan, Kategori Pekerjaan Multi-Item & Pengeluaran per Trip

> STATUS: **DISETUJUI (13 Sept 2026)** — hasil diskusi lanjutan atas
> pertanyaan terbuka §5 di
> [`12-analisis-chat-13sep-dan-roadmap.md`](12-analisis-chat-13sep-dan-roadmap.md).
> Menggantikan deskripsi §3.1/§3.2/§3.8 di dokumen itu dengan spesifikasi
> teknis final di bawah ini.

## 0. Masalah yang ditemukan

`Order::total()` sekarang = `serviceCatalog->harga * jumlah_unit` —
**satu order cuma bisa punya SATU jenis layanan dengan SATU harga**.
Tidak ada tempat untuk menambah biaya kalau di tengah pengerjaan ternyata
ada perbaikan/sparepart tambahan yang disepakati dengan customer. Ini
akar masalah kenapa alur "cuci AC → ternyata perlu ganti kapasitor →
admin deal harga dgn customer" belum bisa direpresentasikan.

## 1. Order jadi multi-item (baris layanan)

Order bisa punya **beberapa baris layanan** (`order_items`), bukan cuma
satu `service_catalog_id` tunggal:

- Tabel baru `order_items`: `order_id`, `service_catalog_id` (nullable —
  null kalau baris manual bukan dari katalog), `nama_layanan` (snapshot
  teks, terisi dari katalog atau diketik manual), `harga` (snapshot,
  **diisi manual oleh admin tiap baris** — hasil nego dgn customer,
  BUKAN dikunci ke harga katalog), `jumlah` (default 1), `kategori`
  (`ServiceType`, dipakai jg utk nentuin template foto §3), `catatan`,
  `ditambahkan_oleh` (user_id), `created_at`.
- Order tetap punya `service_catalog_id`+`jumlah_unit` LAMA (jangan
  dihapus — kompatibilitas data lama), tapi baris pertama `order_items`
  dibuat otomatis dari situ saat order dibuat (migrasi data existing:
  1 baris per order lama, dari `service_catalog_id`+`jumlah_unit`+harga
  katalog saat itu).
- `Order::total()` diubah jadi **jumlah semua `order_items.harga *
  jumlah`** (bukan lagi baca `serviceCatalog->harga` langsung).
- Harga tiap baris: **selalu diisi manual admin** (baik baris awal dari
  katalog — prefill dari harga katalog tapi tetap bisa diedit — maupun
  baris tambahan perbaikan). Tidak perlu maintain "katalog sparepart"
  terpisah; fleksibel utk harga hasil nego per kasus (keputusan 13 Sept).

## 2. Alur "Ada Perbaikan"

1. Order jalan normal (mis. kategori Cuci AC), status `dikerjakan`.
2. Teknisi cek unit, ada kebutuhan sparepart/perbaikan → teknisi bicara
   ke customer dulu, lalu **lapor ke admin** lewat tombol baru **"Ada
   Perbaikan"** di portal teknisi (mirip pola tombol "Terkendala/Gagal"
   §3.9 — form catatan: apa yg perlu diganti + estimasi harga dari
   teknisi kalau ada). Order **tetap** `dikerjakan` (beda dgn Terkendala
   yg menghentikan alur) — cuma menambahkan flag "menunggu konfirmasi
   perbaikan" + catatan, supaya admin tahu tanpa mengganggu progres
   teknisi di lokasi.
3. Admin lihat notice ini (muncul di Orderan Harian §3.3 & detail
   order), telepon konfirmasi ke customer.
4. Customer setuju → **admin tambah baris `order_items` baru** ke order
   itu (nama layanan + harga hasil deal + kategori). Total tagihan
   order otomatis bertambah. Flag "menunggu konfirmasi" hilang.
   Customer tidak setuju → admin tandai ditolak (flag hilang, tanpa
   baris baru), teknisi lanjut kerja sesuai order awal saja.
5. Teknisi lanjut kerja; saat submit laporan, pilih sparepart yg
   dipakai lewat `WorkReportMaterial` yg **sudah ada** (stok otomatis
   berkurang lewat `StockService::keluar()`). Ini soal biaya modal/stok
   internal — **terpisah** dari harga jual ke customer di langkah 4.
   Tidak perlu tertaut otomatis satu sama lain (admin & teknisi bisa
   independen: admin set harga jual berdasar nego, teknisi catat
   pemakaian stok riil).

## 3. Foto laporan per kategori

Tiap `order_items` (bukan tiap order) punya kategori (`ServiceType`) dan
slot foto sendiri. Tabel baru `work_report_photos`: `work_report_id`,
`order_item_id`, `slot` (string key), `path`, `urutan`.

`ServiceType` diperluas dari `CuciAc, ServiceAc, PengadaanAc` jadi
tambah: `TambahFreon, Instalasi, Relokasi, Bongkar` (sesuai daftar chat:
"cuci ac, tambah freon, service ac, instalasi ac, relokasi AC, bongkar").

Template slot foto per kategori (urutan final utk Cuci AC dari
klarifikasi teknisi lapangan 13 Sept; kategori lain usulan saya —
2-3 foto simpel, disetujui 13 Sept, **detail nama slot final menyusul
saat implementasi, boleh disesuaikan tanpa perlu diskusi ulang**):

| Kategori | Slot foto (urutan) |
|---|---|
| Cuci AC | outdoor_proses → indoor_proses → indoor_sebelum → indoor_sesudah_suhu |
| Tambah Freon | tekanan_sebelum → tekanan_sesudah |
| Service AC | kondisi_sebelum → proses_service → kondisi_sesudah |
| Ganti Sparepart | part_lama → part_baru_terpasang |
| Instalasi | lokasi_sebelum → unit_terpasang → testing_suhu |
| Relokasi | lokasi_asal → lokasi_baru → unit_terpasang |
| Bongkar | sebelum_bongkar → sesudah_bongkar |

`WorkReport.foto_sebelum`/`foto_sesudah` (kolom lama) **tetap
dipertahankan** utk data lama; laporan baru pakai `work_report_photos`.

## 4. Pengeluaran per trip/order

`expenses` dapat kolom `order_id` (nullable, FK). Form Expense yang
**sudah ada** (`ExpenseResource`) tetap dipakai admin utk SEMUA
pengeluaran — tambah 1 field opsional "Order Terkait" (Select, kosong =
pengeluaran umum spt sewa/alat kantor; diisi = terkait trip/order
tertentu, mis. uang jalan Rp50rb/tim atau top-up beli minuman
tambahan). Nominal **selalu manual/bisa diedit** (bukan dikunci
Rp50rb — beda kasus beda nominal). Admin tetap satu-satunya yang input
(konsisten dgn kebijakan sekarang: teknisi tidak boleh mencatat
pengeluaran), sesuai cerita client bahwa admin yg mentransfer/membayar
tiap top-up.

Manfaat: margin per order bisa dihitung (`total() - biaya operasional
terkait - modal material`) — nilai tambah yg tidak ada sebelumnya.

## 5. Urutan implementasi (dari risiko kecil ke besar)

1. ~~**Pengeluaran per order** (`expenses.order_id`)~~ — **✅ selesai**:
   field "Order Terkait" (opsional) di form Expense, `FinanceService::
   createExpense()` terima `orderId`, `Order::expenses()` relasi baru.
   9 test (`tests/Feature/FinanceTest.php`).
2. **`order_items` + refactor `Order::total()`** — struktural, effort
   besar, sentuh banyak tempat (PaymentService, resi, OrderResource,
   portal teknisi).
3. **Tombol "Ada Perbaikan" + flag konfirmasi** (teknisi + admin) —
   bergantung pada #2 selesai duluan (butuh `order_items` sbg tempat
   nampung baris baru).
4. **Foto per kategori** (`work_report_photos`, `ServiceType` diperluas)
   — bisa paralel dgn #2/#3, tapi baru penuh berguna setelah order bisa
   multi-kategori.
