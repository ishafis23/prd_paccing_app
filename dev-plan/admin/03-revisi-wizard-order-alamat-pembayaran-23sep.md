# Revisi Portal Admin — Wizard Buat Order, Sinkron Alamat, & Catat Pembayaran

> STATUS: **✅ DIEKSEKUSI (23 Sep 2026).** Semua item §4 selesai
> diimplementasikan & lulus test (705 test, termasuk ~25 test baru),
> Pint bersih di file yang disentuh. Menunggu instruksi commit/push dari
> user (belum di-commit). Diturunkan dari `dev-plan/teknisi/Portal
> Admin.pdf` (revisi client, dibaca 23 Sep 2026) — 5 poin keluhan/usulan,
> semuanya di area **wizard Buat Order** (`CreateOrder.php`, dev-plan/18)
> dan **Catat Pembayaran** (`PaymentService`). Dicatat juga di
> `dev-plan/02-keputusan-eksekusi.md`.

## 0. Ringkasan Akar Masalah (temuan lintas-poin)

Poin 2, 4, dan sebagian poin 5 di PDF **satu akar masalah yang sama**:
sistem punya **dua tempat berbeda** untuk menyimpan "alamat customer" yang
tidak saling sinkron:

1. `customers.alamat` — field teks tunggal ("Alamat Utama") di form
   Edit Customer (`CustomerResource.php:50-52`), simpel, yang **selalu
   diisi admin** (termasuk ~4000 baris import Excel lama, lihat §1.2).
2. `customer_addresses` (dev-plan/14, tabel terpisah) — "sumber kebenaran
   alamat" sebenarnya untuk order (`Customer::addresses()`,
   `Customer.php:84-87`), dikelola lewat tab **"Alamat"** terpisah di
   halaman edit customer, dan **inilah** yang dipakai dropdown "Pilih
   Alamat" di wizard Buat Order (`CreateOrder.php:178-181`).

Kalau admin cuma isi "Alamat Utama" (kasus paling umum — hampir semua
customer lama) dan **tidak pernah buka tab "Alamat"**, maka
`customer_addresses` customer itu **kosong** — dropdown "Pilih Alamat" di
wizard **memang benar kosong**, bukan bug path-resolution (itu sudah
diperbaiki commit `4a8b026`, 21 Sep). Order tetap bisa dibuat (fallback ke
`$customer->alamat` di `OrderService::resolveAlamat()` /
`OrderService.php:577-589` dan `alamat_pengerjaan` di
`OrderService.php:310`), **tapi admin tidak tahu itu** — dropdown kosong
terlihat seperti error, sehingga (dugaan kami dari alur screenshot PDF
poin 2) admin defensif mengisi **baik "Assign Teknisi" maupun "Atau Assign
Tim"** sekaligus → kena validasi `OrderService.php:241-243` → "Proses
Order" gagal.

Jadi perbaikan intinya: **satukan dua sumber alamat ini** (§1.2/B75-B76),
baru sederhanakan tampilan wizard menyusul (§1.4/B79, §1.5/B80).

## 1. Per-Poin PDF

### 1.1 Poin 1 — Label "Jenis Pelanggan": Rumahan → Cust Umum

Ganti teks tampilan saja (nilai enum `CustomerJenis::Perorangan` =
`'perorangan'` **tidak berubah**, cuma bukan bug/logic — murni copy).
4 titik yang memuat label "Rumahan" utk field **order** (`jenis_pelanggan`,
beda dari field **customer** `jenis` yg sudah menampilkan "Perorangan" via
`EnumOptions::for()`, tidak disentuh):

- `app/Filament/Resources/OrderResource/Pages/CreateOrder.php:93`
- `app/Filament/Resources/OrderResource.php:194` (form edit/table)
- `app/Filament/Resources/OrderResource.php:230` (view/detail)
- `resources/views/filament/pages/orderan-harian.blade.php:57`

Tidak ada B-decision — tinggal ganti string `'Rumahan'` →
`'Cust Umum'` di keempat titik itu.

