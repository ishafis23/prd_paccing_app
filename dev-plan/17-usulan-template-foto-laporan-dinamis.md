# Usulan — Template Foto Laporan Jadi Bisa Diatur Admin (bukan Hardcode)

> STATUS: **✅ SELESAI DIEKSEKUSI (18 September 2026)** — B61, B62, B63
> (foto wajib — direvisi sore hari yang sama: titik penegakan dipindah dari
> "blokir submit" jadi "blokir berangkat ke order berikutnya", supaya
> pembayaran tidak ikut tertahan), B64. 33 test Pest baru, suite penuh 601
> passed. Dicatat di `02-keputusan-eksekusi.md`.

## 1. Konsep Sekarang (jawaban atas "bagaimana konsep sekarang?")

Tiap baris layanan dalam order (`order_items`) punya **kategori**
(`ServiceType`: Cuci AC, Service AC, Pengadaan AC, Tambah Freon, Instalasi,
Relokasi, Bongkar — dev-plan/13 §3). Tiap kategori punya **daftar slot
foto** sendiri yang ditampilkan ke teknisi saat isi laporan.

**Masalahnya persis seperti yang client duga**: daftar slot itu sekarang
**hardcode** di `App\Support\FotoLaporanSlot::untuk()` — sebuah `match()`
PHP biasa (`app/Support/FotoLaporanSlot.php:18-55`). Contoh, Cuci AC
sekarang cuma 4 slot generik (`outdoor_proses`, `indoor_proses`,
`indoor_sebelum`, `indoor_sesudah_suhu`) — **beda** dari 6 item spesifik
yang client sebutkan barusan. Untuk ubah/tambah slot, developer harus edit
kode & deploy — **tidak bisa diatur admin sendiri**, persis keluhan client.

**Temuan penting (menentukan desain di bawah):**
- Slot yang ditampilkan **sekarang semuanya opsional** — validasi
  (`TeknisiService::validasiFotoKategori()`,
  `app/Services/TeknisiService.php:246-271`) cuma cek slot yang **dikirim**
  itu valid utk kategorinya; **tidak ada** yang benar-benar "wajib diisi
  sebelum submit". Ini perlu diputuskan ulang (lihat B63).
- Foto yang **sudah tersimpan** (`work_report_photos.slot`, string key
  mentah) ditampilkan ke admin dengan cara **auto-format dari key itu
  sendiri** (`str($slot)->headline()`,
  `app/Filament/Resources/OrderResource.php:339`) — **TIDAK** bergantung
  ke template lagi saat tampil. Artinya: mengubah/menonaktifkan slot nanti
  **aman**, tidak merusak tampilan laporan lama sama sekali.

## 2. Usulan: Template Jadi Data di Database, Dikelola Admin

Pindahkan `FotoLaporanSlot::untuk()` dari hardcode ke tabel baru yang bisa
dikelola admin — persis seperti yang client gambarkan: **data awal yang
dicentang aktif/tidak** + **admin bisa tambah item baru kapan saja**, tanpa
nunggu developer/deploy.

## 3. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B61 — Bentuk penyimpanan template
- **(a) Tabel baru `photo_report_templates`** — 1 baris = 1 slot foto (
  `kategori`, `kode_slot`, `label`, `urutan`, `aktif`). Relasional, gampang
  query per kategori, dan "tambah item baru" = tambah 1 baris (tidak perlu
  migrasi/skema baru tiap kali nambah).
- (b) Simpan sebagai JSON di 1 baris pengaturan (mirip
  `attendance_settings`) — lebih rumit diedit (harus utak-atik JSON di
  form), tidak alami utk data yang jumlahnya berubah-ubah (nambah/hapus
  baris) seperti ini.

Rekomendasi: **(a)**.

