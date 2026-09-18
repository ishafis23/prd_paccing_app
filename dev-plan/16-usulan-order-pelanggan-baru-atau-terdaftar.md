# Usulan — "Tambah Order": Pilihan Pelanggan Baru vs Terdaftar

> STATUS: **✅ SELESAI DIEKSEKUSI (18 September 2026)** — B56, B57, B58
> (direvisi: Unit AC disertakan), B58b, B59, B60 (warning bukan blokir).
> `CustomerService` baru, `OrderResource::form()` + `CreateOrder` direvisi.
> 13 test Pest baru, suite penuh 572 passed. Dicatat di
> `02-keputusan-eksekusi.md`.

## 1. Konteks

Arahan client: saat admin klik **"Tambah Order"**, sekarang **wajib** ada
customer di database dulu (buka menu Customer → tambah → baru balik ke
Order → pilih customer itu dari dropdown). Client minta ini lebih
**praktis**: begitu buka form Tambah Order, ada pilihan:

1. **Pelanggan Terdaftar** — alurnya **sama seperti sekarang** (pilih dari
   daftar customer yang sudah ada).
2. **Pelanggan Baru** — isi data customer **langsung di form Tambah Order
   itu juga**, tanpa pindah halaman dulu.

## 2. Temuan dari Kode yang Ada (penting utk desain)

- Form "Tambah Order" (`OrderResource::form()`,
  `app/Filament/Resources/OrderResource.php:50-127`) punya rantai reaktif:
  pilih **Customer** → otomatis isi `alamat_pengerjaan`, `jenis_pelanggan`,
  dan alamat utama customer itu (`customer_address_id`) → pilih **Alamat**
  → filter **Unit AC**. Pola `Forms\Set`/`Forms\Get` inilah yang jadi acuan
  gaya form ini.
- `OrderService::createOrder()` (`app/Services/OrderService.php:29-78`)
  **mewajibkan** `customer_id` yang sudah ada (`Customer::findOrFail(...)`)
  — ini titik yang perlu diubah/ditambah jalur baru.
- **Kabar baik:** alamat (`customer_address_id`) dan unit AC
  (`customer_ac_unit_id`) itu **sudah opsional** sekarang — order bisa
  dibuat tanpa keduanya (`resolveAlamat`/`resolveAcUnit`,
  `OrderService.php:391-427`, keduanya balikin `null` dgn aman kalau
  kosong). Jadi jalur "Pelanggan Baru" **tidak wajib** langsung isi alamat
  terstruktur/unit AC — cukup data customer inti + `alamat_pengerjaan` yang
  memang sudah jadi field wajib-tampil di form order.
- **Tidak ada `CustomerService`** sama sekali — beda dari
  Order/Attendance/dll yang sudah pola service-layer, CRUD Customer
  sekarang langsung Eloquent lewat Filament (`CustomerResource`,
  `CreateCustomer` page kosongan tanpa logika). Ini gap yang perlu diisi
  kalau mau konsisten dgn pola project.
- Form Customer (`CustomerResource::form()`,
  `app/Filament/Resources/CustomerResource.php:32-68`) wajib: `nama`,
  `no_hp`, `jenis` (default Perorangan), `area` (3 pilihan kota, **tanpa
  default**), `sumber_lead` (**ada pilihan "Lainnya"** sbg fallback),
  `status` (default Lead). `email`/`alamat`/`catatan` opsional.
- **Tidak ada unique constraint** di `no_hp` maupun `email` customer
  (dicek migrasi) — jadi secara teknis bebas duplikat, tapi ini juga
  artinya **tidak ada pengaman otomatis** dari sistem kalau admin
  "kepraktisan" bikin customer yang sama berkali-kali (lihat B60).
- Belum pernah ada pola "buat data terkait langsung dari form lain" di
  project ini (`grep createOptionForm` nihil) — semua pola yang ada pakai
  Action + modal form terpisah, bukan Filament `createOptionForm` bawaan.

