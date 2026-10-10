# Usulan — Omset Jasa/Material, Keterangan Foto per Unit, Laporan PDF, Invoice, & Fix "1 Unit"

> STATUS: **✅ DISETUJUI — siap dieksekusi per fase** (keputusan di §9). Sumber revisi: `dev-plan/revisi/10oktober2026/` (chat
> klien + screenshot + `INV110037.pdf` + `(SEPT) Maintenance AC Circle K (CK)_compressed.pdf`).

## 1. Ringkasan 6 revisi

| # | Revisi klien | Kondisi app sekarang | Prioritas |
|---|---|---|---|
| A | Order 3 unit tertulis "1 unit" di app teknisi | **Bug** — `orders.jumlah_unit` tidak pernah diisi benar | P0 (kecil, bisa langsung) |
| B | Pisah omset jasa vs material per order | Hanya klasifikasi per **order utuh**, tidak bisa dipecah | P1 |
| C | Dashboard awal: omset per kategori (tidak digabung) + tabel jasa/material, filter tanggal/bulan | Widget cuma total; ada `klasifikasiPengerjaan` tapi hanya di Dashboard Pimpinan & tanpa jasa/material | P1 (butuh B) |
| D | Keterangan foto indoor/outdoor (CK mana, unit ke-, posisi, jenis cuci, suhu, kondisi) + menu "Lengkapi Laporan" | Tidak ada; foto cuma per slot, tanpa metadata | P2 |
| E | Preview laporan seperti PDF Circle K | Tidak ada | P2 (butuh D) |
| F | Invoice per order (`INV110037.pdf`) + lampiran laporan foto | Tidak ada entitas invoice/PDF; baru Resi publik & Surat Jalan | P3 (butuh E) |

Urutan eksekusi usulan: **A → B → C → D → E → F**, tiap fase di-commit & bisa
dirilis sendiri.

---

## 2. Revisi A — Fix "selalu 1 unit" (P0)

### Akar masalah (terverifikasi di kode)
- Tampilan teknisi membaca `$order->jumlah_unit` dan `$order->serviceCatalog`
  (kolom di tabel `orders`, mewakili **baris pertama saja**):
  - `resources/views/livewire/teknisi/order-detail.blade.php:120-122`
  - `resources/views/livewire/teknisi/riwayat-pengerjaan.blade.php:41`
  - `resources/views/resi.blade.php:90`
  - (cek juga `jadwal-hari-ini.blade.php` & kolom tabel `OrderResource`)
- Order dari wizard multi-alamat / "Order dari unit" **hard-code
  `'jumlah_unit' => 1`** (`OrderService.php:140, 328`); jumlah sebenarnya
  tersimpan di `order_items` (1 baris per unit, atau 1 baris dengan `jumlah`=N).
- Makanya **tagihan benar** (Rp180.000 = `Order::total()` dari `order_items`)
  tetapi label unit salah. Persis kasus klien: 60rb × 3 → "1 unit".
- Teks "Cuci standar dua unit" yang tampil di kartu hijau itu hanya
  `catatan_admin` ketikan admin — bukan data.

### Perbaikan
1. `Order::jumlahUnit()` = jumlah `order_items.jumlah` yang tidak dibatalkan
   (fallback ke kolom lama bila item kosong).
2. `Order::ringkasanLayanan()` → kelompok per kategori, mis.
   `"Cuci AC · 3 unit"` atau `"Cuci AC · 2 unit, Ganti Kapasitor · 1"` (supaya
   order campuran tidak hanya menampilkan layanan pertama).
3. Ganti 4 titik tampilan di atas ke helper itu.
4. `OrderService`: isi `orders.jumlah_unit` = jumlah sebenarnya (kolom tetap
   ada untuk kompatibilitas), + migrasi **backfill** `orders.jumlah_unit` dari
   `order_items` untuk data lama.
5. Test: order 3 item → "3 unit"; 1 item `jumlah`=3 → "3 unit"; 1 item
   dibatalkan dari 3 → "2 unit".

Estimasi: ±½ hari. Tidak ada perubahan skema selain backfill data.

---

## 3. Revisi B — Pemisahan Omset Jasa & Material (REVISI v2 — mengikuti jawaban review)

