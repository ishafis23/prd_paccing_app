# Multi-Alamat per Customer (Rumah / Usaha) + Unit AC per Alamat

> STATUS: **DRAFT — menunggu konfirmasi client** (req dari chat setelah 13 Sept)
>
> Request Ust Ranto: *"biasanya 1 nomor customer itu dia pesan untuk rumah
> pribadi dan perusahaan/alamat lain... ketika input orderan, bisa pilih
> alamat mana yg mau dicuci... dari satu customer bisa beberapa alamat (rumah
> 1, rumah 2, usaha 1, dst), tiap-tiap itu baru kita upload/input data AC-nya
> per ruangan lagi."*
>
> Dokumen ini spesifikasi teknis untuk menampung itu. **Belum ada kode yang
> ditulis** — eksekusi dimulai setelah bagian ini disetujui.

## 1. Masalah yang ditemukan

Struktur sekarang **satu customer = satu alamat**:

- `customers.alamat` + `latitude`/`longitude` (single field) — sumber alamat
  default saat buat order (`OrderService::createOrder`: `alamat_pengerjaan =
  $customer->alamat`).
- `customer_ac_units` langsung menunjuk ke `customer_id`. Tidak ada cara
  membedakan "AC-001 di rumah", "AC-001 di kantor".
- Form Buat Order hanya menyalin `customers.alamat` dan daftar unit AC
  difilter per `customer_id` secara global.

Klien ingin: **1 customer → banyak alamat** (rumah 1, rumah 2, usaha 1,
dst), dan **setiap alamat punya daftar Unit AC sendiri**. Saat input orderan
admin memilih **alamat mana** yang mau dicuci, baru unit AC-nya dari alamat
itu.

## 2. Keputusan desain (usulan, tunggu konfirmasi)

1. **Tabel baru `customer_addresses`** menjadi sumber kebenaran alamat.
   Kolom `customers.alamat/latitude/longitude` **tetap dipertahankan** sbg
   fallback data lama (backward-compatible) tapi tidak lagi diisi utk
   customer baru / jadi "alamat utk data lama".
2. **Setiap alamat punya koordinat GPS sendiri** (link maps + lat/lng),
   karena teknisi navigasi ke alamat yang dipilih di order — pola sama
   persis dgn "Lokasi Customer" yg sekarang ada di form Customer.
3. **`customer_ac_units.customer_id` TETAP ada** (kompatibilitas & filter
   cepat), ditambah kolom baru `customer_address_id` (nullable —
   unit lama yg belum punya alamat tetap valid). Validasi di service:
   unit harus milik customer & alamat yg sama.
4. **Keunikan `kode_unit`**: sekarang `unique(customer_id, kode_unit)`.
   Usulan: jadikan `unique(customer_address_id, kode_unit)` supaya kode
   unit yang sama boleh ada di alamat berbeda (mis. "Ruang Guru" di gedung
   A dan gedung B). **Konsekuensi**: perlu dropdown alamat saat import unit
   AC (§7) & migrasi unique index (§4).
5. **Alamat utama** (`is_utama` boolean, satu per customer): dipakai sbg
   default pilihan saat buat order kalau admin tidak mengganti.
6. **`orders.customer_address_id`** (nullable FK) + `orders.alamat_pengerjaan`
   tetap sebagai snapshot teks (data lama & kecocokan sumber di teknisi/
   surat jalan tidak berubah). Default `alamat_pengerjaan` dari alamat yang
   dipilih.

## 3. Perubahan database

### 3.1 Tabel baru `customer_addresses`

| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| customer_id | FK → customers (cascade) | |
| nama_lokasi | string | label pilihan admin, mis. "Rumah 1", "Usaha", "Apartemen" |
| alamat | text | alamat lengkap |
| maps_link | text, nullable | link Google Maps |
| latitude | decimal(10,7), nullable | |
| longitude | decimal(10,7), nullable | |
| is_utama | boolean, default false | satu per customer |
| catatan | text, nullable | |
| timestamps | | |

### 3.2 Migrasi data existing (backfill)

Satu migrasi berisi:

1. Buat `customer_addresses` + kolom baru di `customer_ac_units`,
   `orders`.
2. **Backfill alamat**: utk tiap customer yg `alamat`-nya terisi (atau
   punya `latitude`/`longitude`), buat 1 `customer_addresses`
   (`nama_lokasi` = "Alamat Utama", `is_utama` = true, salin alamat +
   koordinat). Customer tanpa alamat → tidak dibuat (unit/order mereka
   tetap nullable, backward-compatible).