## 3. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B56 — Bentuk pilihan "Baru" vs "Terdaftar"
- **(a) Radio/Toggle di paling atas form Tambah Order** ("Pelanggan Baru" /
  "Pelanggan Terdaftar", default **Terdaftar**) — pilih salah satu langsung
  menukar blok field di bawahnya: Terdaftar → dropdown Customer seperti
  sekarang (tidak berubah sama sekali); Baru → blok mini-form data customer
  (nama, no HP, dst) muncul langsung di halaman yang sama, tanpa modal/
  popup. Paling sesuai kata "ada pilihan pelanggan baru dan terdaftar" dari
  client — jelas terlihat, tidak tersembunyi.
- (b) Pakai fitur bawaan Filament `Select::make('customer_id')
  ->createOptionForm([...])` — customer_id tetap dropdown, tambah tombol
  "+" kecil di sampingnya yang buka **modal** form customer baru. Lebih
  sedikit kode (fitur bawaan), tapi pilihannya "tersembunyi" di tombol
  kecil — kurang match dgn "ada pilihan" yang diminta client, dan belum
  pernah dipakai di project ini (precedent nihil, lihat §2).

Rekomendasi: **(a)** — sesuai literal permintaan client & konsisten dgn
gaya reaktif form yang sudah ada di form ini.

### B57 — Field apa saja yang diminta saat pilih "Pelanggan Baru"
- **(a) Minimal tapi tetap konsisten data:** `nama*`, `no_hp*`, `jenis`
  (default Perorangan — nilai yang sama otomatis dipakai juga utk
  `jenis_pelanggan` order, jadi tidak ditanya dua kali), `area*` (tetap
  wajib, dipakai utk zona servis/laporan — tidak ada default masuk akal),
  `sumber_lead` (default **"Lainnya"**, admin boleh ganti kalau tahu),
  `email` (opsional). **Alamat TIDAK ditanya terpisah** — field
  `alamat_pengerjaan` yang sudah ada di form order (dan memang wajib
  tampil) dipakai ulang jadi alamat customer baru itu juga (lihat B58).
- (b) Field lengkap sama seperti form Customer penuh (termasuk `status`,
  `catatan`) — lebih lengkap tapi mengulang tujuan "lebih praktis" jadi
  form tambah order malah makin panjang.

Rekomendasi: **(a)** — paling pas dengan "lebih praktis" yang diminta;
field yang tidak esensial (status, catatan) tetap bisa dilengkapi nanti
lewat menu Customer kalau perlu.

### B58 — Alamat & Unit AC utk customer baru — **✅ direvisi (klarifikasi 18 Sep): sekalian dimasukkan ke database, bukan disembunyikan**
- **(a) (terpilih, direvisi)** `alamat_pengerjaan` (field yang sudah ada &
  wajib tampil di form order) dipakai ganda: disimpan sbg
  `orders.alamat_pengerjaan` **dan** otomatis dibuatkan 1 baris
  `customer_addresses` (`is_utama` otomatis via aturan model yg sudah ada).
  **Ditambah**: 1 blok mini "Unit AC" (ruangan + jenis + PK, lihat §4 tabel
  field) muncul di mode "Baru" — kalau diisi, otomatis bikin **1 baris**
  `customer_ac_units` tertaut ke alamat baru itu, dan `customer_ac_unit_id`
  order langsung ke unit itu (bukan disembunyikan seperti draf awal).
  `kode_unit` (wajib unik per alamat) **di-generate otomatis** oleh sistem
  (mis. "AC-01") — admin tidak perlu mengarang kode (lihat B58b).
  Kalau order utk >1 unit (`jumlah_unit` > 1), cuma **unit pertama** yang
  tercatat detail di sini — sisanya dilengkapi belakangan lewat halaman
  Customer (sama seperti alur customer lama hari ini, tidak berbeda).
