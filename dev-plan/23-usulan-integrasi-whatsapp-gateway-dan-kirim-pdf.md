# 23 — Usulan Integrasi WhatsApp Gateway Otomatis & Kirim PDF/Dokumen (adaptasi arm_app)

> STATUS: **🚧 SEBAGIAN DIEKSEKUSI — 23-A, 23-B, 23-C inti selesai (11 Okt 2026).**
> Keputusan §9 belum final (mis. penyedia gateway), sebagian memakai usulan default.
> **23-A** (device+scan/reset), **23-B** (kirim teks, Tes Kirim, Riwayat, Kirim Ulang),
> dan **23-C inti** (snapshot PDF + URL bertanda tangan + kirim dokumen invoice) sudah dibuat
> & diuji. Sisa: 23-D (reminder) + permukaan kirim laporan/resi. Lihat §10.
> Dokumen asli usulan tetap: keputusan §9 dikonfirmasi sebelum lanjut bagian sisa.
> Konsep diambil dari aplikasi lain milik user, **`arm_app`** (PHP native, ARM Dafi School,
> `D:\dafiapp\hosting_1\arm_app\arm_app`) yang sudah lebih dulu punya kirim WA otomatis +
> kirim surat PDF. Plan ini **memindahkan konsep itu ke crm_paccing (Laravel)**, bukan
> menyalin kodenya mentah-mentah.

Sumber permintaan (user, 11 Okt 2026, kutipan apa adanya):

> "pelajari bgmn fitur kirim pesan wa dan file pdf yg disini D:\dafiapp\hosting_1\arm_app\arm_app,
> ketika sudah dapat konsepnya buat md file pada crm_paccing plan ke 23"

---

## 1. Ringkasan & tujuan

Saat ini crm_paccing hanya bisa "mengarahkan" admin ke WhatsApp lewat **tautan `wa.me`**
(pesan disusun lalu dikirim manual dari HP admin) — tidak ada pengiriman otomatis, tidak ada
log, tidak ada bukti kirim. `arm_app` sudah memecahkan masalah yang sama dengan **gateway
WhatsApp wablasted.com**: satu nomor WA (device) tersambung lewat scan QR, lalu server
mengirim teks dan **dokumen PDF** langsung dari aplikasi.

Tujuan plan 23:

1. **Kirim WA otomatis** dari crm_paccing (teks ke satu nomor maupun massal), dengan nomor
   pengirim = device WA yang sudah discan QR.
2. **Kirim dokumen PDF** yang sudah dibuat crm_paccing (Invoice, Laporan Pengerjaan, Laporan
   Bulanan, Resi, Surat Jalan) sebagai lampiran WhatsApp.
3. **Manajemen device WA** (scan QR, status koneksi, assign ke admin) lewat panel Filament.
4. **Riwayat/audit semua pesan** di tabel `wa_logs` — setiap percobaan (sukses **maupun
   gagal**) tersimpan lengkap dengan isi pesan, nomor tujuan, dokumen, status, dan pesan
   error; mengganti tombol `wa.me` yang sekarang tidak meninggalkan catatan apa pun.
5. **Kirim ulang dari riwayat** — kalau kirim gagal, admin tahu dari log lalu mengirim ulang
   dengan sekali klik, **tanpa menyusun pesan atau generate PDF lagi** (payload + snapshot PDF
   sudah tersimpan di log).
6. **Preview sebelum kirim** untuk dokumen berfoto (Resi & Laporan Pengerjaan).
7. **Rate limit** agar tidak kena blokir WhatsApp.
8. Tetap **menyediakan `wa.me`** sebagai jalur cadangan (device tidak terhubung / nomor
   pengirim ingin dipakai HP pribadi).

Non-tujuan (di luar plan ini): balasan otomatis (webhook masuk), chatbot, integrasi ke nomor
customer tanpa persetujuan, blast promosi massal.

---

## 2. Konsep yang dipelajari dari `arm_app` (referensi eksternal)

> Semua path di bawah relatif ke `D:\dafiapp\hosting_1\arm_app\arm_app`. Ini **bahan bacaan**,
> bukan berkas yang diubah plan 23.

### 2.1 Arsitektur pengiriman WA (device → provider)

- **Model device.** Satu "device" = satu nomor WA yang tersambung. Relasi:
  `users.device_id → devices → provider_accounts.iduser`
  (`vendor/wablasted/WablastedService.php:20-38`). Identitas kirim yang dikirim ke provider
  adalah pasangan **`device` (device_name)** + **`iduser` (id akun provider)**
  (`WablastedService.php:77-86`).
- **Klien gateway** `WablastedService` (native cURL) memanggil endpoint induk:
  - `{base_url_qr}/api/qr/` → ambil QR / cek status (`getQr`, `:137-148`);
  - `{base_url_send}/api/send/` dengan `jenis=teks|gambar` (`sendText :150`, `sendImage :161`);
  - `{base_url_group}/api/group/` (`getGroups :349`);
  - dokumen — lihat §2.2.
  Base URL diambil dari **tabel `settings`**, fallback ke `.env` `WB_BASE_URL_*`
  (`WablastedService.php:50-75`).
- **Proxy + kendali** (`app/pages/wablasted/proxy.php`): semua AKSI kirim lewat sini dengan
  **`act=`** (`get-qr`, `send-teks`, `send-gambar`, `send-massal`, `cek-grup`,
  `history-mark`, `reset-session`, `:80-86`):
  - `device_id` **selalu dari sesi user**, bukan input klien → anti-IDOR (`:90-101`);
  - daftar aksi dibatasi whitelist (`:80-86`).
- **Manajemen device** (`app/pages/wablasted/device.php`): CRUD device + **Assign / Lepas
  device ke user** (`:12-139`), tabel device menampilkan kolom `connection_status`
  (`:171-195`).

### 2.2 Alur kirim DOKUMEN PDF (yang benar-benar bekerja)

Kunci pengalaman arm_app (`docs/PLAN_05_KIRIM_SURAT_PDF_API_DOCUMENT.md`): endpoint provider
lama `{base}/api/send-document/` (multipart upload `CURLFile`) **sudah tidak bekerja**. Yang
bekerja adalah **bridge `api_document.php`** di domain sendiri, format **JSON + URL publik
dokumen**:

```
POST https://<domain>/apiwablasted/api_document.php
Authorization: Bearer <WB_DOCUMENT_TOKEN>
Content-Type: application/json
{ "iduser", "device", "target", "pesan"(caption), "jenis":"document",
  "dokumen":<URL absolut PDF>, "namafile" }
```

Di `WablastedService::sendDocumentBridge()` (`:264-296`). Alur lengkap di
`app/pages/surat/generate.php`:

1. Render PDF (Dompdf) lalu **simpan file** ke `pdf_storage/` (`:195-202`).
2. Bangun **URL publik bertanda tangan HMAC** untuk file itu
   (`:204-207`): `file.php?file=<nama>&sig=hash_hmac('sha256', <nama>, APP_PDF_SECRET)`.
3. Jadikan absolut dengan `APP_BASE_URL` (`:249-253`) → inilah `dokumen` yang diberikan ke
   bridge (server wablasted **mengunduh sendiri** dari URL itu).
4. `sendDocumentBridge($nomorWA, $pubUrlAbsolut, $caption, $namafile)` (`:263`); `namafile`
   = `surat-tagihan-<nama>.pdf` agar nama file di WA rapi (`:260-261`).
5. Catat ke `wb_log` (`action='send-document'`, `jenis_kirim='surat'`) (`:288-316`).

Endpoint publik PDF `app/pages/surat/file.php` sengaja **tanpa login** hanya karena server
gateway harus bisa mengunduh; keamanannya dari **HMAC** + validasi `basename` + cek
`realpath` di dalam `pdf_storage/` (`file.php:15-47`). Versi untuk admin tetap ada dan
**berlogin** (`download.php`).

### 2.3 Kendali mutu (yang wajib ditiru)

- **Pre-flight live check SEBELUM kirim**: proxy memanggil `get-qr` satu kali; device
  dianggap terhubung hanya bila "HTTP sukses **dan tidak ada gambar QR**", lalu status DB
  (`devices.connection_status`) disinkronkan (`proxy.php:244-278`). Kolom status di DB saja
  tidak dipercaya karena bisa basi.
- **Rate limit** kirim per menit (default 10) dihitung dari tabel `wb_log`
  (`proxy.php:104-117`, env `WB_RATE_LIMIT_SEND`).
- **Audit log** tiap kirim ke tabel `wb_log` (user_id, device_id, action, target, va_bsi,
  unit, jenis_kirim, request_payload, response_payload, status, created_at)
  (`proxy.php:392-396` & `:472-491`), termasuk **snapshot** nominal saat kirim untuk
  perbandingan reminder ke-1/2/3.
- **Reset session** (`reset-session`, `proxy.php:184-242`) untuk logout/scan ulang bila QR
  basi — sumber kebenaran tetap hasil `get-qr`, bukan kode HTTP respons reset.

### 2.4 Template pesan WA

`assets/js/pages/wa-template.js`: satu fungsi `templateWa(m, bulan, custom, adminWa)`
(`:104-133`) menyusun narasi lengkap (salam, nama anak, rincian tagihan, tautan portal,
penutup). Caption kirim dokumen di `generate.php:233-242` memakai gaya yang sama. Konsep:
**template adalah satu-satunya tempat teks disusun**, dipakai berulang di beberapa halaman
(perlu `?v=N` cache-bust saat diubah).

---

## 3. Kondisi sekarang di crm_paccing (dengan bukti)

### 3.1 Yang sudah ada

| Bagian | Bukti |
|---|---|
| PDF via dompdf (bukan LibreOffice) | `composer.json` `barryvdh/laravel-dompdf ^3.1`; `app/Services/InvoicePdfService.php:9`; `app/Services/LaporanPdfService.php:9` |
| Halaman PDF invoice publik bertoken | route `invoice.publik` / `invoice.publik.pdf` (`routes/web.php:126-127`); `app/Http/Controllers/InvoiceController.php:14` |
| Unduh PDF invoice untuk admin | route `invoice.pdf` (`routes/web.php:125`); aksi "Unduh PDF" `app/Filament/Resources/InvoiceResource/Pages/ViewInvoice.php:83-97` |
| Laporan pengerjaan preview + PDF | `routes/web.php:117-121`; `LaporanController`; view bersama `resources/views/laporan/_dokumen.blade.php:3-4` |
| Resi & Surat Jalan publik bertoken | `routes/web.php:85-88`; `ResiController.php:16-19` (pola `hash_equals` atas `orders.resi_token`); `SuratJalanController.php:12` |
| Info usaha (kontak, bank, logo) | `app/Models/BusinessInfo.php:17-28`; dikelola menu **Info Usaha** `app/Filament/Pages/KelolaInfoUsaha.php` |
| Pola halaman pengaturan Filament | `app/Filament/Pages/PengaturanBeranda.php`, `PengaturanAbsensi.php`, `KelolaInfoUsaha.php` |
| Queue tersedia | `composer.json` script `dev` memakai `php artisan queue:listen` |

### 3.2 Cara "kirim WA" sekarang — hanya tautan manual

- `InvoiceService::urlWa()` menyusun teks + membuat **`https://wa.me/<hp>?text=...`**
  (`app/Services/InvoiceService.php:374-391`) — **tanpa API, tanpa log, tanpa bukti kirim**.
- Dipakai aksi Filament **"Kirim via WA"** (`ViewInvoice.php:99-105`) dan tombol WA di
  `resources/views/livewire/teknisi/order-detail.blade.php:112`.
- Test yang ada hanya memverifikasi format `wa.me/...` (`tests/Feature/InvoiceTest.php:385-392`,
  `tests/Feature/LandingPageTest.php:39`, `tests/Feature/BusinessInfoTest.php:179`).

### 3.3 Yang belum ada sama sekali

1. **Tidak ada gateway/API WA** — `Grep -i "fonnte|wablasted|twilio"` → kosong.
2. **Tidak ada device/session WA**, tidak ada scan QR, tidak ada status koneksi.
3. **Tidak ada log kirim** (tabel `wa_logs`/sejenis tidak ada).
4. **Tidak ada rate limit kirim.**
5. **Tidak ada URL PDF publik generik bertanda tangan** khusus untuk dikonsumsi server
   gateway: jalur publik yang ada (`invoice.publik.pdf`, resi) memakai token acak di kolom DB
   — bisa dipakai, tetapi laporan (`laporan.pdf`) sekarang **wajib login** (`routes/web.php:119`)
   sehingga gateway tidak bisa mengunduhnya. Belum ada mekanisme tanda tangan URL generik.
6. **Tidak ada template WA server-side** yang bisa dipakai ulang lintas (invoice/laporan/
   reminder) — teks disusun ad-hoc di `urlWa()`.

---

## 4. Gap analysis