3. **Backfill unit**: `customer_ac_units.customer_address_id` = alamat utama
   customer yg bersangkutan (kalau ada alamatnya).
4. **Backfill order**: `orders.customer_address_id` = alamat utama customer
   (kalau ada).
5. Ganti unique index `customer_ac_units`: hapus `(customer_id, kode_unit)`,
   buat `(customer_address_id, kode_unit)`.
6. `orders.customer_id` + `customer_ac_unit_id` TIDAK diubah (tetap).

> Catatan: migrasi data bisa banyak (ribuan unit) — pakai chunking seperti
> migrasi `order_items` lama, idempoten, dan bisa di-rollback.

## 4. Perubahan service layer

### `OrderService`

- `createOrder()`: terima `customer_address_id` opsional. Default = alamat
  utama customer (atau fallback `customers.alamat` lama). Set
  `alamat_pengerjaan` dari alamat terpilih.
- `resolveAcUnit()` diperluas: kalau unit diisi **dan** alamat diisi, alamat
  unit harus sama dgn alamat pilihan order. Kalau hanya alamat diisi →
  simpan alamatnya saja.
- `tambahLayanan()` → param alamat sama (baris `order_items` ikut alamat
  order, ditambah alamat unit utk baris tsb sudah otomatis dari unit-nya).

### Model baru `CustomerAddress`

Relasi:

- `Customer::addresses()` (hasMany), `Customer::alamatUtama()` (hasOne).
- `CustomerAddress::acUnits()` (hasMany), `CustomerAddress::orders()`
  (hasMany).
- `Order::customerAddress()` (belongsTo).
- `CustomerAcUnit::customerAddress()` (belongsTo) + helper `labelTampil()`
  kaya alamat (mis. "Rumah 1 · AC-001 — Ruang Guru").

## 5. Perubahan UI Admin

### Form Data Customer (`CustomerResource`)

- Field `alamat` + "Lokasi Customer" (maps/lat/lng) **DIKELUARKAN** dari
  form create/edit utama — pindah ke tab "Alamat" (relation manager baru):
  - `AddressesRelationManager` → list alamat (nama_lokasi, alamat,
    badge "Utama", koordinat), form repeatable dgn field peta/ambil
    koordinat sama persis pola yg ada di form Customer sekarang, tombol
    "Jadikan Utama" (keputusan: 1 utama per customer).
- Tab "Unit AC" (`AcUnitsRelationManager`) dimodifikasi:
  - Form unit + kolom tabel + filter: **select "Alamat" wajib-ish**
    (default alamat utama). Daftar opsi = alamat customer ini.
  - Portal/riwayat unit tetap berjalan (per unit → per alamat).

### Form Buat Order (`OrderResource`)

Urutan baru:

1. Customer.
2. **Alamat** (select, reaktif thd customer; opsi = `customer_addresses`
   customer, label "nama_lokasi — alamat"; auto-pilih alamat utama).
   `afterStateUpdated` → isi `alamat_pengerjaan` + kosongkan unit.
3. **Unit AC** — difilter `customer_id` **DAN `customer_address_id`**
   (opsional; kalau customer belum punya alamat/unit tetap bisa lanjut).
4. Jenis layanan + jumlah dsb — tidak berubah.

Infolist detail order: tampilkan nama lokasi/alamat yang dipilih
(`customerAddress`), bukan cuma `alamat_pengerjaan`. `alamat_pengerjaan`
tetap snapshoot di surat jalan/portal teknisi (tidak perlu diubah).

### Order Massal (Import Excel dispatch, §3.4)

`OrderDispatchImportService::import()`:
- Modal "Buat Order Massal" + select **Alamat** (reaktif customer? modal ada
  di tab customer — alamat customer itu saja), `alamat_pengerjaan` diambil
  dari alamat terpilih (sekarang hard-coded `$customer->alamat`).
- Unit difilter per alamat pilihan.

## 6. Perubahan Portal Customer (§3.6)

`Portal\Dashboard` — daftar unit dikelompokkan **per alamat**: header
nama_lokasi + alamat + koordinat, di bawahnya unit AC alamat itu (histori
per unit tetap). Dashboard/controller hanya butuh eager-load
`addresses.acUnits.latestOrderItem`.

## 7. Perubahan Import Unit AC

`CustomerAcUnitImportService` (tab Unit AC, §3.10):

- Opsi paling simpel (usulan): **modal import bertambah select "Alamat
  tujuan"** (default alamat utama) — file TIDAK berubah format
  (`kode_unit, kode_ruangan, jenis_unit, pk, catatan`), seluruh baris masuk
  ke alamat yg dipilih. Template/petunjuk perlu update kalimat satu baris.