### B62 — Kode slot (`kode_slot`): auto atau manual, boleh diubah?
`kode_slot` adalah identitas permanen yang ikut tersimpan di
`work_report_photos.slot` tiap kali teknisi upload — kalau nanti diubah
diam-diam, makna riwayat lama jadi rancu.
- **(a)** `kode_slot` **di-generate otomatis** dari `label` (slug, mis.
  "Foto Cek Suhu (Indoor)" → `foto_cek_suhu_indoor`) saat admin bikin item
  baru, lalu **terkunci** (tidak bisa diedit lagi setelah dibuat — cuma
  `label`, `urutan`, `aktif` yang bisa diubah). Mau ganti kode? Nonaktifkan
  yang lama, buat baris baru.
- (b) Admin isi manual & boleh diubah kapan saja — lebih fleksibel tapi
  beresiko: kalau kode diubah setelah dipakai teknisi, laporan lama secara
  teknis masih aman tampil (lihat §1, auto-format dari string apa adanya),
  tapi maknanya bisa membingungkan (kode yang sama dipakai utk 2 hal beda).

Rekomendasi: **(a)** — otomatis & terkunci, hindari kebingungan riwayat.

### B63 — Foto wajib diisi sebelum submit, atau tetap sekadar panduan? — **✅ DIPUTUSKAN 18 Sep, DIREVISI 18 Sep sore**
Temuan §1: sekarang **tidak ada** yang benar-benar wajib.
- **(a) (dipilih) Tambah kolom `wajib`** per baris template.

**Revisi pada hari yang sama (setelah dites langsung):** desain awal
memblokir `submitLaporan()` itu sendiri kalau foto wajib kurang — ternyata
ini ikut menahan **"Bukti Pembayaran"**, karena bagian itu baru muncul
setelah order berstatus Selesai/ButuhFollowup. Teknisi jadi tidak bisa
lanjut ke pembayaran customer hanya karena 1 foto dokumentasi belum sempat
diambil. **Titik penegakan dipindah**, bukan dihapus:

- `submitLaporan()` **tidak lagi memblokir** — laporan selalu bisa
  disubmit, order tetap lanjut ke Selesai/ButuhFollowup, pembayaran &
  penutupan order **tidak pernah tertahan** oleh foto yang kurang.
- Sebagai gantinya: teknisi **tidak bisa berangkat ke order berikutnya**
  (`berangkat()`, slider "Mulai Berangkat") kalau masih ada order lain
  miliknya yang **belum ditutup** (`ditutup_pada IS NULL`) dan laporannya
  masih kurang foto wajib. Order yang **sudah ditutup** (B32) tidak lagi
  menahan — dianggap "cukup", supaya tidak mengunci teknisi selamanya
  gara-gara 1 foto lama yang mungkin sudah tidak bisa diambil ulang.
- Foto yang kurang bisa dilengkapi belakangan lewat bagian baru **"Lengkapi
  Foto Wajib"** di halaman order yang bersangkutan (muncul otomatis kalau
  masih ada yang kurang) — tanpa perlu submit ulang seluruh laporan.
- Form laporan tetap menampilkan penanda **"Wajib"** di tiap slot (§4) —
  cuma sekarang keterangannya "harus dilengkapi **sebelum order
  berikutnya**", bukan "sebelum submit".

Efek B63 lain (seed Cuci AC `wajib=true` semua, kategori lain `wajib=false`
dulu) **tidak berubah** — lihat §4.

### B64 — Isi awal (seed) utk kategori yang belum dikasih detail lengkap
Client baru kasih detail lengkap utk **Cuci AC** (6 item). 4 kategori lain
(Service AC, Instalasi, Bongkar-Pasang/Relokasi, Bongkar) disebut "beda
juga" tapi belum dirinci.
- **(a)** Cuci AC diisi persis daftar baru dari client (§4 tabel). Kategori
  lain **tetap pakai daftar lama** (yang sudah ada di kode sekarang) sbg
  titik awal — admin lalu **bebas ubah sendiri** kapan saja lewat menu
  baru ini, tidak perlu menunggu client lengkapi dulu sebelum fitur ini
  jalan (justru itu tujuan fiturnya: supaya tidak perlu nunggu developer).