| Kebutuhan (dari arm_app) | Padanan di crm_paccing | Yang harus dibangun |
|---|---|---|
| Device per user (`users.device_id`) | tidak ada | Tabel `wa_devices` + FK di `users` + assign user |
| Klien gateway (`WablastedService`) | tidak ada | `App\Services\WhatsappService` (port ke Laravel + config) |
| Proxy aksi (`?act=`) | tidak ada | `WhatsappController` (route `/admin/wa/*`) dengan whitelist aksi |
| Scan QR + status koneksi | tidak ada | Aksi `scanQr`/`status` + halaman Filament "Perangkat WhatsApp" |
| Kirim teks & massal | hanya `wa.me` | `sendText` + `sendMassal` (queue) + penanda log |
| Kirim dokumen PDF | hanya unduh manual | `sendDocument` (bridge JSON+URL) + **URL PDF publik bertanda tangan HMAC** |
| URL PDF untuk gateway | publik bertoken terbatas (`invoice.publik`) | `PublicBerkasController` + route `berkas.publik` bertanda tangan + berlaku laporan |
| Log audit kirim | tidak ada | Tabel `wa_logs` + resource Filament riwayat |
| Rate limit | tidak ada | Config `wa.rate_limit` + cek dalam 60 detik terakhir |
| Template pesan | teks ad-hoc di `urlWa()` | `App\Support\WhatsappTemplate` (+ view, mis. `resources/views/wa/*`) |
| Konfigurasi base URL & token | tidak ada | `config/wa.php` + `.env` (+ halaman pengaturan non-rahasia) |
| Halaman scan/aneka device untuk admin | tidak ada | Filament Page + (opsional) Livewire QR |

---

## 5. Desain usulan (adaptasi ke Laravel)

### 5.1 Skema data (3 tabel + 1 kolom)

Mengikuti pola arm_app tetapi disesuaikan konvensi crm_paccing (FK Eloquent, `timestamps`).

**`provider_accounts`** (akun gateway; bisa lebih dari satu)

| kolom | isi |
|---|---|
| `id`, `label`, `iduser` (string, unik) | identitas akun gateway |
| `base_url_qr`, `base_url_send`, `base_url_group`, `base_url_document` (nullable) | override per akun; null = pakai `config('wa.*')` |
| `timestamps` | |

**`wa_devices`** (satu nomor WA)

| kolom | isi |
|---|---|
| `id`, `provider_account_id` (FK), `device_name` (unik), `nomor_wa` (nullable) | |
| `status` enum `tersedia\|terpakai\|terputus` | diturunkan dari arm_app `devices.status` |
| `connection_status` enum `terhubung\|belum_terhubung` (default `belum_terhubung`) | sinkron dari pre-flight `get-qr` |
| `assigned_user_id` (FK users, nullable), `last_checked_at` | |
| `timestamps` | |

**`users.wa_device_id`** (nullable FK) — device yang dipakai user saat kirim (setara
`users.device_id` arm_app). Assign di kedua sisi dijaga konsisten oleh `WhatsappService`
(seperti `device.php:86-139`).

**`wa_logs`** (audit **+ sumber untuk kirim ulang**)

> Inilah perubahan penting: log bukan sekadar catatan, tapi **cukup data untuk mengirim ulang
> tanpa menyusun pesan / render PDF lagi**. Pesan final, nomor tujuan, template, dan lokasi
> snapshot PDF semua disimpan di baris log. Tombol "Kirim Ulang" cukup memutar ulang isi log.

| kolom | isi |
|---|---|
| `id`, `user_id` (FK), `wa_device_id` (FK, nullable) | siapa & device mana saat kirim |
| `action` string (`send-teks`\|`send-document`\|`send-massal`) | aksi yang dipakai |
| `target` string | nomor tujuan (sudah dinormalisasi `62…`) |
| `jenis_kirim` (`teks`\|`invoice`\|`laporan`\|`laporan-bulanan`\|`resi`\|`surat-jalan`\|`reminder`\|`tes`) | |
| **`pesan` text (nullable)** | **teks/caption final yang benar-benar dikirim** (sudah jadi, tidak perlu disusun ulang) |
| **`dokumen_path` (nullable)** | **path snapshot PDF tersimpan** (mis. `wa-dokumen/2026/10/xxxx.pdf`) — dipakai untuk kirim ulang & unduh |
| **`dokumen_namafile` (nullable)** | nama file tampil di WA (mis. `invoice-INV-202610-0001.pdf`) |
| `dokumen_url` (nullable) | URL publik bertanda tangan yang dipakai saat itu (jejak) |
| `related_type`, `related_id` (nullable, morph ke `Invoice`/`Order`/`LaporanBulanan`) | jejak dokumen sumber |
| **`status`** (`sukses`\|`gagal`), **`error_message` text (nullable)** | hasil; `error_message` = alasan gagal agar terlihat di riwayat |
| **`dikirim_pada`** (nullable), **`percobaan`** (int, default 1), **`log_asal_id`** (FK `wa_logs`, nullable) | waktu sukses & jejak kirim ulang (siapa mengirim ulang dari log mana) |
| `request_payload` json, `response_payload` json, `created_at` | respons mentah provider (untuk diagnosa) |

Catatan:
- Untuk `send-massal`, satu baris = satu target (seperti arm_app `wb_log`), jadi tiap nomor
  punya status & bisa dikirim ulang satu-satu.
- Snapshot PDF disimpan ke **`storage/app/wa-dokumen/`** (disk `local`, gitignored). Sekali
  render saat kirim pertama; kirim ulang memakai file yang sama (tidak render ulang).
- Retensi: command pembersih berkala (mis. simpan 60 hari) — keputusan di §9-15.

**Konfigurasi** (`config/wa.php` + `.env`, rahasia JANGAN di DB):

```php
// config/wa.php
return [
    'base_url_send'     => env('WA_BASE_URL_SEND'),
    'base_url_qr'       => env('WA_BASE_URL_QR'),
    'base_url_group'    => env('WA_BASE_URL_GROUP'),
    'base_url_document' => env('WA_BASE_URL_DOCUMENT'), // full URL .php = bridge
    'document_token'    => env('WA_DOCUMENT_TOKEN'),
    'rate_limit'        => (int) env('WA_RATE_LIMIT_SEND', 10),
    'pdf_secret'        => env('WA_PDF_SECRET'),         // HMAC URL lampiran
    'aktif'             => (bool) env('WA_AKTIF', false), // kill-switch
];
```

### 5.2 Layanan WA (`App\Services\WhatsappService`)

Port `WablastedService` ke Laravel (pakai `Http`/`Guzzle` cURL):

- `__construct(ProviderAccount $akun)` atau resolusi dari device.
- `getQr(WaDevice): array` → `{ ok, has_qr, image, message }`.
- `status(WaDevice): bool` → **aturan pre-flight arm_app**: `ok && !has_qr` = terhubung;
  sekaligus update `connection_status` + `last_checked_at`.
- `sendText(WaDevice, string $target, string $pesan): array`.
- `sendDocument(WaDevice, string $target, string $urlAbsolut, string $caption, string $namafile): array`
  → bridge JSON + `Authorization: Bearer config('wa.document_token')` (pola
  `sendDocumentBridge` arm_app).