### 1.2 Poin 2 — "Pilih Alamat" kosong & bikin "Proses Order" error

Root cause dijelaskan di §0. Yang perlu diputuskan: **bagaimana
menyatukan** `customers.alamat` dengan `customer_addresses`.

### 1.3 Poin 3 — "Catat Pembayaran" tidak bisa disesuaikan (ongkir/diskon)

`PaymentService::recordPayment()` (`PaymentService.php:34-97`) mengunci
`$total = $order->total()` (jumlah `order_items`, `Order.php:229-240`) —
**tidak bisa diubah**. Guard `$jumlahDibayar > $sisa` (`PaymentService.php:
57-58`) selalu membandingkan ke angka katalog ini, jadi:
- Biaya tambahan tak terduga (ongkir, kenaikan harga material) **tidak
  bisa dicatat** — jumlah bayar > sisa tagihan katalog selalu ditolak.
- Diskon **tidak bisa dicatat** sebagai "lunas dgn total lebih rendah" —
  membayar kurang dari `total()` cuma menghasilkan status `Dp`, order
  tidak pernah `Selesai` walau itu memang harga final yg disepakati.

### 1.4 Poin 4 — Ganti nama step "Alamat & Layanan" jadi "Layanan"

Alasan client eksplisit: "karena alamat cust sudah ada/terisi di Data
Pelanggan" — cocok dengan akar masalah §0: begitu §1.2 (sinkron alamat)
beres, alamat customer **selalu** otomatis tersedia tanpa admin perlu
pilih apa-apa di step ini, jadi visualnya wajar disederhanakan.

### 1.5 Poin 5 — Assign Teknisi/Tim diganti "Pilih Tim" (PIC + Pendamping)

Field `teknisi_id` ("Assign Teknisi") & `team_id` ("Atau Assign Tim")
saat ini kompetitif — kalau **keduanya** diisi, `OrderService.php:241-243`
melempar `BusinessRuleException` dan seluruh wizard gagal submit (semua
alamat, bukan cuma baris yg salah — karena `createOrders()` satu
transaksi all-or-nothing). Client minta diganti **satu** konsep "Pilih
Tim" ad-hoc per order (bukan dari master data `Team`/`TeamResource`,
dev-plan/12 §3.13, yang tetap dipakai di tempat lain — lihat §5):
- Dropdown 1: **Teknisi/PIC** (wajib kalau mau assign apa pun)
- Dropdown 2: **Pendamping** (opsional) — "biasanya ada teknisi yang
  jalan sendiri karena yg lain sakit/tdk masuk"

## 2. Keputusan yang Perlu Disetujui

### B75 — Cara menyatukan `customers.alamat` dengan `customer_addresses`
- **(a) Auto-sync sekali via model hook**: tambah `Customer::booted():
  saved` — kalau `alamat` terisi dan `$customer->addresses()->count() ===
  0`, otomatis `CustomerAddress::create(['alamat' => $customer->alamat,
  'is_utama' => true, ...])`. Sinkron SATU ARAH & SEKALI SAJA (begitu
  sudah ada ≥1 baris `customer_addresses`, hook tidak menimpa lagi —
  perubahan alamat selanjutnya lewat tab "Alamat" sesuai desain
  dev-plan/14 yang sudah ada). Menangkap semua jalur yang MEMICU Eloquent
  event: `CreateCustomer`/`EditCustomer` (Filament, CRUD manual admin).
  **Tidak menangkap** `CustomerImportService::import()` — lihat B76.
- (b) Jadikan `customer_addresses` SATU-SATUNYA sumber (hapus field
  `customers.alamat`, wajib isi tab "Alamat" saat create customer) —
  breaking change besar ke form Create/Edit Customer & migrasi data lama,
  jauh lebih invasif utk menyelesaikan masalah UX yang sebenarnya kecil.