### Temuan penting: tidak perlu admin "memecah" total
Alur sekarang sudah memisahkan secara alami: order awal "Cuci AC", lalu saat
berjalan teknisi/admin **menambah layanan sebagai baris `order_items` baru**
(`OrderDetail::tambahLayanan()` → `OrderService::tambahLayananOlehTeknisi()`,
isian: nama, kategori, harga, jumlah). Jadi contoh klien (ganti kapasitor
Rp350rb) cukup dicatat sebagai **dua baris**: "Jasa ganti kapasitor 150rb" +
"Kapasitor 200rb". Karena itu desain disederhanakan:

- `order_items` + kolom **`komponen`** enum `jasa|material` (bukan dua kolom
  nominal; tidak ada invarian jumlah yang harus dijaga).
- Omset jasa = Σ subtotal baris `jasa`; omset material = Σ subtotal baris `material`.
- **Cuci AC → otomatis `jasa`** (setelan `service_catalogs.mode_omset`,
  Cuci = `otomatis_jasa`, bisa diubah owner di Katalog Layanan).
- Baris tambahan: form "Tambah Layanan" (teknisi & admin) dapat pilihan
  **Jasa / Material**; default = **Material** bila kategori Pengadaan/Sparepart
  atau nama dipilih sebagai barang, **Jasa** untuk Cuci/Service/Instalasi/dll.
  (pilihan tetap bisa diganti). Sesuai keputusan: sparepart default material.
- Tidak ada lagi bucket "Belum dipisah" — setiap baris selalu punya komponen
  (nilai default terisi otomatis), jadi dashboard selalu lengkap.
- Admin bisa mengubah komponen baris di tabel item Order (toggle kecil).

### Migrasi data lama
Backfill `komponen` per baris dari aturan lama: kategori Cuci/Service = jasa;
Pengadaan/lainnya = material. Total historis tidak berubah.

### Koreksi total (temuan + usulan — mohon konfirmasi §9)
Kode sekarang: `OrderService::koreksiTotal()` hanya menyimpan baris
`order_total_corrections`; **`Order::total()` tidak memakainya** (notifikasi
Filament menampilkan "Total lama → baru", tetapi tagihan/omset tetap angka lama).
Usulan: total & omset mengikuti total terkoreksi (sesuai yang tampil di notifikasi):
selisih (+/−) dicatat sebagai **baris penyesuaian** `komponen=jasa` (default) pada
order tsb sehingga `Σ item = total terkoreksi`, dan semua laporan (akuntan,
dashboard, invoice) otomatis konsisten. Baris penyesuaian bisa dipindah ke
material oleh admin.

### Perubahan perhitungan
`FinanceService::labaRugi()` & `AkuntanService::pendapatan()` menjumlah per
`komponen` baris (bukan mapping order utuh via `IncomeCategory::untukLayanan`).

---

## 4. Revisi C — Dashboard: omset per kategori + tabel jasa/material

Gambar klien (WhatsApp 12.35.06): tabel **Pekerjaan | Omset Jasa | Omset
Material | Total** + baris Total, dengan filter tanggal / bulan.

### Di dashboard awal Admin (`Pages\Dashboard` + widget)
1. **Kartu per kategori — tidak digabung:** Cuci AC · Service AC · Pasang/Pengadaan
   · (Tambah Freon, Instalasi, Relokasi, Bongkar bila ada transaksi). Tiap kartu:
   unit, omset total, jasa, material. (Menggantikan stat "Jasa + Material"
   gabungan di `RingkasanFinanceWidget`.)
2. **Tabel Omset** (widget baru `OmsetJasaMaterialWidget`): baris = pekerjaan
   (per kategori; opsi "rinci per transaksi" seperti contoh klien: Ganti kapasitor,
   Pasang AC), kolom jasa/material/total & baris total.
3. **Filter:** toggle `Bulan` (picker bulan) ⇄ `Rentang tanggal` (dari–sampai) +
   preset cepat (Hari ini, Pekan ini, Bulan ini, Bulan lalu). Filter berlaku
   untuk kartu + tabel + (ikut) stat finance di atasnya.
4. Ekspor tabel ke CSV/Excel (opsional, mudah ditambah).

### Di Dashboard Pimpinan
Sub-tab "Klasifikasi rinci" (`klasifikasiPengerjaan()`) ditambah kolom jasa &
material memakai service yang sama.