- `resetSession(WaDevice): array`.
- `testKoneksi()` untuk tombol "Tes" di panel.
- Normalisasi nomor & validasi (`preg_replace('/\D/','')`, `0…`→`62…`, min 10 digit) —
  setara `normWa()` di `wa-template.js:20-25` & `generate.php:72-81`.

Aturan internal: setiap panggilan **wajib** melewati rate-limit check + tulis `wa_logs`
(kecuali `get-qr`/`status` yang tidak dicatat sebagai kirim), dan **wajib** pre-flight
`status()` sebelum `send*`.

**Penyimpanan payload & kirim ulang (inti permintaan user):**
- Satu helper `catat(WaLog $log, array $respons)` menulis `status`, `error_message`,
  `response_payload`, dan `dikirim_pada` **selalu** (sukses maupun gagal).
- `sendTeks(...)` menyimpan `pesan` final ke log; `sendDokumen(...)` **merender PDF sekali,
  menyimpannya ke `dokumen_path`, menyimpan `pesan`(caption) + `dokumen_namafile`**, baru
  mengirim. Karena isi final ada di log, pengiriman berikutnya tidak perlu menyusun pesan
  atau render PDF lagi.
- `kirimUlang(WaLog $log): WaLog` — **hanya** memutar ulang dari baris log:
  - `send-teks` → kirim `$log->pesan` ke `$log->target`;
  - `send-document` → kirim `$log->dokumen_path` + `$log->pesan` + `$log->dokumen_namafile`
    ke `$log->target`;
  - membuat **baris log baru** dengan `log_asal_id = $log->id` dan `percobaan = $log->percobaan + 1`
    (log lama tetap utuh sebagai riwayat), lalu menjalankan pre-flight + rate-limit normal.
  - **Tidak** memanggil `InvoicePdfService`/`LaporanPdfService`/`WhatsappTemplate` sama sekali.
- `kirimUlang()` boleh dipakai baik untuk log **gagal** ("coba lagi") maupun **sukses**
  ("kirim ke orang yang salah / nomor baru"), dengan nomor tujuan dapat diganti opsional.

### 5.3 Route & controller aksi

Semua di grup admin/owner (guard `web` + izin role). Contoh:

| Route | Aksi |
|---|---|
| `POST /admin/wa/qr` | ambil QR device |
| `POST /admin/wa/status` | cek status koneksi |
| `POST /admin/wa/send-teks` | kirim 1 pesan teks |
| `POST /admin/wa/send-dokumen` | kirim 1 dokumen PDF (dari jenis+id) |
| `POST /admin/wa/send-massal` | kirim massal (queue) |
| **`POST /admin/wa/kirim-ulang/{waLog}`** | **kirim ulang dari baris log (tanpa render ulang)** |
| `POST /admin/wa/reset` | reset session (scan ulang) |
| `GET /admin/wa/riwayat` | daftar `wa_logs` (Filament Resource) |

`WhatsappController::sendDokumen()` menerima `{ jenis, id, target?, caption? }`; server
merender PDF **sekali**, menyimpannya sebagai snapshot (`dokumen_path`), membangun URL publik
bertanda tangan (§5.4) + nama file rapi, memanggil `WhatsappService::sendDocument`, lalu
mencatat `wa_logs` (termasuk `pesan`, `dokumen_path`, `dokumen_namafile`,
`related_type/related_id`, `status`, `error_message`).

`WhatsappController::kirimUlang(WaLog $log, Request $r)` meneruskan ke
`WhatsappService::kirimUlang()` (opsional `target` baru). Hanya baris log yang bisa dikirim
ulang (log dari aksi lain ditolak).

**Anti-IDOR**: device diambil dari `auth()->user()->wa_device_id` (bukan input klien);
`target` dinormalisasi & dibatasi panjangnya.

### 5.4 URL PDF publik bertanda tangan HMAC (bagian paling kritis)

**Keputusan (mengikuti permintaan user "tidak perlu generate PDF ulang"): PDF dirender sekali
lalu DISIMPAN sebagai snapshot, dan URL publik bertanda tangan menunjuk ke file snapshot itu**
— persis pola arm_app (`pdf_storage/` + `surat/file.php`), bukan render on-the-fly.

- **Snapshot disimpan** ke `storage/app/wa-dokumen/<Y>/<m>/<hash>.pdf`; jalur & nama tampil
  dicatat di `wa_logs.dokumen_path` / `dokumen_namafile` (lihat §5.1).
- `App\Http\Controllers\PublicBerkasController` **hanya menyajikan file snapshot** (stream
  `application/pdf`), **tidak** memanggil dompdf. Sumber file = `wa_logs` atau direktori
  snapshot; keamanan:
  - `sig = hash_hmac('sha256', "{$token}:{$exp}", config('wa.pdf_secret'))`, `exp` mis. +24 jam;
    dibandingkan `hash_equals`; `token` = token acak per snapshot (bukan path mentah, agar
    tidak path-traversal — tetap divalidasi `basename` + `realpath` di dalam disk, pola
    `file.php:24-47`);
  - `WA_PDF_SECRET` wajib ada, jika kosong → 500;
  - file yang tidak ada / kedaluwarsa → 404.
- Route: `GET /berkas/{token}/{sig}` `nama: berkas.publik` (mis. juga
  `GET /berkas/{token}/{sig}?unduh=1`).
- **Kirim ulang** memakai `dokumen_path` yang sama → URL snapshot yang sama → **tidak ada
  render dompdf lagi**, jadi gagal-kirim bisa dicoba lagi kapan saja tanpa biaya render.
- Snapshot boleh dibuat saat **preview** (§5.7) juga, sehingga preview dan kirim memakai file
  yang sama persis.
- UI admin tetap memakai route **berlogin** yang sudah ada (`invoice.pdf`, `laporan.pdf`).

### 5.5 Menu admin "WhatsApp" + halaman Perangkat & Tes Kirim (Filament)

Ditempatkan di **navigation group baru "WhatsApp"** (satu grup), ikon
`heroicon-o-chat-bubble-left-right`, izin Owner/Admin (pola `navigationGroup` yang sudah
dipakai repo, mis. `PengaturanAbsensi.php:21`). Isi grup:

| Item | Kelas | Isi |
|---|---|---|
| **Perangkat & Tes WA** | `app/Filament/Pages/WhatsappDevice.php` (+ view blade) | Kelola device, **Sambungkan (scan QR/barcode)**, **Putuskan/Reset**, **Cek Status**, dan **Tes Kirim** (teks & PDF) |
| **Riwayat Kirim WA** | `app/Filament/Resources/WhatsappLogResource.php` | Daftar **semua** `wa_logs` (sukses & gagal) + filter tanggal/status/jenis, pratinjau pesan/PDF, tombol **Kirim Ulang** & **Unduh PDF** |
| **Pengaturan WhatsApp** (opsional) | page gaya `KelolaInfoUsaha` | kill-switch `aktif`, `rate_limit`, teks default template (token/secret tetap di `.env`) |