- (b) Tunda eksekusi sampai client kasih daftar lengkap utk semua kategori.

Rekomendasi: **(a)** — fitur "bisa diatur sendiri" ini justru menghilangkan
kebutuhan menunggu; kategori lain tinggal diedit langsung oleh admin/client
begitu fitur ini jalan.

## 4. Desain Teknis (ringkas)

### Tabel baru
**`photo_report_templates`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| kategori | string | nilai `ServiceType` (`cuci_ac`, `service_ac`, dst) |
| kode_slot | string | slug dari label, unik per kategori, terkunci (B62) |
| label | string | teks yang dilihat teknisi, mis. "Foto Cek Suhu (Indoor)" |
| urutan | integer, default 0 | urutan tampil |
| wajib | boolean, default false | submit laporan ditolak kalau ini `true`+`aktif` tapi belum ada fotonya (B63) |
| aktif | boolean, default true | dicentang/tidak — inilah "data awal yg dicentang" yang client maksud |
| timestamps | | |
| unique(kategori, kode_slot) | | |

Data awal (diisi langsung di migrasi, langsung ada begitu `php artisan
migrate` — tidak perlu langkah seed terpisah). Kolom **Wajib**: Cuci AC
`true` semua (sesuai kata client "foto req"); kategori lain `false` dulu
(daftar lama, belum direview ulang — lihat B63 "konsekuensi desain"),
admin tinggal nyalakan toggle per item kapan sudah siap.

| Kategori | Slot (urutan tampil) | Wajib? |
|---|---|---|
| **Cuci AC** (baru, dari client) | Foto Tampak Depan Lokasi → Foto Sesudah Cuci (Indoor) → Foto Sesudah Cuci (Outdoor) → Foto Area Unit (Indoor) → Foto Area Unit (Outdoor) → Foto Cek Suhu (Indoor) | **Ya**, ke-6nya |
| Service AC (lama, tunggu diedit admin) | Kondisi Sebelum → Proses Service → Kondisi Sesudah | Tidak dulu |
| Tambah Freon (lama) | Tekanan Sebelum → Tekanan Sesudah | Tidak dulu |
| Instalasi (lama) | Lokasi Sebelum → Unit Terpasang → Testing Suhu | Tidak dulu |
| Relokasi / Bongkar-Pasang (lama) | Lokasi Asal → Lokasi Baru → Unit Terpasang | Tidak dulu |
| Bongkar (lama) | Sebelum Bongkar → Sesudah Bongkar | Tidak dulu |
| Pengadaan AC / kategori kosong | *(tidak ada baris — tetap pakai fallback umum "Sebelum"/"Sesudah" yang sudah ada di kode, bukan bagian yang diatur admin, TIDAK PERNAH wajib)* | — |

### Komponen baru
- **`App\Models\PhotoReportTemplate`** — fillable `kategori, kode_slot,
  label, urutan, wajib, aktif`; cast `kategori` → `ServiceType`,
  `wajib`/`aktif` → boolean.
- **`App\Services\PhotoReportTemplateService`**:
  - `untukKategori(?ServiceType $kategori): array` — query
    `aktif=true` orderBy `urutan`, balikin `[kode_slot => label]` (cache
    pendek per kategori, dilupakan tiap ada perubahan — pola sama seperti
    `AttendanceSettingService`). Kategori tanpa baris aktif (mis. Pengadaan
    AC) → balikin array kosong (biar `FotoLaporanSlot` yang isi fallback).
  - `daftarWajib(?ServiceType $kategori): array` — sama query tapi tambah
    `where wajib=true`, balikin `[kode_slot => label]` juga. Dipakai
    validasi submit **dan** penanda "Wajib" di form (dua-duanya butuh tahu
    kode_slot mana yang wajib).
  - `tambah(array $data, User $by): PhotoReportTemplate` — validasi
    label/kategori wajib, `kode_slot` di-generate dari `Str::slug(label,
    '_')`, tolak kalau slug itu sudah dipakai di kategori yg sama (pesan
    jelas: "sudah ada slot dengan nama serupa"). Role gate Admin/Owner.
  - `toggleAktif(PhotoReportTemplate $row, User $by): PhotoReportTemplate`,
    `perbarui(PhotoReportTemplate $row, array $data, User $by)` (cuma
    `label`/`urutan`/`wajib` yang bisa diubah, `kode_slot` diabaikan kalau
    dikirim — dikunci di level service juga, bukan cuma UI).