### Catatan konsistensi tanggal
- `AkuntanService::pendapatan()` memakai **tanggal bayar lunas** (fallback
  `updated_at`), sedangkan `klasifikasiPengerjaan()` memakai `orders.updated_at`.
  Usul: satukan ke satu fungsi "tanggal pendapatan" agar angka dashboard,
  akuntan, dan pimpinan **selalu sama**. Satu `OmsetService` baru jadi sumber
  tunggal (dipakai widget, Akuntan, Pimpinan).

---

## 5. Revisi D — Keterangan Foto per Unit + menu "Lengkapi Laporan"

### Kebutuhan klien (indoor)
CK mana · Unit ke berapa · Posisi dimana · Jenis cuci apa · Suhu berapa ·
Kondisi (normal / tidak normal + catatan). Outdoor: disesuaikan. Dari PDF Circle K
tampak juga **RPM** (anemometer, mis. "RPM : 7,3") — **sudah diputuskan, §9-5**.

Contoh caption target (dari PDF): `CK DADI · UNIT SATU · DI ATAS TOILET · CUCI STANDAR · RPM 7,3 · SUHU 17,5°C`.

### Kondisi sekarang
- Foto laporan: `work_report_photos` (per `order_item_id` + `slot`) dengan
  slot dari `photo_report_templates` (dev-plan/17). **Tanpa metadata**, dan
  **tanpa nomor unit** → bila 1 item `jumlah`=3, 3 unit berbagi slot yang sama.
- Tabel lama `order_photos` (`unit_number`, `photo_position`) dan
  `PhotoLayananStructure` masih ada (alur lama, `PhotoController`). Perlu
  diputuskan: tetap `work_report_photos` (§9-6).
- `CustomerAcUnit` sudah punya `kode_unit`, `kode_ruangan`, `catatan`,
  `customer_address.nama_lokasi` → "CK mana" & "posisi" bisa **di-prefill**.

### Desain data
Tabel baru `order_unit_reports` (1 baris = 1 unit yang dikerjakan di 1 order):

| kolom | isi | asal/prefill |
|---|---|---|
| `order_id`, `order_item_id`, `unit_no` | unit ke-berapa | otomatis urutan item/jumlah |
| `customer_ac_unit_id` (null) | unit terdaftar | dari item |
| `lokasi_label` | "CK mana" | `customer_addresses.nama_lokasi` (readonly) |
| `posisi` | "di atas toilet" | `kode_ruangan` bila ada, teknisi bisa ubah |
| `jenis_pekerjaan` | "Cuci Standar" | nama layanan item (readonly) |
| `suhu` (decimal), `rpm` (decimal null) | angka | input teknisi |
| `kondisi` enum `normal/tidak_normal`, `catatan_kondisi` | | wajib catatan bila tidak normal |
| `bagian` enum `indoor/outdoor` | | outdoor: field suhu/rpm disembunyikan |

`work_report_photos` + kolom `unit_no` (default 1) + FK opsional ke
`order_unit_reports`, supaya foto menempel ke unit tertentu.

Konfigurasi field per slot lewat `photo_report_templates` (kolom baru
`field_set`: `indoor_lengkap`, `outdoor`, `bebas`) → admin yang menentukan slot
mana butuh keterangan apa; Service AC/pemasangan bisa memakai caption bebas
("Proses vakum", "Setelah ditambah freon") seperti halaman 2 PDF.

### UI Teknisi
- Order Detail → tab Foto: per **unit** (accordion "Unit 1 / Unit 2 / Unit 3"),
  tiap unit: foto + form keterangan ringkas (field prefill, teknisi hanya isi
  posisi/suhu/kondisi). Tombol "Salin posisi dari unit sebelumnya" untuk
  mempercepat.
- Menu baru **"Lengkapi Laporan"** (bottom nav/halaman terpisah): daftar order
  yang foto/keterangannya belum lengkap (menyatu dengan rencana dev-plan/18
  "info foto kurang"), tap → langsung ke unit yang kurang.
- Foto wajib & keterangan wajib dihitung bersama (`TeknisiService::fotoWajibKurang()`
  diperluas ke "keterangan kurang").