**Halaman "Riwayat Kirim WA" — semua pesan tersimpan & bisa dikirim ulang:**

1. Menampilkan **semua** percobaan kirim (bukan hanya yang sukses): waktu, pengguna, target,
   jenis, **status** (sukses/gagal), **error** (bila gagal), `percobaan` ke-berapa, device.
2. **Kolom aksi per baris:**
   - **Kirim Ulang** — hanya muncul untuk baris yang punya `pesan` (dan untuk dokumen, punya
     `dokumen_path`). Memakai `WhatsappService::kirimUlang()`: **tanpa** render PDF / susun
     pesan ulang. Bisa dipakai untuk baris **gagal** (coba lagi) maupun **sukses** (kirim ke
     nomor lain/salah orang) — nomor tujuan boleh diganti di modal kecil.
   - **Unduh PDF** — buka `dokumen_path` (snapshot) untuk verifikasi.
   - **Lihat** — pratinjau pesan final + tautan `related_type/related_id` (buka Invoice/Order).
3. Filter cepat: **Gagal** (default perhatian), tanggal, jenis, pengguna; badge jumlah gagal.
4. Setelah kirim ulang, baris **baru** muncul dengan `log_asal_id` menunjuk baris lama; baris
   lama tetap ada sebagai riwayat (tidak ditimpa), sehingga jejak "dikirim ulang dari mana"
   terlihat.
5. Snapshot PDF tidak perlu di-generate ulang, sehingga **kirim ulang cepat & hemat**.

**Halaman "Perangkat & Tes WA" — rincian (ini yang diminta user):**

1. **Tabel device**: nama, nomor WA, provider, status (`tersedia/terpakai/terputus`),
   **koneksi** (`terhubung/belum_terhubung`), pemakai. Tombol: Tambah Device,
   **Assign ke user**, **Lepas** (logika `device.php:12-139`).
2. **Sambungkan WA (barcode)** — tombol **"Ambil QR"** pada device terpilih → modal
   menampilkan gambar QR hasil `POST /admin/wa/qr` (auto-refresh tiap ±20 detik sampai
   terhubung; saat terhubung modal menutup & badge jadi **terhubung**). Status ditentukan
   aturan pre-flight arm_app: `ok && !has_qr` (`proxy.php:261-278`).
3. **Putuskan / Reset** — tombol **"Putuskan & Scan Ulang"** → `POST /admin/wa/reset`
   (`reset-session`); mengembalikan device ke `belum_terhubung` lalu QR baru bisa diambil
   (sumber kebenaran tetap `get-qr`, `proxy.php:184-242`).
4. **Cek Status** — `POST /admin/wa/status` (get-qr) untuk memaksa sinkron status nyata.
5. **Tes Kirim** — kartu/modal khusus uji coba (bukan dari invoice/order):
   - field **Nomor Tujuan** — **boleh diedit bebas**: default diisi nomor device sendiri
     (`nomor_wa`), admin bisa ganti ke nomor lain (nomor admin/HP kedua) untuk **tes**.
     Format dinormalisasi (`0…`→`62…`, min 10 digit).
   - tab/pilihan **Jenis**: **Teks** (textarea isi bebas) atau **PDF** (pilih sumber dokumen
     uji: Invoice / Laporan order — bisa juga "PDF contoh" bawaan), + caption opsional.
   - tombol **Kirim Tes** → memakai jalur produksi yang sama (`send-teks` / `send-dokumen`)
     sehingga hasilnya **tercatat di `wa_logs`** (ditandai `action`/`jenis_kirim`) dan bisa
     diverifikasi di halaman Riwayat.
   - hasil langsung ditampilkan (sukses: pesan terkirim; gagal: pesan error provider).

Karena "Tes Kirim" memakai endpoint yang sama dengan kirim sebenarnya, admin bisa memastikan
device benar-benar bekerja **sebelum** memakainya untuk customer.

### 5.6 Template pesan & UI kirim (modal, nomor bisa diedit)

- `App\Support\WhatsappTemplate` (server) + view `resources/views/wa/*.blade.php`, port dari
  `templateWa()` (`wa-template.js:104-133`) untuk: invoice, laporan, reminder tagihan.
  Satu tempat agar konsisten.
- Ganti aksi **"Kirim via WA"** di `ViewInvoice.php:99-105` agar membuka **modal** (bukan
  langsung `wa.me`):
  - **Nomor Tujuan** — terisi otomatis dari `customer.no_hp`, **tetap bisa diedit** (untuk tes
    atau mengirim ke nomor lain);
  - dropdown/pilih **template** + **caption** (boleh diubah), preview teks;
  - opsi **lampirkan PDF** (Invoice, opsional + lampiran Laporan);
  - tombol **Kirim Sekarang** (via gateway) + tombol **Buka WhatsApp (manual)** (`wa.me`
    tetap sebagai cadangan bila device belum terhubung);
  - hasil kirim ditampilkan dan tercatat di `wa_logs`.
- Tombol serupa ditambahkan di Order (laporan), Invoice list, dan Resi/Surat Jalan (opsional),
  semuanya memakai **modal dengan nomor yang bisa diedit**.
- Tandai di UI siapa yang **sudah pernah dikirimi** dokumen (badge dari `wa_logs`) — padanan
  penanda history arm_app.

### 5.7 Preview SEBELUM kirim (khusus dokumen berfoto: Resi & Laporan Pengerjaan)

Dokumen yang isinya **gabungan banyak foto** (Resi dan Laporan Pengerjaan / Laporan Bulanan)
rawan: foto kurang, urutan salah, atau caption keliru baru ketahuan setelah terkirim ke
customer. Karena itu modal kirim memiliki **2 langkah wajib**: **Preview → Kirim**, bukan
langsung kirim.

- **Sumber preview = sumber PDF yang sama.** Preview memakai renderer/Blade yang sama dengan
  PDF (`resources/views/laporan/_dokumen.blade.php:3-4`, `resources/views/invoice/_faktur.blade.php:3`)
  sehingga **apa yang terlihat di preview = apa yang jadi PDF**. Ini sudah ada untuk laporan
  (`route laporan.preview`, `routes/web.php:118`) dan invoice
  (`route invoice.publik`, `routes/web.php:126`); Resi sudah tampil di `resi.show`
  (`routes/web.php:85`). Untuk laporan/resi bertoken, tambahkan varian **preview berlogin**
  (tanpa token) agar admin melihatnya dari panel.
- **Alur di modal:**
  1. Pilih dokumen (mis. Laporan order / Resi).
  2. Tombol **"Preview"** → membuka tampilan HTML (tab baru / iframe / modal besar). Admin
     memeriksa foto, urutan, dan keterangan per unit.
  3. Tombol **"Kirim"** baru **aktif setelah preview dibuka** (atau minimal menampilkan
     konfirmasi "sudah dicek?") + nomor tujuan (editable) + caption.
  4. Kirim → catat `wa_logs`.