- Alternatif (kalau satu file ingin menyebar ke beberapa alamat): tambah
  kolom opsional `alamat` di file (mapping nama_lokasi → walau repot utk
  ribuan baris). **Usulan: pilih via modal dulu**, lebih cepat & aman
  konsisten dgn file 2000 baris.
- Duplikasi `kode_unit`: deteksi per alamat terpilih (ubah dari
  `$customer->acUnits()` jadi `alamat->acUnits()`); "sudah terdaftar utk
  alamat ini".

## 8. Dampak ke fitur lain (tidak perlu diubah, hanya diverifikasi)

- `surat-jalan.blade.php`, jadwal teknisi, `orderan-harian.blade.php`, resi:
  pakai `orders.alamat_pengerjaan` (snapshot) → **aman**.
- Auto-reminder / histori per unit: tetap per customer/unit — hanya soal
  tampilan jika perlu filter per alamat (opsional, Stage 2).
- `CustomerPortalService`, auth portal: tidak tersentuh.

## 9. File yang tersentuh (perkiraan)

| Area | File |
|---|---|
| Migrasi | `database/migrations/2026_09_14_..._create_customer_addresses_table.php`, `..._add_customer_address_id_ke_customer_ac_units_dan_orders.php` (backfill + unique) |
| Model | `app/Models/CustomerAddress.php` (baru), `Customer.php`, `CustomerAcUnit.php`, `Order.php`, `OrderItem.php` |
| Service | `OrderService.php`, `CustomerAcUnitImportService.php`, `OrderDispatchImportService.php` |
| Filament | `CustomerResource.php`, `RelationManagers/AddressesRelationManager.php` (baru), `RelationManagers/AcUnitsRelationManager.php`, `OrderResource.php` (form + infolist) |
| Portal | `app/Livewire/Portal/Dashboard.php`, `resources/views/livewire/portal/dashboard.blade.php` |
| Test | baru: `CustomerAddressTest.php`, `OrderAddressTest.php`, `CustomerAddressBackfillTest.php`; update: `CustomerAcUnitTest`, `OrderDispatchImportTest`, `CustomerPortalTest`, `OrderAcUnitLinkTest` |

## 10. Rencana pengujian

- Migrasi/backfill: customer dgn alamat → 1 `customer_addresses` utama,
  unit & order tertaut; customer tanpa alamat → alamat tidak dibuat, unit/
  order tetap nullable; idempoten (re-run tidak dobel).
- CRUD alamat admin (buat/icon peta/koordinat, jadikan utama — utama baru
  menggantikan yg lama).
- Buat order: pilih alamat → `alamat_pengerjaan` & `customer_address_id`
  benar; unit AC hanya muncul dari alamat terpilih; unit yg bukan alamat
  tsb ditolak (`easy`).
- Import Unit AC ke alamat tujuan; kode_unit duplikat per alamat
  (dilewati), kode_unit sama di alamat beda diperbolehkan.
- Order massal: alamat terpilih masuk ke order.
- Portal customer: unit berkelompok per alamat, histori tetap benar.
- Re-run seluruh test suite (439+ test sekarang) → semua hijau incl. yg
  lama (backward-compatible).

## 11. Pertanyaan terbuka (konfirmasi client sebelum eksekusi)

1. **Keunikan kode_unit**: boleh `kode_unit` yang sama di alamat berbeda
   utk customer yang sama (usulan: ya — "Ruang Guru" boleh ada di 2 gedung)?
2. **Import Unit AC**: cukup pilih alamat lewat modal sebelum upload
   (usulan), atau butuh kolom `alamat` di dalam file Excel?
3. **Alamat lama (customer yg sudah terlanjur 1 alamat)**: cukup label
   default "Alamat Utama", atau perlu nama_lokasi yang diisi manual saat
   migrasi?
4. Perlu tidaknya **filter historis/reminder per alamat** di portal
   customer sekarang, atau cukup giliran berikutnya (Stage 2)?

## 12. Urutan pengerjaan (setelah disetujui)

1. Migrasi: tabel `customer_addresses` + kolom FK + backfill + unique index.
2. Model & relasi (`CustomerAddress`, update 3 model + Order).
3. `OrderService` (createOrder/resolveAcUnit/tambahLayanan).
4. UI Admin: `AddressesRelationManager`, modifikasi `AcUnitsRelationManager`
   & `CustomerResource`.
5. `OrderResource` form Buat Order (select alamat reaktif + filter unit).
6. Import Unit AC + Order Massal (select alamat).
7. Portal Customer (grouping per alamat).
8. Test baru + update test lama, jalankan seluruh suite.