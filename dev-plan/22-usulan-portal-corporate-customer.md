# 22 — Usulan Portal Corporate Customer (Pekerjaan, Laporan Foto, Invoice, & Akses PIC)

> STATUS: **📝 USULAN — menunggu persetujuan user** (keputusan di §9). Dokumen ini
> **belum dieksekusi**: belum ada kode, migrasi, atau test yang dibuat untuk plan 22.
> Eksekusi baru boleh dimulai **setelah** dev-plan/21 **Fase 4, 5, dan 6** selesai
> (lihat §2.4 dan §6). Bagian §4.4 berisi **REVISI** atas keputusan 13 Sept di
> [`portal-customer/01-konsep-portal-customer.md`](portal-customer/01-konsep-portal-customer.md)
> §4 poin 1 (1 akun per customer) — perubahan & alasannya ditulis eksplisit di sana.

Sumber permintaan (user, 10 Okt 2026, kutipan apa adanya):

> "isinya tentang pembuatan portal corporate jadi customer tipe perusahaan ada nanti page
> khusus untuk pantau unit-unit yang telah dikerjakannya sesuai invoice dll, atau tanggal
> pengerjaan, di sana lengkap ada laporan tadi foto dan keterangan, serta ada invoice tagihan
> dari tiap-tiap orderan. jadi hostingnya lengkap di sana. nah saya tidak tahu bagaimana untuk
> username dan password yang akan digunakan pada customer itu. susun plannya pada md file 22 dulu"

---

## 1. Ringkasan & tujuan

Portal corporate = **satu tempat** bagi PIC customer berjenis `company` untuk melihat sendiri,
tanpa minta ke admin lewat WA satu per satu:

1. **Pekerjaan** — order apa saja yang sudah/akan dikerjakan, tanggal pengerjaan, unit AC
   mana, cabang mana, teknisi siapa.
2. **Laporan foto + keterangan per unit** — hasil dev-plan/21 Fase 4 (`order_unit_reports`)
   dan Fase 5 (preview + PDF laporan format Circle K).
3. **Invoice/tagihan per order** — hasil dev-plan/21 Fase 6 (`invoices` + PDF + lampiran),
   termasuk saldo yang belum dibayar.
4. **Rekap unit** — unit mana yang sudah dikerjakan pada periode tertentu, mana yang belum,
   dan jadwal berikutnya.

Plus jawaban atas pertanyaan user: **bagaimana username & password PIC diberikan** (§5).

Portal ini **memperluas** Portal Customer yang sudah ada (dev-plan/12 §3.6), bukan membuat
aplikasi baru: guard, layout, route prefix `/portal`, dan halaman login dipakai ulang.

---

## 2. Kondisi sekarang (dengan bukti)

Perintah yang dipakai untuk memeriksa: `Glob` atas `app/Livewire/Portal/**`,
`resources/views/**/portal/**`, `app/Services/*Portal*`, `tests/Feature/*Portal*`;
`Grep "portal|customer" routes/web.php`; `Grep "invoice|Invoice|LaporanPengerjaan" app/`;
`Grep "dompdf|browsershot|snappy" composer.json`; `Grep "RateLimiter|throttle" app/`;
pembacaan langsung berkas yang disebut di bawah.

### 2.1 Yang sudah ada

| Bagian | Bukti |
|---|---|
| Route login, logout, dashboard | `routes/web.php:84` `GET /portal/login` → `PortalLogin`; `:86-92` `POST /portal/logout` (`auth:customer`); `:94-96` grup `auth:customer` prefix `portal` berisi **hanya** `GET /` → `PortalDashboard` |
| Guard & provider | `config/auth.php:49-52` guard `customer` (session, provider `customers`); `:78-81` provider `customers` → `App\Models\Customer`. **Tidak ada** broker password untuk `customers` (`:108-115` hanya `users`) |
| Tamu diarahkan ke login portal | `bootstrap/app.php:24` `redirectGuestsTo(... is('portal*') ? route('portal.login') ...)` |
| Model akun | `app/Models/Customer.php:24` `implements AuthenticatableContract`; `:42-44` `password` hidden; `:53` cast `hashed`; `:61-64` `bisaLoginPortal()` = email terisi **dan** password terisi. Kolom ditambah oleh `database/migrations/2026_09_14_000008_add_password_ke_customers_table.php` |
| Login | `app/Livewire/Portal/Login.php:36-38` cari customer berdasar `lower(email)`; `:40` tolak bila belum aktif/password salah; `:46` tolak bila `status = nonaktif`; `:52-53` login + regenerate session. **Tidak ada pembatasan percobaan login** (`Grep RateLimiter|throttle app/` → kosong) |
| Pengelolaan akses oleh admin | `app/Services/CustomerPortalService.php:20-46` `aturPassword()` (admin/owner, wajib email, min 8 karakter, email unik di antara customer ber-portal); `:52-60` `cabutAkses()` (password → `null`). UI: `app/Filament/Resources/CustomerResource.php:102-124` aksi tabel **"Atur Password Portal"** (admin **mengetik** password di modal, `:110-115`); `:126-137` aksi **"Cabut Akses Portal"**; `:85-89` kolom ikon "Portal" |
| Dashboard | `app/Livewire/Portal/Dashboard.php:20-24` load `addresses.acUnits.latestOrderItem.order.teknisi`; `:27-32` fallback customer tanpa alamat; `:34` `ServiceReminder` terbaru. View `resources/views/livewire/portal/dashboard.blade.php` (catatan: view ada di `resources/views/livewire/portal/`, **bukan** `resources/views/portal/`) |
| Layout | `resources/views/layouts/portal.blade.php:17` lebar maks **480px** (gaya mobile), header + tombol Keluar; tidak ada navigasi antar-halaman |
| Test | `tests/Feature/CustomerPortalTest.php` — 15 kasus `it(...)` (`aturPassword` ×5, `cabutAkses`, login ×4, redirect tamu, dashboard ×2, logout, aksi admin) |
| Pola tautan publik bertoken | `app/Http/Controllers/ResiController.php:16-19` `hash_equals` atas `orders.resi_token`; route `routes/web.php:77` & `:80` (surat jalan). Token disimpan **plain** di kolom order |