- **Kontrol kelengkapan sebelum kirim** (opsional tapi disarankan): bila laporan masih
  "foto kurang" (`TeknisiService::fotoWajibKurang()`, dev-plan/18) atau `order_unit_reports`
  belum lengkap, modal menampilkan **peringatan kuning** ("Laporan belum lengkap — Y foto
  belum diunggah") dan minta konfirmasi sebelum boleh kirim.
- **PDF lampiran ikut preview:** tombol **"Pratinjau PDF"** memakai `berkas.publik`
  (§5.4, HMAC) di-render inline di browser, sehingga admin melihat **hasil PDF sesungguhnya**
  (bukan hanya HTML) sebelum mengirim.
- Khusus preview ini, **tidak ada penomoran/efek samping**: membuka preview tidak membuat
  record pengiriman dan tidak menaikkan rate limit (hanya *kirim* yang dicatat).

### 5.8 Reminder tagihan terjadwal (fase lanjut)

Setelah fondasi stabil: command terjadwal `wa:reminder` yang mencari invoice `terkirim` dengan
saldo > 0 mendekati/melewati jatuh tempo → kirim WA + PDF dalam batas rate limit, lewat
**queue** (bukan sinkron) agar tidak memblok request. Keputusan bisnis di §9-7.

---

## 6. Fase eksekusi

Urutan: **23-A → 23-B → 23-C → 23-D** (D opsional).

### 23-A — Fondasi device + layanan WA + scan QR ✅ SELESAI (11 Okt 2026)
- Migrasi: `create_provider_accounts_table`, `create_wa_devices_table`,
  `add_wa_device_id_to_users_table`, `create_wa_logs_table`.
- Berkas: `app/Models/ProviderAccount.php`, `WaDevice.php`, `WaLog.php`;
  `app/Services/WhatsappService.php`; `config/wa.php`; `.env.example` (var `WA_*`);
  `app/Filament/Pages/WhatsappDevice.php` (+ view QR);
  `app/Filament/Resources/WaDeviceResource.php` (+ Pages: kelola/assign device).
- **Deviasi dari usulan:** aksi `qr`/`status`/`reset` dipanggil **langsung dari Livewire
  page** (bukan `WhatsappController` + fetch). `WhatsappController` + route `admin/wa/*`
  digeser ke **23-B** bersama endpoint kirim. `WaDeviceResource` ditambahkan (mengelola
  device lebih rapi daripada CRUD manual di page).
- Kriteria selesai: **menu "WhatsApp" muncul di sidebar admin**; admin bisa tambah device →
  **Sambungkan (Ambil QR)** → status berubah jadi `terhubung` (dibuktikan lewat `get-qr`
  "tanpa gambar QR"); **Putuskan & Scan Ulang** (`reset-session`) mengembalikan ke
  `belum_terhubung`. (Pengisian `wa_logs` menyusul di 23-B saat kirim pertama.)

### 23-B — Kirim teks + template + **Tes Kirim** + riwayat + **kirim ulang**
- Berkas: aksi `send-teks`/`send-massal` di `WhatsappController`; **kartu/modal "Tes Kirim"
  (teks & PDF, nomor bisa diedit) di `WhatsappDevice`**; `App\Support\WhatsappTemplate`;
  view `resources/views/wa/*`; `app/Filament/Resources/WhatsappLogResource.php` (**kolom status
  & error + aksi Kirim Ulang / Unduh PDF / Lihat**); `WhatsappService::kirimUlang()`;
  route `POST /admin/wa/kirim-ulang/{waLog}`; ubah aksi `ViewInvoice.php:99-105` menjadi modal
  (nomor editable + gateway + `wa.me` cadangan).
- Kriteria selesai: dari halaman admin bisa **tes kirim teks dan PDF ke nomor yang diketik
  bebas**; kirim ke nomor tes berhasil; rate limit menolak ke-N+1 dalam 1 menit; **setiap
  percobaan (sukses & gagal) tercatat lengkap di `wa_logs` dengan `pesan` + `error_message`**;
  **baris gagal bisa dikirim ulang dari Riwayat tanpa menyusun pesan ulang** (baris baru dengan
  `log_asal_id`); tombol lama `wa.me` tetap tersedia.

### 23-C — Kirim dokumen PDF (invoice/laporan/resi) via signed URL + **preview dulu**
- Berkas: `app/Http/Controllers/PublicBerkasController.php` + route `berkas.publik`;
  action `send-dokumen` di `WhatsappController`; **route preview berlogin** untuk laporan
  (`/laporan/order/{order}/preview` sudah ada) & resi (varian berlogin); penyesuaian
  `InvoicePdfService`/`LaporanPdfService` (agar bisa dipanggil controller publik); tombol
  **Preview → Kirim** dokumen di Order (laporan), Resi, & Invoice.
- Kriteria selesai: **modal menampilkan Preview (HTML + PDF inline) SEBELUM tombol Kirim aktif**;
  PDF invoice/laporan/resi terkirim sebagai dokumen dengan nama file rapi + caption benar;
  **PDF dirender sekali & disimpan (snapshot `dokumen_path`)** sehingga **kirim ulang tidak
  render ulang**; URL HMAC lihat-dari-HP-lain berhasil; URL tanpa/`sig` salah → 403; invoice
  batal/laporan belum diverifikasi → 404; **membuka preview tidak mencatat `wa_logs`/rate
  limit**; tercatat `related_type/related_id` + `pesan` + `dokumen_path` di `wa_logs` saat
  kirim; baris gagal bisa dikirim ulang dari Riwayat.

### 23-D — (opsional) Reminder tagihan terjadwal
- Berkas: `app/Console/Commands/KirimRememberWa.php`, `app/Jobs/KirimWaJob.php`,
  penjadwalan di `routes/console.php`/`bootstrap/app.php`, template reminder.
- Kriteria selesai: plan/queue berjalan di `--dry-run` dulu, tidak melebihi rate limit,
  idempoten (tidak kirim dobel di hari yang sama).

---

## 7. Berkas terdampak & test

### 7.1 Berkas (path lengkap, rencana)

Baru:
- `database/migrations/2026_10_16_000001_create_provider_accounts_table.php`
- `database/migrations/2026_10_16_000002_create_wa_devices_table.php`
- `database/migrations/2026_10_16_000003_add_wa_device_id_to_users_table.php`
- `database/migrations/2026_10_16_000004_create_wa_logs_table.php`
- `app/Models/ProviderAccount.php`, `app/Models/WaDevice.php`, `app/Models/WaLog.php`
- `app/Services/WhatsappService.php`
- `app/Support/WhatsappTemplate.php`
- `app/Http/Controllers/WhatsappController.php`
- `app/Http/Controllers/PublicBerkasController.php`
- `app/Jobs/KirimWaJob.php` (23-D), `app/Console/Commands/KirimRememberWa.php` (23-D)
- `app/Console/Commands/BersihkanWaLog.php` (retensi snapshot + log lama)
- **`storage/app/wa-dokumen/`** (snapshot PDF, gitignored)
- `app/Filament/Pages/WhatsappDevice.php` + `resources/views/filament/pages/whatsapp-device.blade.php`
- `app/Filament/Resources/WhatsappLogResource.php`
- `resources/views/wa/invoice.blade.php`, `laporan.blade.php`, `reminder.blade.php`
- `config/wa.php`
- `database/factories/WaDeviceFactory.php`, `ProviderAccountFactory.php`

Diubah:
- `routes/web.php` (route `admin/wa/*`, `berkas.publik`), `.env.example` (var `WA_*`)
- `app/Filament/Resources/InvoiceResource/Pages/ViewInvoice.php` (aksi kirim → modal)
- `app/Models/User.php` (`wa_device_id` fillable + relasi `waDevice()`)
- `app/Services/InvoiceService.php` (`urlWa()` tetap, ditambah pemakaian `WhatsappTemplate`)
- `resources/views/livewire/teknisi/order-detail.blade.php` (tombol kirim opsional)

### 7.2 Test (Pest, mengikuti pola `tests/` yang ada)

`tests/Feature/WhatsappDeviceTest.php` (23-A):
- admin tambah device + assign ke user → `users.wa_device_id` & `devices.assigned_user_id`
  konsisten; lepas device mengembalikan status;
- `status()` menandai `terhubung` hanya saat respons "ok tanpa QR" (fake HTTP);
- non-owner/non-admin ditolak.

`tests/Feature/WhatsappKirimTest.php` (23-B/C):
- `send-teks` menulis `wa_logs` sukses; pre-flight gagal → 403 & tidak kirim;
- rate limit: kirim ke-`limit+1` dalam 1 menit → 429;
- `send-dokumen` membangun URL bertanda tangan valid & `namafile` benar (assert body yang
  dikirim ke gateway lewat `Http::fake`);
- `PublicBerkasController`: sig valid → `application/pdf`; sig salah → 403; invoice batal →
  404; `WA_PDF_SECRET` kosong → 500;
- **preview tidak menulis `wa_logs` dan tidak menaikkan rate limit**;
- laporan dengan foto kurang → preview menandai peringatan & kirim minta konfirmasi;
- isolasi: device diambil dari user login (kirim tidak bisa memakai `wa_device_id` user lain).

`tests/Feature/WhatsappRiwayatTest.php` (23-B/C — log & kirim ulang):
- kirim gagal → baris `wa_logs` `status=gagal` + `error_message` terisi + `pesan` tersimpan;
- **`kirimUlang()` sukses memakai `pesan`/`dokumen_path` yang tersimpan** — assert
  `InvoicePdfService`/`LaporanPdfService` **tidak** dipanggil saat kirim ulang (mock/spy),
  membuktikan tidak ada render PDF ulang;
- kirim ulang membuat **baris baru** `log_asal_id` = log lama & `percobaan` naik; log lama tetap;
- kirim ulang dengan `target` baru mengirim ke nomor baru;
- semua percobaan (sukses & gagal) tampil di daftar Riwayat, filter "Gagal" benar.

Regresi: `tests/Feature/InvoiceTest.php` (format `wa.me` tetap ada sebagai jalur cadangan),
`tests/Feature/BusinessInfoTest.php`, test publik invoice/resi tetap lulus.

---

## 8. Risiko & pertanyaan terbuka

### Risiko
- **R1 — Ketergantungan vendor gateway**: base URL/endpoint & bridge `api_document.php`
  ditentukan pihak gateway. Mitigasi: semua URL & token di `config/wa.php`, ada kill-switch
  `WA_AKTIF`, dan `wa.me` tetap tersedia.
- **R2 — PDF diunduh ulang oleh gateway**: **diselesaikan** — PDF dirender **sekali** lalu
  disimpan sebagai snapshot (`storage/app/wa-dokumen/`) dan `dokumen_path` dicatat di log, jadi
  baik unduhan gateway maupun **kirim ulang** memakai file yang sama (tanpa render dompdf lagi).
  Sisa risiko: pemakaian disk → ditangani retensi (§9-15).
- **R3 — Kebocoran URL bertanda tangan**: mirip arm_app; dimitigasi tanda tangan HMAC +
  kedaluwarsa `exp` + hanya dokumen layak kirim. Tidak untuk dokumen draft/batal.
- **R4 — rate limit/blokir WhatsApp**: nomor device bisa diblokir bila blast kencang.
  Mitigasi: `WA_RATE_LIMIT_SEND`, kirim massal lewat queue dengan jeda, dan konfirmasi manual.
- **R5 — Rahasia di DB**: token/secret jangan disimpan di tabel pengaturan; tetap `.env`
  (kebiasaan repo).
- **R6 — Test lama**: `InvoiceTest` menganggap `urlWa()` satu-satunya jalur; sesuaikan tanpa
  menghapus ekspektasi `wa.me` (tetap jalur cadangan).

### Pertanyaan terbuka
- **a.** Gateway yang dipakai: **wablasted.com yang sama dengan arm_app** (tinggal isi URL +
  token), atau akun/penyedia lain? (menentukan `config/wa.php` & bentuk bridge dokumen).
- **b.** **Domain produksi crm_paccing** (`APP_URL`) — dipakai untuk URL absolut lampiran;
  pastikan DNS/HTTPS stabil karena server gateway mengunduhnya.
- **c.** Model device: **satu device per admin** (pola arm_app) atau **device bersama**
  (nomor pusat) untuk semua admin? (menentukan UI assign).
- **d.** Kebijakan **nomor pengirim**: device terhubung vs tetap `wa.me` dari HP masing-masing
  untuk beberapa kasus.
- **e.** Domain & apakah crm_paccing boleh membuat bridge `api_document.php` sendiri (server
  sekolah) atau langsung memakai endpoint gateway.

---

## 9. § Keputusan yang diminta

1. **Lanjut membangun gateway WA otomatis** seperti arm_app (device + scan QR + kirim
   teks/dokumen + log)? Usul: **ya**. — setuju/tidak
2. **Penyedia gateway**: pakai **wablasted.com seperti arm_app**? Usul: **ya bila akunnya
   bisa dipakai bersama**; kalau tidak, sebutkan penyedia lain. — pilih
3. **Mode device**: **satu device per admin** (assign user, pola arm_app) vs **device bersama**?
   Usul: **satu device per admin**, dengan opsi tak menutup kemungkinan device bersama di
   fase lanjut. — pilih
4. **Dokumen yang bisa dikirim** (fase 23-C): usul **Invoice + Laporan Pengerjaan** dulu;
   Laporan Bulanan/Resi/Surat Jalan menyusul. — setuju/tambah
5. **URL lampiran**: usul **HMAC + kedaluwarsa (mis. 24 jam)** dan hanya dokumen layak kirim.
   — setuju/ubah masa berlaku
6. **`wa.me` tetap dipertahankan** sebagai jalur cadangan (tombol manual)? Usul: **ya**. — setuju/tidak
7. **Reminder tagihan terjadwal (23-D)**: perlu sekarang atau fase lanjut? Usul: **fase
   lanjut** setelah 23-C terbukti stabil. — setuju/tidak
8. **Rate limit default** 10 pesan/menit per device (seperti arm_app). — setuju/ubah angka
9. **Konfigurasi**: token/secret di `.env`, base URL/template boleh di halaman pengaturan
   Filament. Usul: **ya**. — setuju/tidak
10. **Urutan eksekusi**: 23-A → 23-B → 23-C (→ 23-D), tiap fase di-commit & bisa dirilis
    sendiri. — setuju/tidak
11. **Menu admin**: grup sidebar **"WhatsApp"** berisi **Perangkat & Tes WA** (Sambungkan via
    QR/barcode, Putuskan/Reset, Cek Status, **Tes Kirim teks & PDF**) + **Riwayat Kirim WA**.
    Usul: **ya**. — setuju/tidak
12. **Nomor tujuan selalu bisa diedit** (baik di Tes Kirim maupun modal kirim invoice/laporan),
    supaya bisa dipakai untuk tes atau mengirim ke nomor lain. Usul: **ya**. — setuju/tidak
13. **Preview dulu, baru kirim** untuk dokumen berfoto (Resi & Laporan Pengerjaan/Bulanan):
    tombol **Kirim aktif setelah preview dibuka**, preview HTML + PDF inline memakai renderer
    yang sama dengan PDF final, plus peringatan bila foto belum lengkap. Usul: **ya**
    (wajib untuk semua dokumen, bukan hanya yang berfoto). — setuju/tidak
14. **Log lengkap + kirim ulang**: semua percobaan (sukses & gagal) disimpan di `wa_logs`
    berikut `pesan` final, `dokumen_path` (snapshot PDF), `status`, `error_message`; baris
    gagal bisa **dikirim ulang tanpa generate PDF / susun pesan lagi** (baris baru,
    `log_asal_id` + `percobaan` naik). Usul: **ya**. — setuju/tidak
15. **Retensi snapshot PDF & log**: usul simpan **60 hari** lalu dihapus otomatis oleh command
    `BersihkanWaLog` (dokumen tetap bisa dibuat ulang bila masih diperlukan dari sumbernya).
    — setuju/ubah masa simpan

---

## 10. Status eksekusi 23-A (11 Okt 2026)

Dibuat & lolos `php -l`, Pint, dan test (`tests/Feature/WhatsappDeviceTest.php`, 9 lulus):

| Berkas | Isi |
|---|---|
| `database/migrations/2026_10_16_000001_create_provider_accounts_table.php` | akun gateway |
| `database/migrations/2026_10_16_000002_create_wa_devices_table.php` | device WA |
| `database/migrations/2026_10_16_000003_add_wa_device_id_to_users_table.php` | `users.wa_device_id` |
| `database/migrations/2026_10_16_000004_create_wa_logs_table.php` | riwayat/audit + payload kirim-ulang |
| `app/Models/ProviderAccount.php`, `WaDevice.php`, `WaLog.php` | model (+ sinkron `users.wa_device_id` via event `WaDevice`) |
| `app/Services/WhatsappService.php` | `qr()`, `status()` (pre-flight), `resetSession()`, `normalizeNumber()` |
| `config/wa.php` + `.env.example` | konfigurasi `WA_*` (rahasia di `.env`) |
| `app/Filament/Resources/WaDeviceResource.php` (+ 3 Pages) | kelola/assign device (Owner/Admin) |
| `app/Filament/Pages/WhatsappDevice.php` + `resources/views/filament/pages/whatsapp-device.blade.php` | menu **WhatsApp → Perangkat & Tes WA**: Ambil QR, Cek Status, Putuskan/Reset |
| `database/factories/ProviderAccountFactory.php`, `WaDeviceFactory.php` | factory test |
| `tests/Feature/WhatsappDeviceTest.php` | 9 test (QR/status/reset/normalisasi/sinkron assignment/akses) |

**Prasyarat runtime:** isi `.env` (`WA_AKTIF`, `WA_BASE_URL_*`, `WA_DOCUMENT_TOKEN`,
`WA_PDF_SECRET`) dari penyedia gateway (§9-2) + konfirmasi keputusan §9.

### Status eksekusi 23-B & 23-C (11 Okt 2026)

Dibuat & lolos `php -l`, Pint, dan `tests/Feature/WhatsappKirimTest.php` (**9 lulus**;
total WA 18 test lulus).

**23-B ✅** — `WhatsappService::kirimTeks()` (pre-flight + rate limit + `wa_logs`),
`App\Support\WhatsappTemplate`, **Tes Kirim teks** di halaman Perangkat & Tes WA
(nomor tujuan editable), `WaLogResource` = menu **Riwayat Kirim WA** dengan kolom status/error
& aksi **Kirim Ulang** (via `WhatsappService::kirimUlang`, tanpa render ulang), Lihat, Unduh PDF.

**23-C ✅ (inti)** — `WhatsappService::simpanSnapshot()` + `urlPublik()`/`tandaTanganSah()`,
`PublicBerkasController` + route `berkas.publik` (HMAC + kedaluwarsa + whitelist folder),
`kirimDokumen()` (bridge JSON + Bearer), aksi **Kirim via WA** di halaman Invoice jadi **modal**
(nomor editable + caption template) + tombol **Buka WhatsApp (manual)** (`wa.me` cadangan).

**Belum (sisa):**
- Aksi kirim dokumen untuk **Laporan Pengerjaan & Resi** (jalur mesin sudah ada; tinggal
  tombol yang memanggil `simpanSnapshot`+`kirimDokumen`).
- **Gating preview ketat** ("Kirim aktif setelah preview dibuka") — saat ini preview memakai
  aksi "Unduh PDF" yang sudah ada; bisa diperketat di iterasi UI.
- `WhatsappController` + route `admin/wa/*` sengaja **tidak** dibuat: aksi dilakukan lewat
  Livewire/Filament action yang memanggil service (deviasi dari §5.3, dicatat).
- **23-D** reminder tagihan terjadwal + retensi (`BersihkanWaLog`).