- **`FotoLaporanSlot` (existing, TIDAK pindah lokasi/nama)** —
  - `untuk()`: isinya diganti dari `match()` hardcode jadi delegasi ke
    `PhotoReportTemplateService::untukKategori()`; kalau hasilnya kosong,
    fallback ke `['sebelum' => 'Sebelum', 'sesudah' => 'Sesudah']`
    (perilaku lama utk kategori tanpa template, dipertahankan sbg jaring
    pengaman, **bukan** bagian yang diatur admin & **tidak pernah wajib**).
  - `wajibUntuk(?ServiceType $kategori): array` (baru) — delegasi ke
    `daftarWajib()`, dipakai `OrderDetail` (tampilkan penanda) &
    `TeknisiService` (hitung kekurangan, bukan lagi validasi keras).
- **`TeknisiService` (direvisi 18 Sep sore — lihat B63)**:
  - `submitLaporan()` **tidak lagi memvalidasi wajib** — selalu jalan
    seperti sebelum dev-plan/17, laporan & pembayaran tidak pernah
    tertahan foto.
  - `fotoWajibKurang(Order $order): array` (baru, publik) — cek ke
    `work_report_photos` (lintas seluruh laporan order itu, bukan cuma
    submit yang barusan), balikin daftar `[order_item, kode_slot, label]`
    yang masih kosong. Dipakai UI "Lengkapi Foto Wajib" & gate `berangkat()`.
  - `lengkapiFotoWajib(Order $order, User $teknisi, array $fotoKategori):
    WorkReport` (baru) — hanya utk order berstatus Selesai/ButuhFollowup;
    foto baru ditautkan ke laporan **terakhir** order itu (reuse
    `validasiFotoKategori()`/`catatFotoKategori()` yang sudah ada).
  - `berangkat()` — **tambah 1 gate** sebelum transisi status: kalau
    teknisi punya order lain (`untukTeknisi`, status Selesai/ButuhFollowup,
    **`ditutup_pada IS NULL`**) yang `fotoWajibKurang()`-nya tidak kosong,
    tolak dgn pesan sebut nomor order + nama customer yang perlu
    dilengkapi dulu. Order yang **sudah ditutup** (B32) tidak lagi
    dihitung — supaya tidak mengunci teknisi selamanya.
- **`OrderDetail.php`** — tambah `getFotoWajibKurangProperty()` (aktif
  hanya saat order Selesai/ButuhFollowup), property `$fotoLengkapi`, method
  `lengkapiFotoWajib()`; blade tambah 1 blok baru "Lengkapi Foto Wajib"
  (kuning, cuma tampil kalau masih ada yang kurang) — TIDAK mengubah
  struktur form laporan utama yang sudah ada.