### 2.2 Apa yang dashboard sekarang tampilkan

Per cabang (`customer_addresses.nama_lokasi`, view baris 38-42) → per unit (`kode_unit`,
`kode_ruangan`, `pk`, baris 49-54) → **satu** baris pengerjaan terakhir: nama layanan,
`tanggal_jadwal`, nama teknisi, status order (baris 58-66). Plus kartu "Jadwal Servis
Berikutnya" dari `ServiceReminder` (baris 10-20).

### 2.3 Apa yang belum ada

1. **Daftar order/riwayat** — hanya 1 pengerjaan terakhir per unit; order tanpa
   `customer_ac_unit_id` pada item-nya tidak terlihat sama sekali.
2. **Laporan foto + keterangan** — tidak ada satu pun foto atau keterangan yang ditampilkan.
3. **Invoice / tagihan / pembayaran** — tidak ada; `invoices` belum ada di kode
   (`Grep invoice app/` hanya menemukan `OrderResource.php` & `LaporanPengerjaanTab.php`, bukan
   model invoice; tidak ada migrasi `*invoice*`).
4. **Filter** cabang, periode/bulan, jenis pekerjaan — tidak ada.
5. **Multi-PIC** — 1 akun (email + password) per customer (`Customer.php:16-23`).
6. **Aktivasi oleh PIC sendiri / lupa password** — tidak ada; admin mengetik password lalu
   harus menyampaikannya ke customer di luar sistem (dok. 01 §4 poin 1, "Alur lupa-password
   self-service belum ada").
7. **Penyajian foto yang terlindungi** — foto laporan disimpan di disk `public`
   (`app/Livewire/Teknisi/OrderDetail.php:476-481`, folder `work-reports/`), artinya bisa
   dibuka siapa pun yang tahu URL `/storage/...`. Portal tidak boleh menambah paparan ini
   (§4.1).

### 2.4 Status ketergantungan dev-plan/21 (per 10 Okt 2026)

| Fase 21 | Status yang terlihat di kode | Dipakai portal untuk |
|---|---|---|
| 1–2 (jumlah unit, jasa/material) | sudah di commit `1b8076c` (dari snapshot git status awal sesi; git tidak dijalankan) | jumlah unit per order |
| 3 (dashboard omset) | sedang dikerjakan agent lain (berkas `OmsetService.php`, `Filament/Pages/Dashboard.php` belum di-commit) | tidak dipakai |
| 4 (keterangan per unit) | sedang dikerjakan: `app/Models/OrderUnitReport.php`, `app/Services/UnitReportService.php`, `WorkReportPhoto` sudah punya `unit_no` & `order_unit_report_id` (`WorkReportPhoto.php:17-18`), migrasi `2026_10_11_00000{1,2,3}` belum di-commit | **wajib** — keterangan per unit |
| 5 (preview + PDF laporan) | **belum ada**: tidak ada `LaporanPengerjaanService`, tidak ada library PDF di `composer.json` | **wajib** — preview & unduh PDF laporan |
| 6 (invoice) | **belum ada**: tidak ada model/tabel `invoices` | **wajib** — daftar & PDF invoice |

Kesimpulan: plan 22 Fase 22-A boleh berjalan paralel dengan Fase 5–6 karena hanya menyentuh
akses; **22-B menunggu Fase 4 & 5**, **22-C menunggu Fase 6**.

---

## 3. Gap analysis

| Kebutuhan user | Kondisi sekarang | Yang harus dibangun | Bergantung pada |
|---|---|---|---|
| "page khusus" customer perusahaan | Ada `/portal` (1 halaman), tidak dibedakan per `jenis` | Navigasi portal (Ringkasan · Pekerjaan · Unit · Invoice), layout lebih lebar untuk tabel | — |
| "pantau unit-unit yang telah dikerjakannya" | 1 pengerjaan terakhir per unit | Halaman **Unit**: riwayat per unit + status sudah/belum dikerjakan pada periode | 21 Fase 4 (`order_unit_reports.customer_ac_unit_id`) |
| "sesuai ... tanggal pengerjaan" | `tanggal_jadwal` hanya utk pengerjaan terakhir | Halaman **Pekerjaan**: daftar order + filter bulan/cabang/jenis | — (data order sudah ada) |
| "laporan tadi foto dan keterangan" | Tidak ada | Detail order: foto per unit + caption keterangan; unduh PDF laporan | 21 Fase 4 & 5 |
| "invoice tagihan dari tiap-tiap orderan" | Tidak ada | Halaman **Invoice**: daftar, saldo jatuh tempo, unduh PDF (+lampiran) | 21 Fase 6 |
| "sesuai invoice" (pantau unit per invoice) | Tidak ada | Detail invoice menampilkan order & unit yang ditagih | 21 Fase 6 (`invoice_orders`) |
| "hostingnya lengkap di sana" | Foto & PDF tidak ada di portal | Semua berkas disajikan lewat route terautentikasi portal | 21 Fase 5 & 6 |
| "username dan password" | Admin mengetik password; 1 akun per customer | Akun per PIC + tautan undangan sekali pakai (§5) | — |

---

## 4. Desain usulan

### 4.1 Halaman/route baru & penguncian data

Semua di grup `auth:customer` (nama guard dipertahankan agar `redirectGuestsTo` &
logout tidak berubah) + middleware baru `portal.aktif` (tolak bila akun PIC dinonaktifkan
atau customer `nonaktif` **di tengah sesi** — sekarang status hanya dicek saat login,
`Login.php:46`).

| Route | Komponen | Isi |
|---|---|---|
| `GET /portal` | `Portal\Dashboard` (diubah) | Ringkasan: jumlah order bulan ini, unit dikerjakan/total, invoice belum lunas + total saldo, jadwal berikutnya |
| `GET /portal/pekerjaan` | `Portal\Pekerjaan` (baru) | Daftar order + filter |
| `GET /portal/pekerjaan/{order}` | `Portal\PekerjaanDetail` (baru) | Detail order + laporan foto per unit |
| `GET /portal/pekerjaan/{order}/laporan.pdf` | `Portal\BerkasController@laporan` | Unduh PDF laporan (Fase 21-5) |
| `GET /portal/foto/{photo}` | `Portal\BerkasController@foto` | Stream foto laporan (versi resize) |
| `GET /portal/unit` | `Portal\Unit` (baru) | Rekap unit per cabang + riwayat |
| `GET /portal/invoice` | `Portal\Invoice` (baru) | Daftar invoice + saldo |
| `GET /portal/invoice/{invoice}` | `Portal\InvoiceDetail` (baru) | Rincian invoice + order yang ditagih |
| `GET /portal/invoice/{invoice}/pdf` | `Portal\BerkasController@invoice` | Unduh PDF invoice (+lampiran, Fase 21-6) |
| `GET /portal/aktivasi/{token}` | `Portal\Aktivasi` (baru, **di luar** `auth:customer`) | PIC mengatur password sendiri (§5) |

**Mekanisme penguncian (bukan filter di view):**

1. Satu kelas `App\Services\PortalAksesService` menjadi **satu-satunya** pintu query portal:
   `orderQuery(PortalUser $u): Builder` = `Order::where('customer_id', $u->customer_id)`
   + (bila PIC dibatasi cabang, §4.4) `whereIn('customer_address_id', $u->cabangIds())`;
   `invoiceQuery()` dan `unitQuery()` dengan pola sama. Komponen Livewire portal **dilarang**
   memanggil `Order::query()` langsung.
2. **Route model binding terkunci**: parameter `{order}`, `{invoice}`, `{photo}` di-resolve
   lewat `Route::bind(...)` / `resolveRouteBindingQuery` yang memakai query no. 1, sehingga
   ID milik customer lain menghasilkan **404** (bukan 403 — tidak membocorkan bahwa ID itu ada).
3. Properti Livewire yang menyimpan ID (mis. `$orderId`) diberi `#[Locked]` dan selalu
   di-resolve ulang lewat query no. 1 di `render()`/aksi.
4. **Foto & PDF tidak pernah diberi URL `/storage/...`** di portal. `BerkasController`
   memeriksa kepemilikan (foto → `work_report_photos.order_item_id` → `order_items.order_id`
   → query no. 1), lalu `response()->file()`/stream. URL publik `/storage/work-reports/...`
   yang sudah ada **di luar scope** plan ini (lihat §8 risiko R3).
5. Hanya order dengan status selain `batal` yang tampil; laporan foto hanya tampil bila
   laporan sudah diverifikasi admin (`work_reports.diverifikasi_pada`, `WorkReport.php:50-53`)
   — **usulan default, butuh keputusan §9-4**.

### 4.2 Apa yang tampil

**Pekerjaan (daftar)** — kolom: tanggal (`orders.tanggal_jadwal`), cabang
(`customer_addresses.nama_lokasi`), ringkasan layanan (`Order::ringkasanLayanan()`), jumlah
unit (`Order::jumlahUnit()`), teknisi/tim (`teknisi`, `timTeknisi`), status order, status
laporan (lengkap / belum), nomor invoice bila sudah ditagih. Urut tanggal terbaru.

**Pekerjaan (detail order)** —
- Header: tanggal, cabang + alamat, status, teknisi, nomor invoice (tautan).
- Per **unit** (`order_unit_reports`, urut `order_item_id, unit_no`): label unit
  (`kode_unit`/`kode_ruangan` bila tertaut `customer_ac_unit_id`), posisi, jenis pekerjaan,
  suhu, RPM, kondisi + catatan, galeri foto (`work_report_photos` per `order_unit_report_id`)
  dengan caption sama seperti PDF Fase 21-5.
- Tombol **Unduh PDF laporan** (memanggil `LaporanPengerjaanService` Fase 21-5 — portal
  **tidak** membuat renderer sendiri supaya isi portal = isi PDF = isi preview admin).
- Order lama tanpa `order_unit_reports`: tampil "Laporan rinci tidak tersedia untuk order ini"
  + foto lama per order (bila ada), mengikuti aturan "caption minimal" di plan 21 §8 risiko.
- Yang **tidak** ditampilkan ke customer: komponen jasa/material, koreksi total
  (`order_total_corrections`), biaya/insentif teknisi, catatan internal.

**Unit (rekap)** — per cabang, per `customer_ac_units`:
- Terakhir dikerjakan (tanggal + layanan + kondisi terakhir dari `order_unit_reports`).
- Status pada periode terpilih: **Sudah** (ada `order_unit_reports` pada order `selesai`
  dalam periode) / **Terjadwal** (ada order `terjadwal`/`menuju_lokasi`/`dikerjakan` yang
  mencakup unit) / **Belum**.
- Jadwal berikutnya: order terjadwal terdekat; bila tidak ada, `ServiceReminder` terbaru
  milik customer. Catatan: `ServiceReminder` masih **per customer**, bukan per unit (dok. 01
  §2 poin 3) — portal menampilkannya sebagai satu tanggal di tingkat customer, tidak
  mengarang tanggal per unit.
- Klik unit → riwayat semua pengerjaan unit tsb (tautan ke detail order).

**Invoice** — daftar invoice customer dengan status `terkirim` atau `lunas` (bukan `draft`/
`batal`): nomor (`INV-YYYYMM-0001`, plan 21 §9-8), tanggal, jatuh tempo, total, **saldo**
(total − pembayaran tercatat, angka dari `InvoiceService` Fase 21-6, bukan dihitung ulang di
portal), status, tanda "lewat jatuh tempo". Kartu ringkas di atas: total saldo terbuka.
Detail: baris invoice + daftar order yang ditagih (tautan ke detail pekerjaan → "pantau unit
sesuai invoice"), instruksi transfer dari `BusinessInfo`, tombol **Unduh PDF** (opsi lampiran
laporan sesuai Fase 21-6).

### 4.3 Filter

| Filter | Sumber | Berlaku di |
|---|---|---|
| Cabang | `customer_addresses` milik customer (`nama_lokasi`, fallback `labelTampil()`) — dibatasi cabang PIC | Pekerjaan, Unit, Invoice (invoice tampil bila salah satu order-nya di cabang tsb) |
| Periode (bulan; default bulan berjalan; opsi rentang tanggal) | `orders.tanggal_jadwal`; invoice: `invoices.tanggal` | Pekerjaan, Unit, Invoice |
| Jenis pekerjaan | `order_items.kategori` / `nama_layanan` | Pekerjaan |
| Status invoice | belum lunas / lunas | Invoice |

Filter disimpan di query string (`#[Url]` Livewire) agar tautan bisa dibagikan antar-PIC.
Layout portal diubah responsif: tetap nyaman di HP, tapi melebar di desktop untuk tabel
(sekarang dikunci 480px, `layouts/portal.blade.php:17`).

### 4.4 Model akses perusahaan banyak cabang/PIC — **REVISI keputusan 13 Sept**

**Keputusan lama** (dok. 01 §4 poin 1): 1 akun per customer, `customers.email` sebagai
username, `customers.password`.

**Masalah dengan keputusan lama untuk kebutuhan sekarang:**
- Customer seperti Circle K punya banyak cabang dan biasanya lebih dari satu orang yang
  memantau (pusat/finance + area/cabang). Satu password dibagi ramai-ramai → tidak tahu siapa
  yang login, dan **saat satu PIC keluar, password harus diganti dan semua PIC lain ikut
  kehilangan akses** sampai diberi password baru.
- Username = `customers.email` mengikat alamat email *perusahaan* (dipakai juga untuk data
  customer) ke satu orang.
- Tidak ada cara membatasi PIC cabang hanya melihat cabangnya.

**Usulan (REVISI):** akun portal **per PIC**, tabel baru `customer_portal_users`:

| kolom | isi |
|---|---|
| `customer_id` (FK) | perusahaan pemilik |
| `nama`, `jabatan` (null) | identitas PIC |
| `email` (null, unik bila terisi), `no_hp` (null, unik bila terisi, dinormalisasi `62...`) | identitas login; minimal salah satu wajib |
| `password` (null, hashed) | null = belum aktivasi |
| `semua_cabang` (bool, default true) | false → hanya cabang di pivot `customer_portal_user_addresses` |
| `aktif` (bool), `diaktivasi_pada`, `login_terakhir_pada` | status & jejak |
| `dibuat_oleh` (FK users) | admin pembuat |

- Model `App\Models\CustomerPortalUser implements Authenticatable`; provider `customers` di
  `config/auth.php:78-81` diganti ke model ini (nama guard `customer` tetap).
- **Migrasi data**: tiap customer yang `password`-nya terisi dibuatkan 1 baris
  `customer_portal_users` (`email` = `customers.email`, `password` disalin apa adanya — hash
  tetap valid, PIC tidak perlu ganti password, `semua_cabang` = true). Kolom
  `customers.password` dibiarkan dulu (tidak dipakai) dan dihapus di rilis berikutnya.
- Tidak ada batas jumlah PIC dari sisi sistem; batas (bila perlu) keputusan bisnis.
- Konsekuensi: login portal mencari di `customer_portal_users`, bukan `customers`; semua
  query portal berangkat dari `$pic->customer_id` (§4.1); test lama di
  `CustomerPortalTest.php` perlu disesuaikan (bukan dihapus).
- Pembatasan per cabang (`semua_cabang = false`) **dibangun di 22-A** karena murah selama
  semua query lewat `PortalAksesService`; invoice multi-order yang mencakup cabang lain
  hanya tampil untuk PIC `semua_cabang` (usulan default §9-3).

Alternatif mempertahankan 1 akun per customer tetap mungkin (cukup tambah undangan bertoken
ke `customers`), tetapi masalah PIC berganti tidak terselesaikan. **Rekomendasi: per PIC.**

---

## 5. Kredensial — jawaban atas pertanyaan user

### 5.1 Siapa membuat akun & di mana

- **Admin/Owner** (sama dengan `CustomerPortalService::assertRole` sekarang,
  `CustomerPortalService.php:22`), bukan registrasi mandiri.
- Tempat: `CustomerResource` → halaman View/Edit customer berjenis `company` → relation
  manager baru **"Akses Portal (PIC)"**: tabel PIC (nama, email/HP, cabang, status:
  *Belum aktivasi / Aktif / Nonaktif*, login terakhir) dengan aksi **Tambah PIC**,
  **Buat tautan undangan**, **Buat tautan reset password**, **Nonaktifkan**, **Aktifkan lagi**.
- Aksi tabel lama **"Atur Password Portal"** (`CustomerResource.php:102-124`, admin mengetik
  password) **dihapus**; **"Cabut Akses Portal"** (`:126-137`) diganti "Nonaktifkan semua
  PIC" (memanggil logika `cabutAkses()` versi baru).
- `CustomerPortalService` diperluas: `tambahPic()`, `buatUndangan()`, `aktivasi()`,
  `nonaktifkanPic()`, `cabutAkses()` (semua PIC customer). `aturPassword()` dihapus setelah
  data dimigrasi.

### 5.2 Cara PIC menerima akses — pembandingan singkat

| Opsi | Kelebihan | Kekurangan |
|---|---|---|
| (a) **Tautan undangan bertoken sekali pakai**, admin salin & kirim manual via WA | Password tidak pernah diketahui admin & tidak pernah lewat chat; tidak butuh layanan pihak ketiga; cocok dengan cara kerja sekarang (resi/surat jalan juga dikirim manual); dipakai ulang untuk reset password | Admin perlu 1 langkah salin-tempel; tautan bisa diteruskan orang lain sebelum dipakai (dimitigasi kedaluwarsa + sekali pakai + admin melihat status "sudah aktivasi") |
| (b) No HP + OTP | PIC tidak perlu ingat password | Butuh layanan SMS/WA API berbayar — **tidak ada integrasi WA/SMS di kode** (`Grep -i "wa\.me|whatsapp|fonnte|twilio" app/` hanya menemukan enum `LeadSource` & placeholder teks); tanpa itu OTP tidak bisa dikirim otomatis |
| (c) Magic-link tiap login | Tanpa password | Butuh pengiriman otomatis tiap login (email) — `MAIL_MAILER=log` di `.env` sekarang, jadi email tidak terkirim; tiap login bergantung pada kotak masuk |
| (d) Admin mengetik password awal lalu kirim via WA | Paling cepat dibuat (sudah ada) | **Paling lemah**: password tertulis di riwayat chat WA (admin & PIC, ikut backup), admin mengetahui password customer, PIC jarang menggantinya, dan saat PIC berganti password lama tetap beredar di chat |

**Rekomendasi: (a) tautan undangan bertoken sekali pakai.** Alasan: satu-satunya opsi yang
(1) tidak pernah mengirim password dalam bentuk teks, (2) bisa dijalankan **hari ini** tanpa
layanan SMS/WA/email, dan (3) mekanisme yang sama sekaligus menyelesaikan "lupa password"
(admin buat tautan reset). Username yang dipakai PIC untuk login berikutnya = **email atau
nomor HP** yang terdaftar di akun PIC-nya.

### 5.3 Alur (a) secara rinci

1. Admin **Tambah PIC** (nama, email dan/atau no HP, cabang).
2. Admin klik **Buat tautan undangan** → sistem membuat token acak 64 karakter
   (`Str::random(64)`), menyimpan **hanya hash SHA-256**-nya di tabel baru
   `customer_portal_tokens` (`customer_portal_user_id`, `jenis` undangan/reset, `token_hash`,
   `kedaluwarsa_pada` = +72 jam, `dipakai_pada`, `dibuat_oleh`). Token lama yang belum dipakai
   untuk PIC yang sama langsung dibatalkan.
3. Modal menampilkan tautan `https://<APP_URL>/portal/aktivasi/<token>` **sekali saja** + tombol
   **Salin** + tombol **Buka WA** (`https://wa.me/<no_hp>?text=...` berisi teks + tautan,
   tanpa password). Tautan tidak bisa ditampilkan ulang (yang tersimpan hanya hash) — kalau
   hilang, buat tautan baru.
4. PIC membuka tautan → halaman `Portal\Aktivasi`: menampilkan nama perusahaan + nama PIC +
   identitas login (email/HP), form password baru + konfirmasi (min. 8 karakter, aturan sama
   dengan `CustomerPortalService.php:28`).
5. Simpan → password di-hash, token ditandai `dipakai_pada`, `diaktivasi_pada` diisi, PIC
   langsung login dan diarahkan ke `/portal`.
6. Login berikutnya: `/portal/login` dengan **email atau no HP** + password.

**Lupa password**: PIC menghubungi admin → admin klik **Buat tautan reset password** (jenis
`reset`, berlaku 24 jam) → alur sama dengan langkah 3–5. Self-service via email ditunda sampai
mailer produksi dikonfigurasi (pertanyaan terbuka §8-b).

**PIC berganti**: admin **Nonaktifkan** PIC lama (akun tetap tersimpan untuk jejak; sesi
aktifnya ikut tertolak oleh middleware `portal.aktif`, §4.1) → **Tambah PIC** baru → kirim
undangan. PIC lain di perusahaan yang sama **tidak terdampak**. Bila perusahaan berhenti
berlangganan: "Nonaktifkan semua PIC" (`cabutAkses`).

**Email & no HP PIC kosong**: PIC tidak bisa dibuat (validasi: minimal salah satu wajib,
karena keduanya adalah username). Bila hanya email terisi: tombol **Buka WA** disembunyikan,
admin hanya bisa **Salin** tautan dan mengirim lewat kanal lain. Bila hanya no HP terisi:
login memakai no HP. `customers.email` / `customers.no_hp` perusahaan **tidak** dipakai
otomatis sebagai username PIC (bisa jadi nomor kantor/umum); admin boleh mengisikannya bila
memang PIC-nya.

### 5.4 Aturan keamanan (wajib)

1. Password **tidak pernah** dikirim/ditampilkan dalam bentuk teks oleh sistem atau admin;
   admin tidak punya form untuk mengetik password PIC.
2. Token undangan/reset: acak ≥ 64 karakter, disimpan **hash**-nya saja, dibandingkan dengan
   `hash_equals`, **sekali pakai**, **kedaluwarsa** (undangan 72 jam, reset 24 jam), batal
   otomatis bila dibuat token baru atau PIC dinonaktifkan. Token kedaluwarsa/terpakai →
   halaman "Tautan tidak berlaku, minta tautan baru ke admin" (tanpa membocorkan nama PIC).
3. **Rate limit**: `/portal/login` dan `/portal/aktivasi/{token}` dibatasi (mis. 5 percobaan
   per menit per kombinasi identitas+IP) — sekarang belum ada pembatasan sama sekali.
4. Sesi di-regenerate saat login & aktivasi (pola `Login.php:53`); PIC/customer nonaktif
   ditolak di setiap request (`portal.aktif`), bukan hanya saat login.
5. **Isolasi data**: semua query lewat `PortalAksesService` + binding terkunci (§4.1); berkas
   foto/PDF hanya lewat controller berotorisasi. Dibuktikan dengan test isolasi (§7): login
   sebagai PIC customer A, akses ID order/invoice/foto milik customer B → **404**; PIC cabang
   X akses order cabang Y milik customer yang sama → **404**; daftar pekerjaan/invoice A tidak
   memuat satu pun ID milik B.
6. Pesan error login tetap generik (pola `Login.php:41`) — tidak membedakan "akun tidak ada"
   dan "password salah".

---

## 6. Fase eksekusi

Urutan: **21-F4 → 21-F5 → 21-F6** adalah prasyarat; 22-A boleh mulai setelah plan 22 disetujui
(tidak bergantung pada fase 21), 22-B setelah 21-F4 & F5 selesai, 22-C setelah 21-F6 selesai.

### 22-A — Fondasi akses multi-PIC + undangan bertoken
- Migrasi: `create_customer_portal_users_table`, `create_customer_portal_user_addresses_table`,
  `create_customer_portal_tokens_table`, migrasi data `customers.password` →
  `customer_portal_users`.
- Berkas: `app/Models/CustomerPortalUser.php`, `app/Models/CustomerPortalToken.php`,
  `config/auth.php` (provider), `app/Services/CustomerPortalService.php`,
  `app/Services/PortalAksesService.php`, `app/Livewire/Portal/Login.php` (email/HP + throttle),
  `app/Livewire/Portal/Aktivasi.php` + view, middleware `PortalAktif`,
  `bootstrap/app.php` (alias middleware + rate limiter), `routes/web.php`,
  `app/Filament/Resources/CustomerResource.php` (hapus aksi lama),
  `app/Filament/Resources/CustomerResource/RelationManagers/PortalUsersRelationManager.php`,
  `resources/views/layouts/portal.blade.php` (navigasi + responsif).
- Kriteria selesai: customer portal lama tetap bisa login dengan password lama setelah
  migrasi; PIC baru bisa diundang → aktivasi → login; token sekali pakai & kedaluwarsa
  terbukti di test; PIC dinonaktifkan langsung keluar pada request berikutnya; semua test lama
  `CustomerPortalTest` lulus (setelah disesuaikan) + test baru 22-A lulus.

### 22-B — Pekerjaan + laporan foto + rekap unit (setelah 21-F4 & 21-F5)
- Migrasi: tidak ada.
- Berkas: `app/Livewire/Portal/Pekerjaan.php`, `PekerjaanDetail.php`, `Unit.php`,
  `Dashboard.php` (ringkasan), view masing-masing di `resources/views/livewire/portal/`,
  `app/Http/Controllers/Portal/BerkasController.php` (foto + PDF laporan),
  `PortalAksesService` (query order/unit/foto).
- Kriteria selesai: daftar & detail order tampil dengan filter cabang/bulan/jenis; foto
  disajikan lewat controller (tidak ada `/storage/` di HTML portal); PDF laporan yang diunduh
  dari portal identik sumbernya dengan preview admin (`LaporanPengerjaanService`); rekap unit
  menampilkan sudah/terjadwal/belum per periode; test isolasi 22-B lulus.

### 22-C — Invoice & saldo (setelah 21-F6)
- Migrasi: tidak ada (memakai `invoices`/`invoice_orders` dari 21-F6).
- Berkas: `app/Livewire/Portal/Invoice.php`, `InvoiceDetail.php` + view,
  `BerkasController@invoice`, `PortalAksesService::invoiceQuery()`, kartu saldo di Dashboard.
- Kriteria selesai: hanya invoice `terkirim`/`lunas` yang tampil; saldo = angka dari
  `InvoiceService`; unduh PDF invoice (+lampiran) berhasil; invoice multi-cabang mengikuti
  aturan §9-3; test isolasi 22-C lulus.

---

## 7. Berkas terdampak & test

### 7.1 Berkas (path lengkap, rencana)

Baru:
- `database/migrations/2026_xx_xx_create_customer_portal_users_table.php`
- `database/migrations/2026_xx_xx_create_customer_portal_user_addresses_table.php`
- `database/migrations/2026_xx_xx_create_customer_portal_tokens_table.php`
- `database/migrations/2026_xx_xx_pindah_password_customer_ke_portal_users.php`
- `app/Models/CustomerPortalUser.php`, `app/Models/CustomerPortalToken.php`
- `app/Services/PortalAksesService.php`
- `app/Http/Middleware/PortalAktif.php`
- `app/Http/Controllers/Portal/BerkasController.php`
- `app/Livewire/Portal/Aktivasi.php`, `Pekerjaan.php`, `PekerjaanDetail.php`, `Unit.php`,
  `Invoice.php`, `InvoiceDetail.php`
- `resources/views/livewire/portal/aktivasi.blade.php`, `pekerjaan.blade.php`,
  `pekerjaan-detail.blade.php`, `unit.blade.php`, `invoice.blade.php`,
  `invoice-detail.blade.php`
- `app/Filament/Resources/CustomerResource/RelationManagers/PortalUsersRelationManager.php`
- `database/factories/CustomerPortalUserFactory.php`

Diubah:
- `config/auth.php`, `bootstrap/app.php`, `routes/web.php`
- `app/Models/Customer.php` (relasi `portalUsers()`; `bisaLoginPortal()` disesuaikan)
- `app/Services/CustomerPortalService.php`
- `app/Livewire/Portal/Login.php`, `app/Livewire/Portal/Dashboard.php`
- `resources/views/livewire/portal/login.blade.php`, `dashboard.blade.php`,
  `resources/views/layouts/portal.blade.php`
- `app/Filament/Resources/CustomerResource.php`
- `tests/Feature/CustomerPortalTest.php`

### 7.2 Test (Pest, mengikuti `tests/Feature/CustomerPortalTest.php`)

`tests/Feature/PortalAksesPicTest.php` (22-A):
- migrasi data: customer dengan password lama → 1 PIC, bisa login dengan password lama;
- admin tambah PIC tanpa email & HP → ditolak; selain admin/owner → ditolak;
- buat undangan → token tersimpan sebagai hash (nilai plain tidak ada di DB);
- aktivasi dengan token valid → password tersimpan hashed, `dipakai_pada` terisi, login otomatis;
- token dipakai dua kali → ditolak; token kedaluwarsa (`travel`) → ditolak; token lama batal
  saat token baru dibuat;
- login dengan email dan dengan no HP; pesan error generik;
- rate limit login: percobaan ke-6 dalam 1 menit ditolak;
- PIC dinonaktifkan saat sesi aktif → request berikutnya diarahkan ke login;
- customer `nonaktif` → semua PIC-nya tertolak;
- menonaktifkan PIC A tidak memengaruhi PIC B di customer yang sama.

`tests/Feature/PortalIsolasiDataTest.php` (22-B, 22-C) — **test isolasi**:
- PIC customer A membuka `/portal/pekerjaan/{order milik B}` → 404;
- `/portal/pekerjaan/{order B}/laporan.pdf`, `/portal/foto/{foto order B}` → 404;
- `/portal/invoice/{invoice B}` dan `/pdf` → 404;
- invoice B berstatus `draft` pun tidak terlihat oleh B sendiri;
- daftar pekerjaan/invoice/unit A tidak memuat ID milik B (assert `assertDontSee` nomor/kode B);
- PIC A cabang X membuka order A cabang Y → 404; PIC A `semua_cabang` → 200;
- Livewire: mengubah properti ID secara manual ke milik B → ditolak (`#[Locked]`).

`tests/Feature/PortalPekerjaanTest.php` (22-B): filter bulan/cabang/jenis; detail order
menampilkan keterangan per unit & caption; order tanpa `order_unit_reports` tetap tampil;
HTML portal tidak mengandung `/storage/`; rekap unit sudah/terjadwal/belum.

`tests/Feature/PortalInvoiceTest.php` (22-C): hanya `terkirim`/`lunas`; saldo sama dengan
`InvoiceService`; tanda lewat jatuh tempo; unduh PDF menghasilkan `application/pdf`.

---

## 8. Risiko & pertanyaan terbuka

### (a) Sudah final (tidak dibuka ulang)
- Portal memakai guard `customer` terpisah & halaman login `/portal/login` (dok. 01 §4).
- Akses dikelola admin/owner, bukan registrasi mandiri (dok. 01 §4).
- Format & isi laporan PDF, nomor invoice `INV-YYYYMM-0001`, dompdf, pembatasan peran
  invoice (plan 21 §9-7, §9-8, §9-9, §9-10) — portal hanya menyajikan, tidak mengubah.
- Jalur foto `work_report_photos` (plan 21 §9-6).

### (b) Butuh keputusan user (dicatat 10 Okt 2026)
- Seluruh butir §9 di bawah.
- **Mailer produksi**: `.env` sekarang `MAIL_MAILER=log` — apakah nanti ada SMTP? Kalau ada,
  "lupa password" mandiri via email bisa ditambah; kalau tidak, reset tetap lewat admin.
- **Domain/URL portal** di produksi (nilai `APP_URL`) — tidak bisa dipastikan dari kode; tautan
  undangan memakai nilai itu.

### Risiko
- **R1 — Ketergantungan plan 21**: bila Fase 5/6 berubah bentuk (nama service/kolom), 22-B/C
  ikut menyesuaikan. Mitigasi: portal hanya memanggil `LaporanPengerjaanService` &
  `InvoiceService`, tidak membaca tabelnya secara mandiri untuk angka/isi PDF.
- **R2 — Data lama tanpa unit**: order sebelum Fase 4 tidak punya `order_unit_reports`; rekap
  unit bisa terlihat "Belum" padahal sudah dikerjakan. Mitigasi: fallback ke
  `order_items.customer_ac_unit_id` (sumber yang dipakai dashboard sekarang) untuk penanda
  "terakhir dikerjakan".
- **R3 — Foto di disk `public`**: file `work-reports/*` bisa diakses lewat `/storage/...` oleh
  siapa pun yang tahu path-nya. Portal tidak menampilkan path tsb, tetapi masalah dasarnya di
  luar plan 22. Belum diperiksa apakah nama file cukup acak untuk ditebak — pertanyaan terbuka.
- **R4 — Ukuran PDF**: PDF laporan bulanan bisa besar; portal memakai file hasil job Fase 21-5
  bila tersedia, bukan render ulang tiap klik.
- **R5 — Tautan undangan diteruskan** sebelum dipakai: dimitigasi kedaluwarsa 72 jam, sekali
  pakai, dan admin melihat siapa yang sudah aktivasi.
- **R6 — Test lama**: 15 test di `CustomerPortalTest.php` bergantung pada `customers.password`;
  perlu disesuaikan bersamaan dengan migrasi provider di 22-A.

---

## 9. § Keputusan yang diminta

1. **Akun per PIC (REVISI dok. 01) atau tetap 1 akun per perusahaan?** Usul: **per PIC**
   (`customer_portal_users`), karena PIC sering berganti dan password bersama ikut berganti
   untuk semua orang. — setuju/tidak
2. **Cara pemberian akses: tautan undangan bertoken sekali pakai yang admin kirim manual via
   WA**, PIC mengatur password sendiri; aksi lama "Atur Password Portal" dihapus. Usul: **ya**.
   — setuju/tidak
3. **PIC bisa dibatasi per cabang?** Usul: **ya, dibangun di 22-A**, default "semua cabang";
   invoice yang mencakup lebih dari satu cabang hanya terlihat oleh PIC "semua cabang".
   — setuju/tidak
4. **Laporan foto tampil di portal kapan?** Usul: **hanya setelah laporan diverifikasi admin**
   (`diverifikasi_pada` terisi), supaya foto/keterangan yang belum dicek tidak terlihat
   customer. — setuju/tidak
5. **Username PIC**: Usul: **email atau no HP** (minimal salah satu wajib). — setuju/tidak
6. **Masa berlaku tautan**: Usul: **undangan 72 jam, reset 24 jam**. — setuju/ubah angka
7. **Portal hanya untuk customer `jenis = company`?** Usul: **ya** — tombol "Akses Portal"
   hanya muncul untuk `company`; customer `perorangan` tetap memakai resi publik. Customer
   perorangan yang sudah punya password (bila ada) tetap dimigrasi, tidak diputus.
   — setuju/tidak
8. **Invoice yang tampil**: Usul: **hanya `terkirim` dan `lunas`**; `draft`/`batal` tidak.
   — setuju/tidak
9. **Urutan eksekusi**: Usul: **22-A boleh dikerjakan segera setelah disetujui** (paralel
   dengan 21-F5/F6); 22-B setelah 21-F4 & F5; 22-C setelah 21-F6. — setuju/tidak