### UI Admin
Di Order → tab/section **Laporan Pengerjaan**: lihat & **edit/lengkapi**
keterangan tiap unit (untuk melengkapi yang teknisi lupa), upload foto susulan.

---

## 6. Revisi E — Preview & PDF Laporan (format Circle K)

### Format target (dari PDF 18 halaman)
- Kop biru: logo PACCING + judul "LAPORAN MAINTENANCE AC {CUSTOMER}".
- Blok info: **Cabang**, **Hari/Tanggal**, **Keterangan** (mis. "Cuci Standar 3 Unit",
  "Pasang Unit Indoor & Isi Freon").
- Grid foto 2 kolom dengan caption keterangan unit (§5); foto outdoor/area tanpa
  caption unit.
- Footer biru "PT. PACCING JAYA SEJAHTERA". **1 halaman (atau lanjut) per kunjungan.**
- Variasi: judul/warna tergantung customer (CK); template dasar tetap, judul
  memakai nama customer.

### Fitur
1. **Preview** (admin & teknisi): halaman Blade `laporan.preview` — tampil HTML
   mirip PDF di dalam modal/halaman; bisa dibuka dari Order (tombol
   "Preview Laporan") & dari "Lengkapi Laporan" teknisi.
2. **Unduh PDF per order**.
3. **Laporan Bulanan per Customer** (padanan "(SEPT) Maintenance AC Circle K"):
   admin pilih customer + bulan (+ opsional cabang) → satu PDF berisi semua
   kunjungan terurut tanggal. Jalankan sebagai queued job (18 halaman + foto
   besar) dan simpan file ke storage dengan tautan unduh.
4. Kompres foto saat render (PDF contoh "_compressed" ±1,4 MB) — pakai versi
   resize (maks ±1000px) agar PDF tidak ratusan MB.

### Teknis
- Belum ada library PDF di `composer.json`. Usul **`barryvdh/laravel-dompdf`**
  (pure PHP, mudah di shared hosting/Windows) — cukup untuk layout ini. Alternatif
  Browsershot/Chromium (hasil lebih presisi tapi butuh Node+Chrome di server)
  → **§9-7**.
- Service baru `LaporanPengerjaanService` (menyusun data: order → unit → foto →
  caption) dipakai preview & PDF supaya konsisten.
- Foto dibaca dari disk storage (`FilePenyimpananService`), hormati kuota
  penyimpanan; tidak ada foto → placeholder "foto belum diunggah".

---

## 7. Revisi F — Invoice per Order + Lampiran

### Format target (`INV110037.pdf`, gaya Zoho)
Kop PT. Paccing Jaya Sejahtera + alamat + telp → "FAKTUR" → nama customer,
**No. Invoice**, tanggal, jatuh tempo → tabel **# | Item | Deskripsi (tanggal +
lokasi) | Jml | Tarif | Jumlah** → Sub Total / Total / **Saldo Jatuh Tempo** →
catatan "Terima kasih…" + instruksi transfer (BCA, a.n.). Satu invoice meliputi
**beberapa kunjungan/tanggal** (26, 27, 30 Juni, 3 Okt) untuk satu customer.

### Desain
- Tabel `invoices`: `nomor` (unik, format konfigurabel mis. `INV{seq6}`),
  `customer_id`, `tanggal`, `jatuh_tempo`, `status` (draft/terkirim/lunas/batal),
  `catatan`, `subtotal`, `total`, snapshot info bank. + pivot
  `invoice_orders` (satu invoice ↔ banyak order). Baris invoice diturunkan dari
  `order_items` (nama layanan, jumlah, harga, **deskripsi otomatis** =
  tanggal jadwal + nama lokasi) dan bisa diedit admin sebelum final.
- Fase awal: **invoice dari 1 order** (tombol "Buat Invoice" di Order); fase
  lanjut: pilih beberapa order selesai milik customer yang sama → satu invoice.
- Info usaha & rekening: tambah field bank (nama bank, no rek, atas nama) di
  `BusinessInfo` (menu Info Usaha yang sudah ada, dev-plan/09); logo dari sana.
- PDF Invoice + **Lampiran**: halaman-halaman Laporan Pengerjaan (§6) milik
  order-order di invoice tsb digabung dalam satu file (invoice di depan, laporan
  foto di belakang). Opsi centang "Sertakan lampiran laporan".