- (c) Biarkan dua sumber terpisah, cuma perbaiki UX dropdown (placeholder
  "— pakai Alamat Utama customer —" dijadikan opsi eksplisit yg bisa
  dipilih, bukan navigasi tersembunyi lewat "dikosongkan") — tidak
  menyelesaikan poin 4 (client tetap harus paham dua sistem alamat
  berbeda), cuma tempelan UX.

Rekomendasi: **(a)** — minim perubahan, konsisten dengan pola
`CustomerAddress::booted()` yang sudah ada (`CustomerAddress.php:79-91`,
auto-`is_utama` utk alamat pertama), tidak mengubah desain dev-plan/14.

### B76 — Backfill utk customer yang sudah ada (termasuk ~4000 hasil
Import Excel, dev-plan/admin §"Sudah selesai")
`CustomerImportService::import()` pakai `Customer::query()->insert($chunk)`
bulk insert (`CustomerImportService.php:265-266`) — **melewati Eloquent
event sepenuhnya**, jadi hook B75(a) tidak akan jalan utk baris hasil
import (baik yang sudah ada sekarang, maupun import baru nanti).
- **(a) Service kecil `CustomerAddressSyncService::backfillMissing()`**
  (query semua `Customer` dgn `alamat` terisi & `addresses()->count() ===
  0`, batch-create `CustomerAddress`) dipanggil dari **dua** tempat: (1)
  Artisan command sekali-jalan `php artisan customers:sync-alamat-utama`
  utk membereskan ~4000 data lama begitu fitur ini di-deploy, (2) ujung
  `CustomerImportService::import()` supaya import Excel berikutnya juga
  otomatis tersinkron (tidak perlu ubah cara `import()` insert baris,
  cukup tambah 1 panggilan setelah loop selesai).
- (b) Ubah `CustomerImportService::import()` dari bulk `insert()` ke
  `Customer::create()` per baris supaya event Eloquent terpicu natural —
  lebih "rapi" tapi `insert()` bulk sengaja dipakai utk performa impor
  4000 baris (lihat komentar `UKURAN_CHUNK_INSERT`), berisiko regresi
  performa impor besar demi hal yang bisa diselesaikan lebih murah lewat
  (a).

Rekomendasi: **(a)** — satu service method dipakai ulang, tidak mengubah
performa impor sama sekali.