- (b) Cuma isi `orders.alamat_pengerjaan` + `customers.alamat` (field lama)
  — TIDAK bikin baris `customer_addresses`/`customer_ac_units` sama sekali.
  **Ditinggalkan** — bertentangan dengan klarifikasi client ("sekalian
  masuk database").

### B58b — Kode unit (`kode_unit`) di-generate otomatis atau diisi admin?
`customer_ac_units.kode_unit` wajib & unik per alamat (bukan sekadar label
bebas — dipakai identifikasi unit lintas fitur, mis. histori pencucian).
- **(a)** Sistem generate otomatis format `AC-01`, `AC-02`, dst (urutan per
  alamat) — admin tidak diminta mengarang kode di form cepat ini (selaras
  "praktis"). Bisa diubah manual belakangan lewat halaman Customer kalau
  admin mau kode lain (mis. sesuai label fisik di lokasi).
- (b) Admin isi manual di form cepat ini juga — kontrol penuh sejak awal,
  tapi nambah 1 field wajib lagi yg mengurangi "kepraktisan" yang diminta.

Rekomendasi: **(a)** — konsisten dgn semangat "lebih praktis", tetap bisa
dikoreksi belakangan tanpa efek samping (kode cuma label internal).

### B59 — Tempat logika "buat customer baru" diletakkan
- **(a) `CustomerService::create()`** (kelas baru, mengisi gap di §2) —
  dipanggil dari jalur "Pelanggan Baru" di `CreateOrder` page SEBELUM
  memanggil `OrderService::createOrder()` yang sudah ada (tidak diubah
  logikanya sama sekali, cuma dikasih `customer_id` hasil create barusan).
  `CustomerResource` (form Customer biasa) **tetap seperti sekarang**
  (Eloquent langsung) — migrasi resource itu ke service jadi usulan
  terpisah, di luar cakupan ini (tidak nyenggol kode yang sudah stabil).
- (b) Taruh logika langsung di `OrderService::createOrder()` (nerima data
  customer opsional, bikin Customer di dalam method yg sama) — order &
  customer creation jadi bercampur dlm 1 method besar, lebih sulit dites
  terpisah.

Rekomendasi: **(a)** — pemisahan tanggung jawab yang jelas, sekalian
mengisi gap layanan Customer yang sudah lama hilang di project ini.

### B60 — Cegah duplikat customer tak sengaja — **✅ dikonfirmasi 18 Sep: warning, bukan blokir**
- **(a) (terpilih, sesuai klarifikasi) Warning reaktif, tidak memblokir**:
  begitu admin mengisi/keluar dari field No. HP di blok "Pelanggan Baru",
  sistem cek langsung (live, tanpa submit) apakah ada customer lain dgn
  `no_hp` **persis sama** — kalau ada, tampil **peringatan** di bawah field
  itu ("⚠️ No. HP ini sudah terdaftar atas nama **{nama}** — pastikan ini
  memang orang/order baru, atau pindah ke 'Pelanggan Terdaftar'"). Admin
  **tetap bisa lanjut submit** meski peringatan muncul — keputusan akhir
  di admin, sistem cuma mengingatkan.
- (b) Tolak/blokir submit kalau ada duplikat — **ditinggalkan**, client
  eksplisit minta bentuknya warning, bukan blokir.

Keputusan: **(a)**.

## 4. Desain Teknis (ringkas)

### Komponen baru
- **`app/Services/CustomerService.php`** (baru) —
  - `cariByNoHp(string $noHp): ?Customer` — dipakai pengecekan reaktif B60
    (bukan validasi keras, cuma lookup).
  - `create(array $data, User $by): Customer`: validasi `nama`/`no_hp`
    wajib isi, `DB::transaction()`: buat `Customer` (fillable sesuai
    `$fillable` model + default `status = Lead`); kalau
    `alamat_pengerjaan` disertakan → buat 1 `CustomerAddress` (`alamat` =
    teks itu, `is_utama` otomatis via `booted()` yang sudah ada); kalau
    `kode_ruangan` disertakan → buat 1 `CustomerAcUnit` tertaut ke alamat
    barusan, `kode_unit` di-generate otomatis format `AC-01` dst per
    alamat (B58b). Balikin `Customer` + relasi alamat/unit yang baru
    dibuat (utk disuntik ke payload order). Role gate Admin/Owner
    (`RestrictsByRole`, sama seperti `OrderService`). **Tidak** ada guard
    penolakan duplikat di sini (B60 = warning di level form, bukan blokir
    service).
- **`CreateOrder` page** (`app/Filament/Resources/OrderResource/Pages/
  CreateOrder.php`) — `handleRecordCreation($data)` direvisi: kalau
  `$data['mode_pelanggan'] === 'baru'`, panggil `CustomerService::create(
  [...dari field baru...], auth()->user())` dulu → dapat `Customer` (+
  alamat + unit baru) → suntik `$data['customer_id']`,
  `$data['customer_address_id']`, `$data['customer_ac_unit_id']` dari hasil
  itu → lanjut panggil `OrderService::createOrder($data, ...)` **seperti
  biasa, tanpa perubahan** di service itu.
- **`OrderResource::form()`**:
  - `Forms\Components\Radio::make('mode_pelanggan')` di paling atas
    (`live()`, default `'terdaftar'`). Blok `customer_id` +
    `customer_address_id` + `customer_ac_unit_id` yang sudah ada dibungkus
    `->visible(fn (Get $get) => $get('mode_pelanggan') === 'terdaftar')`.
  - Blok baru (nama/no_hp/jenis/area/sumber_lead/email + mini-blok unit AC
    ruangan/jenis/PK) `->visible(...) === 'baru'`; `jenis` blok baru
    di-`Set` juga ke `jenis_pelanggan` (gantikan cascade `customer_id` yg
    cuma jalan di mode Terdaftar).
  - Field `pelanggan_baru_no_hp` → `->live(onBlur: true)`
    `->afterStateUpdated()` panggil `CustomerService::cariByNoHp()`, kalau
    ketemu → `->helperText()`/`Forms\Components\Placeholder` reaktif
    tampilkan peringatan (B60) — **tidak** ada `->required()`/validasi yg
    menolak submit.

### Field form baru (mode "Baru")
| Field | Tipe | Wajib | Catatan |
|---|---|---|---|
| `pelanggan_baru_nama` | TextInput | ya | → `customers.nama` |
| `pelanggan_baru_no_hp` | TextInput | ya | → `customers.no_hp`; live, tampilkan warning kalau ketemu duplikat (B60) |
| `pelanggan_baru_jenis` | Select | ya (default Perorangan) | → `customers.jenis` **dan** `orders.jenis_pelanggan` |
| `pelanggan_baru_area` | Select | ya | → `customers.area` (tetap 3 pilihan apa adanya — dikonfirmasi 18 Sep, tidak ditambah) |
| `pelanggan_baru_sumber_lead` | Select | ya (default Lainnya) | → `customers.sumber_lead` |
| `pelanggan_baru_email` | TextInput | opsional | → `customers.email` |
| `pelanggan_baru_kode_ruangan` | TextInput | opsional | → `customer_ac_units.kode_ruangan`; kosongkan blok ini = tidak bikin unit sama sekali |
| `pelanggan_baru_jenis_unit` | Select (UnitType) | opsional | → `customer_ac_units.jenis_unit` |
| `pelanggan_baru_pk` | TextInput | opsional | → `customer_ac_units.pk` (teks bebas, mis. "1 PK") |

`alamat_pengerjaan` (field lama, tetap 1 di form) → `orders.alamat_pengerjaan`
**dan** dipakai bikin `customer_addresses.alamat` (B58). `kode_unit`
di-generate sistem, tidak diminta dari admin (B58b).

**Batasan disengaja:** kalau `jumlah_unit` order > 1, cuma unit **pertama**
yang detailnya tercatat lewat blok ini — unit ke-2 dst dilengkapi
belakangan lewat halaman Customer (§8).

### Tidak ada migrasi baru
Semua tabel yang dipakai (`customers`, `customer_addresses`,
`customer_ac_units`, `orders`) sudah ada — usulan ini murni penataan ulang
form + 1 service baru.

## 5. Alur Pengguna

**A. Admin pilih "Pelanggan Terdaftar" (default, sama seperti sekarang):**
1. Buka Tambah Order → radio di "Pelanggan Terdaftar" → form persis
   seperti hari ini (cari & pilih customer, dst).

**B. Admin pilih "Pelanggan Baru":**
1. Buka Tambah Order → klik radio "Pelanggan Baru" → blok Customer lama
   hilang, muncul field nama/no HP/jenis/area/sumber lead/email + mini-blok
   Unit AC (ruangan/jenis/PK, opsional).
2. Ketik No. HP → kalau ternyata sudah ada di database, langsung muncul
   peringatan di bawah field itu (B60) — admin bisa tetap lanjut atau
   berhenti & pindah ke mode "Terdaftar", sesuai penilaian sendiri.
3. Isi field itu + field order seperti biasa (layanan, jumlah unit, alamat
   pengerjaan, jadwal, dst).
4. Submit → 1x klik langsung menghasilkan: `Customer` baru + alamat
   utamanya (dari `alamat_pengerjaan`) + (kalau kolom ruangan diisi) 1 unit
   AC pertama + `Order` yang tertaut ke semuanya.

## 6. Definisi "Selesai"

1. Radio "Pelanggan Baru" vs "Pelanggan Terdaftar" tampil di form Tambah
   Order, default Terdaftar.
2. Mode Terdaftar: **tidak ada perubahan perilaku** dari sekarang (regresi
   dijaga test lama).
3. Mode Baru: submit 1x menghasilkan `Customer` baru + `CustomerAddress`
   utama (dari `alamat_pengerjaan`) + (opsional, kalau ruangan diisi) 1
   `CustomerAcUnit` dgn `kode_unit` ter-generate otomatis + `Order` yang
   tertaut ke ketiganya — tanpa buka menu Customer sama sekali.
4. No. HP yang sudah terdaftar memunculkan **peringatan reaktif** (bukan
   error submit) sebelum admin submit (B60).
5. Hanya Admin/Owner yang bisa pakai jalur ini (sama seperti hak buat order
   & customer yang sudah ada).
6. Test Pest hijau: `CustomerService::create` (sukses incl. alamat & unit,
   guard role), `cariByNoHp`, `CreateOrder` mode baru end-to-end (Livewire
   fillForm — customer+alamat+unit+order semua kebentuk benar), regresi
   mode terdaftar tidak berubah.

## 7. Pertanyaan Terbuka

Tidak ada lagi yang mengganjal eksekusi — 3 pertanyaan di draf awal sudah
dijawab (18 Sep 2026): area tetap 3 pilihan apa adanya, duplikat No. HP
berupa warning (bukan blokir), dan Unit AC **disertakan** sekalian (bukan
disembunyikan) — semua sudah tercermin di §3/§4 di atas.

## 8. Batas & Non-Tujuan (versi ini)

- **Tidak mengubah** `CustomerResource` (form Customer penuh via menu
  Customer) — tetap seperti sekarang, cuma dapat jalur cepat TAMBAHAN dari
  form Order.
- Unit AC di jalur cepat ini **cuma unit pertama** (kalau `jumlah_unit` >
  1) — sisanya dilengkapi belakangan lewat halaman Customer, sama seperti
  alur customer lama hari ini.
- **Tidak** ada pengecekan duplikat berbasis nama/fuzzy-match — hanya
  `no_hp` persis sama, dan sifatnya warning bukan blokir (B60, cakupan
  sengaja dibatasi).
- **Tidak** mengubah `OrderService::createOrder()` — jalur baru murni
  "menyuntik" `customer_id`/`customer_address_id`/`customer_ac_unit_id`
  sebelum memanggilnya (B59), supaya alur order yang sudah stabil & teruji
  tidak tersentuh sama sekali.

## 9. Urutan Eksekusi Usulan

1. **Fase A** — `CustomerService::create()` (+ `cariByNoHp()`) — buat
   Customer + CustomerAddress + (opsional) CustomerAcUnit dgn kode_unit
   auto-generate, 1 transaksi. Test: sukses lengkap (3 tabel), sukses tanpa
   unit (ruangan kosong), guard role, `cariByNoHp` ketemu/tidak ketemu.
2. **Fase B** — revisi `OrderResource::form()` (radio + blok kondisional +
   warning reaktif No. HP) + `CreateOrder::handleRecordCreation()` (jalur
   suntik customer_id/address_id/ac_unit_id) + test Livewire end-to-end
   mode Baru (customer+alamat+unit+order kebentuk benar) & regresi mode
   Terdaftar (tidak berubah).

Skala kecil-menengah (1 service baru + 1 form + 1 page) — realistis 2 fase
di atas, siap dieksekusi langsung (semua pertanyaan §7 sudah terjawab).