- Keterkaitan pembayaran: status invoice mengikuti `payments` order (lunas bila
  semua order lunas). Resi publik yang ada tidak diubah.
- Opsi kirim: tautan publik bertoken (pola `resi_token`) + tombol "Kirim via WA"
  (teks + tautan), tanpa integrasi pihak ketiga.

Catatan: invoice contoh tidak ada rincian jasa/material; tidak perlu tampil di
invoice kecuali diminta (§9-8).

---

## 8. Rencana Fase, Berkas Terdampak, & Test

| Fase | Isi | Berkas utama | Migrasi |
|---|---|---|---|
| 1 | A: fix jumlah unit | `Order.php`, 4 blade, `OrderService.php` | backfill `jumlah_unit` |
| 2 | B: omset jasa/material | `OrderItem`, `ServiceCatalog(Resource)`, `OrderResource` (modal rincian), `FinanceService`, `AkuntanService`, `IncomeCategory` | kolom di `order_items` & `service_catalogs` + backfill |
| 3 | C: dashboard | `OmsetService` baru, `RingkasanFinanceWidget`, widget baru, `DashboardPimpinanService`, view pimpinan | — |
| 4 | D: keterangan unit + Lengkapi Laporan | `order_unit_reports`, `work_report_photos.unit_no`, `OrderDetail` (+blade), `TeknisiService`, `PhotoReportTemplate*`, halaman Lengkapi Laporan, bottom-nav | 2 migrasi |
| 5 | E: preview + PDF + bulanan | `LaporanPengerjaanService`, view PDF, aksi Order, halaman Laporan Bulanan, job | composer dompdf |
| 6 | F: invoice | `invoices`, `invoice_orders`, `InvoiceService`, `InvoiceResource`, view PDF, `BusinessInfo` bank | 2–3 migrasi |

Test (Pest/PHPUnit, mengikuti pola `tests/` yang ada): jumlah unit; invarian
jasa+material = subtotal; backfill tidak mengubah total historis; angka
dashboard = akuntan = pimpinan untuk periode sama; validasi "kondisi tidak
normal wajib catatan"; PDF ter-generate untuk order tanpa foto; nomor invoice unik.

Risiko: ukuran PDF/memori (foto banyak) → resize + queue; data lama tanpa
metadata unit → laporan tetap tampil dengan caption minimal; dua jalur foto
(`order_photos` vs `work_report_photos`) harus disatukan agar PDF tidak ganda.

---

## 9. Keputusan hasil review (10 Okt 2026)

| # | Keputusan |
|---|---|
| 1 | Tidak ada bucket "Belum dipisah"; split lewat baris layanan tambahan dengan komponen jasa/material (§3) |
| 2 | Sparepart/tambahan: default material |
| 3 | Total mengikuti koreksi seperti notifikasi → **DISETUJUI**: baris penyesuaian berkomponen jasa (§3), admin bisa pindah ke material |
| 4 | Tabel dashboard default per kategori + tombol "rinci per transaksi" |
| 5 | RPM ikut dicatat. Keterangan per foto disimpan lewat migrasi baru (`order_unit_reports`) dengan **form input seragam per unit** yang tinggal diisi teknisi; field set per slot (indoor lengkap / outdoor / bebas) |
| 6 | Tetap pakai jalur foto yang sudah jalan (`work_report_photos` + OrderDetail); `order_photos` lama tidak disentuh; fitur keterangan ditambahkan di atasnya |
| 7 | dompdf dulu |
| 8 | Nomor baru: usul **`INV-YYYYMM-0001`** (urut per bulan, di-reset tiap bulan, dibuat dalam transaksi + unique index). Timestamp murni (mis. `INV-20261010173412`) tidak dipakai karena panjang, tidak berurutan rapi, dan bisa bentrok bila 2 invoice dibuat di detik sama. **DISETUJUI** format ini. Invoice multi-order: fase lanjutan |
| 9 | Template laporan standar (logo dari Info Usaha, judul dari nama customer) |
| 10 | Pengatur omset/invoice: Owner, Admin, Finance — **teknisi tidak** (teknisi hanya isi keterangan foto & tambah layanan) |

Semua keputusan sudah final. STATUS: **siap dieksekusi per fase (1→6).**