### B77 — "Catat Pembayaran": total tagihan bisa disesuaikan — **✅ DIPUTUSKAN 23 Sep: opsi (a)**
- **(a) Tambah field opsional "Total Tagihan (sesuaikan bila perlu)"**
  di form aksi `catatPembayaran` (`OrderResource.php:735-747`), pre-filled
  `$order->total()`, admin bisa naikkan (ongkir/material tambahan) atau
  turunkan (diskon). `PaymentService::recordPayment()` dapat param baru
  `?float $totalTagihanOverride = null` — kalau diisi dipakai sbg `$total`
  (bukan `$order->total()`), tersimpan ke kolom `payments.total_tagihan`
  yang **sudah ada** (kolom ini sekarang cuma duplikat `order->total()`,
  jadi berubah makna jadi "total final yang disepakati utk pembayaran
  ini" — tidak perlu migrasi kolom baru). Guard overpay
  (`PaymentService.php:57-58`) TETAP ADA tapi membandingkan ke total yang
  (mungkin) sudah disesuaikan — jadi bukan dihapus, cuma jadi bisa
  digeser dulu oleh admin. **Perbaikan bug tersembunyi sekalian**: kalau
  order sudah py Payment (DP) dgn total yg sebelumnya disesuaikan,
  panggilan berikutnya HARUS pakai `$payment->total_tagihan` yang
  tersimpan sbg default (bukan balik ke `$order->total()` katalog) —
  kalau tidak, penyesuaian di pembayaran DP pertama hilang saat pelunasan
  kedua. `catatIncome()` (`PaymentService.php:120-138`) sudah otomatis
  pakai `payment->total_tagihan` jadi income ikut benar tanpa perubahan.
- (b) Hapus saja guard overpay, biarkan `jumlah_dibayar` bebas berapa pun
  — lebih simpel, tapi hilang proteksi typo (misal kelebihan nol), dan
  `catatIncome()` tetap pakai `total_tagihan` (katalog) shg kelebihan
  bayar riil (ongkir dsb.) tidak pernah tercatat sbg income — data
  finance jadi tidak akurat.

Rekomendasi: **(a)** — satu-satunya opsi yg bikin `payments.total_tagihan`
& `incomes` tetap mencerminkan nilai riil yang disepakati, bukan cuma
"boleh lebih bayar tanpa penjelasan".

### B78 — Wajib catatan alasan penyesuaian? — **✅ DIPUTUSKAN 23 Sep: opsi (a), wajib**
- **(a) Ya, wajib kalau total disesuaikan** — tambah kolom nullable
  `payments.catatan` (migrasi baru, pola sama dgn `catatan_admin` di
  tabel lain), field textarea "Alasan Penyesuaian" muncul & **wajib**
  di form cuma kalau admin mengubah "Total Tagihan" dari default
  `order->total()`. Membantu Finance/Owner audit kenapa suatu order
  nominalnya beda dari katalog saat rekonsiliasi nanti.
- (b) Tidak perlu catatan — lebih cepat diisi admin di lapangan, tapi
  tidak ada jejak kenapa suatu order "lunas" dgn nominal beda dari
  katalog (Finance harus tanya manual kalau curiga).

Rekomendasi: **(a)** — kolom baru murah, dan riwayat alasan penting begitu
override dipakai bolak-balik (banyak order, banyak admin/kasir berbeda).

### B79 — Step 2 wizard: rename + default alamat otomatis — **✅ DIPUTUSKAN 23 Sep**
(Prasyarat: B75 sudah jalan, jadi `alamatUtama()` SELALU ada isinya kalau
customer punya alamat.)

Keputusan client (23 Sep): **BUKAN** disembunyikan/disederhanakan
tampilannya (opsi awal yg diusulkan di draft pertama dokumen ini), tapi
**auto-terisi default** — field "Pilih Alamat" tetap tampil apa adanya
(radio + dropdown tidak dihilangkan), cuma **defaultnya otomatis diisi
alamat utama** (`is_utama`, sama seperti yg tampil di halaman detail
customer, mis. `/admin/customers/4`) begitu customer dipilih di Step 1.
Kalau customer punya lebih dari satu alamat tersimpan (krn sudah pernah
dikerjakan di alamat lain sebelumnya — jadi tambahan alamat di
`customer_addresses`, bukan alamat utama), dropdown tetap bisa diganti
admin ke alamat lain itu — tapi yang otomatis kepilih di awal selalu
alamat utama.

- Rename step label `"Alamat & Layanan"` → `"Layanan"` (`CreateOrder.php:
  150`) — tetap jalan, tidak berubah dari draft awal.
- Select `customer_address_id` (`CreateOrder.php:175-200`): tambah
  `->default(fn (Get $get) => filled($get('../../customer_id'))
  ? Customer::find($get('../../customer_id'))?->alamatUtama()?->id
  : null)` — pola path relatif `../../customer_id` PERSIS sama dgn yang
  sudah dibuktikan benar di `options()` closure field yg sama (komentar
  `CreateOrder.php:182-198`, fix komit `4a8b026`), jadi risiko regresi
  path-resolution rendah. Radio `mode` (`CreateOrder.php:164-173`) &
  dropdown TETAP tampil (tidak ada perubahan `->visible()`) — cuma
  defaultnya yang berubah dari kosong jadi ke-isi otomatis.
- Efeknya utk kasus paling umum (customer 1 alamat): dropdown langsung
  terlihat terisi ("Jln Dg Ramang (Kos Binabrata)" dst.), bukan lagi
  tampak kosong — inilah yang memutus rantai kebingungan admin di §0
  (dropdown kosong → ragu → isi Assign Teknisi & Assign Tim sekaligus).
  Admin yang memang ingin kerja di alamat lain tinggal ganti dropdown
  seperti biasa, atau pilih radio "Alamat Baru" utk titik yang benar-benar
  baru.

### B80 — Ganti "Assign Teknisi"/"Atau Assign Tim" jadi "PIC" + "Pendamping"
- **(a)** Di `stepAlamatLayanan()` (`CreateOrder.php:218-226`): relabel
  `teknisi_id` jadi **"Teknisi/PIC"**, tambah field baru
  `pendamping_teknisi_id` **"Pendamping (opsional)"** (opsi = user role
  Teknisi, sebaiknya exclude yg sudah dipilih sbg PIC via `Get`), **hapus**
  field `team_id` dari wizard ini saja (master data `Team`/aksi "Assign
  Tim" di tabel Order & Orderan Harian, dev-plan/admin §"Tim Teknisi
  permanen", **tidak disentuh** — tetap dipakai utk tim tetap yg memang
  sudah terdaftar, lihat non-tujuan §5).
  Di `OrderService::createOrders()` (`OrderService.php:212-341`):
  hapus guard konflik `team_id` vs `teknisi_id` (`OrderService.php:
  241-243`, jadi tidak relevan lagi krn wizard tidak kirim `team_id`),
  tambah 2 guard baru: pendamping tanpa PIC → `BusinessRuleException`
  ("Pendamping butuh Teknisi/PIC dipilih dulu"), pendamping == PIC →
  `BusinessRuleException` ("Pendamping tidak boleh sama dgn Teknisi/PIC" —
  mencegah UNIQUE constraint `order_technicians(order_id, teknisi_id)`,
  `create_order_technicians_table.php:17`, gagal jadi SQL error mentah).
  Kalau pendamping valid, tambah 1 `OrderTechnician::create()` lagi
  (setelah baris PIC yg sudah ada di `OrderService.php:332-334`) — pola
  identik dgn cara `assignTeam()` menambah anggota (`OrderService.php:
  ~380-410`), cuma tanpa lewat model `Team`.
- (b) Tetap 2 field terpisah (Assign Teknisi / Assign Tim) tapi ubah jadi
  radio pemilih mode dulu (mis. "Mode: Perorangan" vs "Mode: Tim
  Terdaftar") baru munculkan field sesuai mode — technically juga
  menghilangkan konflik, tapi client secara eksplisit minta bentuk
  "PIC + Pendamping", bukan sekadar radio switcher, dan tetap terikat ke
  master data Team utk kasus "jalan berdua" yg justru paling sering
  ad-hoc (bukan tim tetap).

Rekomendasi: **(a)** — sesuai spesifikasi eksplisit client, dan lebih pas
dgn realita lapangan (partner kerja berubah-ubah harian, bukan selalu tim
tetap terdaftar).

## 3. Desain Teknis Ringkas

**B75/B76 (sinkron alamat):**
- `app/Models/Customer.php`: tambah `booted(): void { static::saved(...) }`
  — cek `$customer->wasChanged('alamat') || $customer->wasRecentlyCreated`
  DAN `blank($customer->alamat) === false` DAN
  `$customer->addresses()->count() === 0` → buat 1 `CustomerAddress`.
- `app/Services/CustomerAddressSyncService.php` (baru, method
  `backfillMissing(): int` return jumlah dibuat) dipanggil dari:
  - Artisan command baru `app/Console/Commands/SyncAlamatUtama.php`
    (`customers:sync-alamat-utama`), sekali jalan pasca-deploy.
  - Akhir `CustomerImportService::import()` (`CustomerImportService.php`,
    setelah loop chunk insert selesai).

**B77/B78 (pembayaran adjustable):**
- Migrasi baru: `payments.catatan` (nullable text).
- `PaymentService::recordPayment()` (`PaymentService.php:34`): tambah
  parameter `?float $totalTagihanOverride = null, ?string $catatan =
  null`; `$total = $totalTagihanOverride ?? $payment?->total_tagihan ??
  $order->total();`; validasi `$catatan` wajib kalau
  `$totalTagihanOverride !== null && (float) $totalTagihanOverride !==
  $order->total()`.
- `OrderResource.php:735-761` (aksi `catatPembayaran`): tambah
  `TextInput::make('total_tagihan')` (numeric, default
  `fn (Order $r) => $r->total()`) + `Textarea::make('catatan')`
  (`->visible/->required` kalau `total_tagihan` beda dari default via
  `Get`), teruskan ke `recordPayment()`.

**B79 (step "Layanan"):**
- `CreateOrder.php:150`: `Step::make('Layanan')`.
- Select `customer_address_id` (`CreateOrder.php:175-200`): tambah
  `->default()` closure yg resolve ke `Customer::find($customerId)
  ->alamatUtama()?->id` (path relatif `../../customer_id`, sama seperti
  `options()` closure di field yg sama). Tidak ada perubahan `->visible()`
  — field & radio `mode` di atasnya tetap tampil seperti sekarang, cuma
  auto-terisi.

**B80 (PIC + Pendamping):**
- `CreateOrder.php:218-226`: relabel `teknisi_id`, tambah
  `Select::make('pendamping_teknisi_id')`, hapus `Select::make('team_id')`.
- `OrderService.php:241-243`: hapus guard lama, tambah 2 guard baru
  (lihat B80 di atas) sebelum baris `OrderTechnician::create()`
  (`OrderService.php:332-334`).

## 4. Definisi "Selesai"

- [ ] Label "Jenis Pelanggan" tampil "Cust Umum" (bukan "Rumahan") di
      wizard Buat Order, tabel/detail Order, & Orderan Harian.
- [ ] Customer baru/lama dgn "Alamat Utama" terisi otomatis punya ≥1
      `CustomerAddress` (is_utama) — dites: create customer via
      CustomerResource, cek tab "Alamat" langsung terisi.
- [ ] Command `customers:sync-alamat-utama` dijalankan sekali di semua
      environment (lokal, staging, production) pasca-deploy — backfill
      customer lama termasuk hasil Import Excel.
- [ ] Import Excel customer baru otomatis ikut tersinkron (test baru:
      import, cek `CustomerAddress` ikut terbuat).
- [ ] Wizard Buat Order step "Layanan": begitu customer terdaftar dipilih
      di Step 1, dropdown "Pilih Alamat" di titik pertama OTOMATIS terisi
      alamat utama (`is_utama`) customer itu — tidak lagi tampak kosong.
      Customer multi-alamat tetap bisa ganti dropdown ke alamat lain;
      titik ke-2+ (repeater) tetap bisa pilih/tambah alamat seperti
      sekarang.
- [ ] "Catat Pembayaran": admin bisa isi `Total Tagihan` beda dari
      `order->total()` (naik utk ongkir/tambahan, turun utk diskon),
      wajib isi `catatan` kalau beda, `payments.total_tagihan` &
      `incomes.nominal` mencerminkan nominal yang disesuaikan, order jadi
      `Selesai` sesuai total yang disesuaikan (bukan total katalog).
      Pembayaran DP dgn total disesuaikan lalu dilunasi kedua kalinya
      TETAP memakai total yang sama (tidak balik ke harga katalog).
- [ ] Wizard: field "Assign Teknisi"/"Atau Assign Tim" diganti
      "Teknisi/PIC" + "Pendamping (opsional)". Isi PIC+Pendamping →
      order langsung `Terjadwal`, kedua nama masuk `order_technicians`.
      Isi Pendamping tanpa PIC / Pendamping = PIC → pesan error jelas,
      bukan SQL exception mentah.
- [ ] Aksi "Assign Tim" (master data Team) di tabel Order & Orderan
      Harian TIDAK berubah — tetap tersedia utk tim tetap terdaftar.
- [ ] Test Pest baru: sinkron alamat (model hook + backfill command +
      import), payment total disesuaikan (naik & turun, termasuk kasus
      DP-lalu-lunasi), guard pendamping (tanpa PIC, = PIC).
- [ ] `php artisan test` penuh hijau, Pint bersih.
- [ ] Dicatat di `dev-plan/02-keputusan-eksekusi.md` setelah selesai.

## 5. Non-Tujuan (versi ini)

- **Tidak** menghapus atau mengubah `Team`/`TeamResource` (dev-plan/12
  §3.13) maupun aksi "Assign Tim" di tabel Order/Orderan Harian —
  keduanya tetap dipakai utk tim tetap yang memang terdaftar.
- **Tidak** mengubah field `customers.alamat` jadi wajib/dihapus — tetap
  field opsional seperti sekarang, cuma disinkronkan ke belakang layar.
- **Tidak** mengubah `gantiPic()`/`assignTechnician()` (re-assign
  pasca-order-dibuat) — perubahan cuma di wizard **Create Order**.
- **Tidak** menambah UI baru utk mengelola `customer_addresses` di luar
  tab "Alamat" yang sudah ada.

## 6. Pertanyaan Terbuka

**Semua 3 pertanyaan di draft awal sudah terjawab client (23 Sep):**

1. ~~Label final "Cust Umum"?~~ → **Dikonfirmasi**, "Cust Umum" final.
2. ~~B78 wajib/opsional?~~ → **Dikonfirmasi wajib** (opsi a) — lihat B78.
   Konsekuensi teknis: form "Catat Pembayaran" bertambah 2 field baru
   (`OrderResource.php:735-747`) — **"Total Tagihan"** (angka, pre-filled
   `order->total()`, bisa diubah admin) dan **"Alasan Penyesuaian"**
   (textarea, muncul & wajib diisi HANYA kalau admin mengubah angka Total
   Tagihan dari nilai default katalog; kalau tidak diubah, form tetap
   sesederhana sekarang — cuma Metode, Jumlah Dibayar, Tanggal Bayar).
3. ~~B79 threshold multi-alamat?~~ → **Dikonfirmasi**: bukan
   sembunyikan/tampilkan kondisional, tapi **selalu auto-default ke
   alamat utama** (`is_utama`, sesuai yg tampil di halaman detail
   customer) — picker tetap ada & tetap bisa diganti admin kalau memang
   ada alamat lain yg relevan utk order ini. Sudah direvisi di B79 §2.

Tidak ada pertanyaan terbuka tersisa — dokumen ini siap dieksekusi sesuai
urutan §7 begitu user memberi izin mulai coding.

## 7. Urutan Eksekusi (draft, dieksekusi SETELAH user setuju plan ini)

1. B75/B76 — model hook `Customer::booted()`, service
   `CustomerAddressSyncService`, Artisan command backfill, hook di
   `CustomerImportService::import()`, test Pest. Jalankan command backfill
   di semua environment.
2. B77/B78 — migrasi `payments.catatan`, ubah `PaymentService::
   recordPayment()`, form `catatPembayaran` di `OrderResource.php`, test
   Pest (naik/turun/DP-lalu-lunasi/catatan wajib).
3. B79 — rename step, sembunyikan picker alamat kondisional di
   `CreateOrder.php` (butuh B75 sudah jalan supaya kondisi "1 alamat"
   akurat).
4. B80 — field PIC+Pendamping di `CreateOrder.php`, guard baru &
   penghapusan guard lama di `OrderService::createOrders()`, test Pest.
5. Poin 1 (label "Cust Umum") — ganti string di 4 titik, cepat, bisa
   digabung commit mana saja di atas.
6. Test manual di browser: buat order customer lama (1 alamat) & customer
   multi-alamat, catat pembayaran dgn penyesuaian naik & turun, assign
   PIC+Pendamping.
7. `php artisan test` penuh + Pint.
8. Update `dev-plan/02-keputusan-eksekusi.md`, commit (tunggu instruksi
   "push" eksplisit dari user sebelum push, sesuai kebiasaan).