- **Filament Resource baru** (menu **Customer & Order** → "Template Foto
  Laporan"): tabel dikelompokkan/filter per `kategori`, kolom `aktif` &
  `wajib` sama-sama pakai `ToggleColumn` (klik langsung di tabel, tanpa
  buka form), kolom `label`/`urutan`, tombol header **"Tambah Item"**
  (modal: pilih kategori, isi label, urutan, toggle wajib — `kode_slot`
  otomatis, tidak diminta dari admin). EditAction expose `label`/`urutan`/
  `wajib`. Role: Admin/Owner kelola, Finance
  lihat (konsisten pola resource lain).

### Migrasi
1 migrasi: buat tabel `photo_report_templates` + isi data awal (§4 tabel)
langsung di `up()` (pola sama seperti migrasi backfill multi-alamat
sebelumnya — data langsung ada begitu `php artisan migrate` dijalankan,
tidak perlu langkah `db:seed` terpisah yang bisa lupa dijalankan).

## 5. Alur Pengguna

**A. Admin kelola template:**
1. Buka menu "Template Foto Laporan" → lihat daftar per kategori, tiap
   baris ada toggle **Aktif** & toggle **Wajib** — klik langsung utk
   nyalakan/matikan tanpa buka form apa pun.
2. Klik "Tambah Item" → pilih kategori, isi label (mis. "Foto Nomor Seri
   Unit"), tentukan wajib atau tidak → simpan → langsung muncul jadi
   pilihan foto baru di form teknisi utk kategori itu, tanpa perlu deploy
   apa pun.

**B. Teknisi isi laporan:**
1. Buka form laporan → tiap baris layanan tampil daftar slot foto sesuai
   kategori & yang **aktif** saja — yang **wajib** ditandai jelas (badge
   "Wajib").
2. Upload foto sesuai kebutuhan → submit → **selalu berhasil** (laporan
   & pembayaran tidak pernah tertahan foto, direvisi 18 Sep sore, B63).
3. Kalau ada slot wajib yang masih kosong, muncul bagian kuning **"Lengkapi
   Foto Wajib"** di halaman order itu (Selesai/ButuhFollowup) — teknisi
   bisa isi belakangan tanpa submit ulang seluruh laporan.
4. Kalau teknisi coba **berangkat ke order berikutnya** sementara order lain
   miliknya (yang belum ditutup) masih punya foto wajib kurang → ditolak,
   pesan sebut order + customer mana yang perlu dilengkapi dulu.

## 6. Definisi "Selesai"

1. Menu "Template Foto Laporan" tampil utk Admin/Owner (kelola) & Finance
   (lihat), daftar dikelompokkan per kategori dgn toggle **aktif** &
   **wajib** langsung di tabel.
2. Admin bisa tambah item baru (kategori + label + wajib/tidak) kapan saja
   tanpa deploy; `kode_slot` otomatis dari label, tidak bisa diedit setelah
   dibuat.
3. Form laporan teknisi otomatis mengikuti perubahan (nonaktifkan/tambah
   item/ubah wajib langsung berefek ke form berikutnya).
4. Submit laporan **selalu berhasil** terlepas dari kelengkapan foto wajib
   (direvisi) — pembayaran & penutupan order tidak pernah tertahan.
5. Slot wajib yang masih kosong bisa dilengkapi lewat "Lengkapi Foto Wajib"
   pada order yang bersangkutan (Selesai/ButuhFollowup).
6. Teknisi **tidak bisa berangkat** ke order berikutnya selama ada order
   lain miliknya yang belum ditutup (`ditutup_pada` kosong) & foto wajibnya
   masih kurang; order yang **sudah ditutup** tidak lagi menahan.
7. Cuci AC terisi 6 item baru sesuai daftar client, semuanya **wajib**;
   kategori lain terisi daftar lama sbg titik awal (B64), **belum wajib**
   sampai admin nyalakan sendiri (B63).
8. Foto laporan **lama** (sebelum fitur ini) tetap tampil normal di halaman
   admin, tidak terpengaruh perubahan/nonaktifnya template (§1).
9. Test Pest hijau (33 test baru total, lihat §9): `PhotoReportTemplateService`
   lengkap, `FotoLaporanSlot` delegasi, `submitLaporan()` tidak lagi
   menolak, `fotoWajibKurang()`/`lengkapiFotoWajib()`/gate `berangkat()`
   (ditolak saat kurang, boleh setelah dilengkapi, boleh kalau order lama
   sudah ditutup), UI "Lengkapi Foto Wajib" end-to-end, regresi seluruh
   suite (601 passed) termasuk ~10 test lama yang disesuaikan (kategori
   default order-nya dipindah ke non-wajib supaya tetap fokus ke hal yang
   sebenarnya mereka uji).

## 7. Pertanyaan Terbuka (tidak menghalangi eksekusi)

1. Daftar lengkap utk **Service AC, Instalasi, Bongkar-Pasang/Relokasi,
   Bongkar** — kalau ada di catatan Ust/lapangan, boleh dikirim sekarang
   supaya langsung saya isi sbg data awal (bukan pakai daftar lama) — tapi
   **tidak wajib sekarang**, karena admin bisa edit sendiri kapan saja
   begitu fitur ini jalan (B64a). Termasuk: kalau mau kategori ini juga
   langsung **wajib** seperti Cuci AC, tinggal bilang, saya set dari awal.
2. Kategori "Pengadaan AC" sengaja tidak dapat baris template (dianggap
   tidak butuh checklist foto sedetail kategori lain, cukup fallback
   umum) — betul begitu, atau memang perlu daftar foto sendiri juga?

## 8. Batas & Non-Tujuan (versi ini)

- **Tidak mengubah struktur inti** `OrderDetail.php`/`TeknisiService.php` —
  perubahan berupa tambahan (badge "Wajib", blok "Lengkapi Foto Wajib",
  gate di `berangkat()`), bukan restrukturisasi alur upload/submit yang
  sudah ada (§4).
- Gate "tidak bisa berangkat" **cuma dicek per-teknisi**, bukan per-tim —
  kalau order dikerjakan berdua, anggota lain tim itu tidak ikut tertahan
  oleh laporan rekannya yang belum lengkap (masing-masing dicek atas
  order-order miliknya sendiri).
- **Tidak** menyentuh `work_report_photos` yang sudah ada — riwayat lama
  aman tanpa migrasi data apa pun (§1).
- **Tidak** membuat versi/riwayat perubahan template (mis. "siapa yang
  ubah label kapan") — kalau dibutuhkan audit trail, jadi usulan lanjutan.
- **Tidak** ada notifikasi proaktif (mis. WA) yang mengingatkan foto masih
  kurang — teknisi baru tahu saat mencoba berangkat ke order berikutnya.

## 9. Urutan Eksekusi — **✅ SELESAI DIEKSEKUSI (18 September 2026)**

1. **Fase A** — migrasi `photo_report_templates` + data awal (termasuk
   kolom wajib), `PhotoReportTemplate` model, `PhotoReportTemplateService`
   (`untukKategori`, `daftarWajib`, `tambah`, `toggleAktif`, `perbarui`),
   revisi `FotoLaporanSlot` (`untuk()` delegasi + `wajibUntuk()` baru),
   badge "Wajib" di form teknisi.
2. **Fase B** — Filament Resource "Template Foto Laporan" (tabel + toggle
   aktif & wajib inline + tambah item).
3. **Fase C (revisi sore hari yang sama, setelah dites langsung — B63)** —
   `submitLaporan()` tidak lagi memblokir; `TeknisiService::fotoWajibKurang()`
   + `lengkapiFotoWajib()` baru; gate di `berangkat()` (tolak kalau ada
   order lain belum ditutup & foto wajibnya kurang); UI "Lengkapi Foto
   Wajib" di `OrderDetail`.

33 test Pest baru (`PhotoReportTemplateTest.php`), ~10 test lama
disesuaikan (kategori default dipindah ke non-wajib di test yang bukan
soal foto), suite penuh **601 passed**.

Skala kecil-menengah, siap dieksekusi sekarang
— poin 2 & 3 tidak menghalangi (ada default aman di B64/§4).
