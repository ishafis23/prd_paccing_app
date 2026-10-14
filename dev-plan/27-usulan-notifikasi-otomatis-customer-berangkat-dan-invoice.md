# 27 — Usulan Notifikasi WA Otomatis ke Customer (Berangkat Teknisi & Pembayaran Selesai + Invoice)

> STATUS: **✅ DIEKSEKUSI (14 Okt 2026) — 27-A, 27-B, 27-C.** Mengikuti usulan default
> §8 (template = Resource CRUD; pemicu berangkat = Job queue; notif pembayaran = tombol
> admin; device notifikasi + fallback terhubung; log `user_id` = pemilik device;
> idempoten lewat cek `wa_logs`). Fondasi: **dev-plan/23** (gateway WhatsApp, `wa_logs`,
> `WhatsappService`, `WhatsappTemplate`) yang sudah berjalan.
> Prasyarat runtime = sama dengan dev-plan/23 (device WA tersambung + `.env` WA_*).

Sumber permintaan (user, 14 Okt 2026, kutipan apa adanya):

> "bisa sekalain siapkn plan ini, ketika teknisi geser mulai orderan berangkat,
> maka ada notifikasi masuk ke costumer, dan data pesan bisa dikelola diadmin,
> jadi nnti sisa disesuaikan, ketika pembayaran juga selesai ada bisa notifikasi
> dikirim oleh admin ke costumer berserta incoice nya, buatkan plan dev md nya
> sebelum eksekusi"

---

## 1. Ringkasan & tujuan

Dua momen penting perjalanan order saat ini **belum** memberi kabar otomatis ke customer:

1. **Teknisi berangkat** — slider "mulai berangkat ke lokasi" (gaya ojek online) mengubah
   status order `terjadwal → menuju_lokasi` (`app/Services/TeknisiService.php:38`, dipanggil dari
   `app/Livewire/Teknisi/OrderDetail.php:535`). Customer tidak dapat notifikasi apa pun.
2. **Pembayaran selesai** — saat admin mencatat pelunasan (`PaymentService::recordPayment()`),
   invoice berubah menjadi `Lunas` (`InvoiceService::segarkanStatus()`,
   `app/Services/InvoiceService.php:328`). Admin sudah bisa mengirim invoice via WA secara
   **manual** (`ViewInvoice` aksi "Kirim via WA", `app/Filament/Resources/InvoiceResource/Pages/ViewInvoice.php:105`),
   tetapi **belum ada** notifikasi khusus "pembayaran diterima" yang dipicu dari status lunas.

Tujuan plan 27:

1. **Notifikasi otomatis saat teknisi berangkat** ke customer (jatuh otomatis dari aksi slider
   berangkat, tanpa admin ketik ulang), memakai gateway WhatsApp yang sudah ada.
2. **Isi pesan dikelola admin** — template bisa diedit (tambah/ubah/aktif-nonaktif) lewat menu
   admin, dengan **placeholder** (nama customer, layanan, nama usaha, jadwal, dsb), **tanpa**
   perlu deploy ulang.
3. **Notifikasi pembayaran selesai + invoice** — saat invoice menjadi `Lunas`, admin bisa
   mengirim notifikasi terima kasih **beserta PDF invoice** ke customer (juga tetap bisa manual
   kapan saja dari halaman Invoice).
4. **Tercatat di `wa_logs`** (audit + bisa kirim ulang) seperti kirim WA lain; konsisten dengan
   pre-flight device, rate limit, dan kill-switch `WA_AKTIF`.
5. **"Sisa disesuaikan"** — template & aturan pemicu dirancang fleksibel agar mudah
   diubah belakangan (semua teks di DB, semua pemicu bisa di-toggle).

Non-tujuan (di luar plan ini): balasan masuk dari customer (webhook), chatbot, blast promosi,
notifikasi ke grup internal (bisa menyusul; jalur `kirimGrup` sudah tersedia bila nanti mau).

---

## 2. Kondisi sekarang (dengan bukti)

| Bagian | Bukti |
|---|---|
| Gateway WA + kirim teks + log + kirim ulang | `app/Services/WhatsappService.php` (`kirimTeks :125`, `kirimGrup`, `kirimDokumen`, `kirimUlang :195`, `catat() :415`) |
| Penyusun teks WA (server) | `app/Support/WhatsappTemplate.php` (`invoice()`, `laporan()`, `resi()`, `tes()`) — **teks masih hard-code di PHP** |
| Halaman device + Tes WA | `app/Filament/Pages/WhatsappDevice.php` + view `whatsapp-device.blade.php` |
| Riwayat kirim (audit) | `app/Filament/Resources/WaLogResource.php`; model `app/Models/WaLog.php` |
| Kirim invoice via WA (manual) | `ViewInvoice.php:105` aksi `kirimWa` (bangun snapshot PDF + `kirimDokumen`) |
| Jalur cadangan `wa.me` | `InvoiceService::urlWa()` (`app/Services/InvoiceService.php:377`) |
| Aksi berangkat teknisi | `TeknisiService::berangkat()` (`:38`), status `terjadwal → menuju_lokasi`; UI `OrderDetail::berangkat()` (`:535`) |
| Pencatatan bayar → lunas | `PaymentService::recordPayment()` (`:40`) → `finalizeLunas()`; status invoice via `InvoiceService::segarkanStatus()` (`:328`) |
| Device per user | `users.wa_device_id` + `WaDevice` (+ relasi `User::waDevice()`) |
| Pola halaman pengaturan Filament (single-row) | `PengaturanBeranda` + model `BerandaSetting`; `KelolaInfoUsaha` + `BusinessInfo` |
| `jenis_kirim` di log | filter `WaLogResource` (`teks`, `grup`, `tes`, `invoice`, `laporan`, `resi`) |

### 2.1 Yang belum ada

1. **Tidak ada pemicu notifikasi** di `TeknisiService::berangkat()` / `PaymentService::finalizeLunas()`.
2. **Template WA tidak bisa dikelola admin** — semua teks ada di `WhatsappTemplate.php`
   (harus ubah kode + deploy untuk mengubah kata-kata).
3. **Tidak ada konsep "device pengirim notifikasi sistem"** — `WhatsappService::kirimTeks()`
   butuh `User $user` (untuk rate limit per-user + `wa_logs.user_id`). Notifikasi otomatis dari
   aksi teknisi bukan "kirim atas nama admin".
4. **Tidak ada jenis kirim** `berangkat` / `pembayaran` di `wa_logs.jenis_kirim`.
5. **Tidak ada notifikasi otomatis saat invoice lunas** (hanya tombol manual).

---

## 3. Gap analysis

| Kebutuhan | Padanan sekarang | Yang harus dibangun |
|---|---|---|
| Pemicu saat berangkat | tidak ada | Hook di `TeknisiService::berangkat()` → event/job |
| Pemicu saat lunas | hanya tombol manual | Hook di `PaymentService::finalizeLunas()` (opsional auto) / tombol admin |
| Template yang bisa diedit | hard-code `WhatsappTemplate.php` | Tabel + model template + halaman admin + resolver placeholder |
| Pengirim notifikasi sistem | `kirimTeks(User,…)` | `kirimTeksSistem()` (user konteks = pemilik device / user sistem; rate limit global) |
| Device pengirim | `users.wa_device_id` | Setting "device notifikasi" + fallback device terhubung |
| Log jenis baru | filter terbatas | tambah `berangkat`, `pembayaran` di `jenis_kirim` + filter |
| Gating | `WA_AKTIF` + pre-flight + rate limit | dipakai ulang + toggle "auto notif" per pemicu |

---

## 4. Desain usulan

### 4.1 Template WA yang dikelola admin (inti "data pesan bisa dikelola diadmin")

**Tabel `wa_templates`** (pola mirip single-row settings, tapi **banyak baris** per jenis):

| kolom | isi |
|---|---|
| `id` | |
| `kode` string unik | `berangkat` \| `pembayaran_lunas` \| `invoice_kirim` \| `laporan` \| `resi` (bisa ditambah) |
| `nama` | label tampil (mis. "Notifikasi Teknisi Berangkat") |
| `isi` text | isi pesan + **placeholder** `{nama}`, `{nama_usaha}`, `{layanan}`, `{alamat}`, `{teknisi}`, `{jadwal}`, `{nomor_invoice}`, `{total}`, `{tautan_invoice}`, dst |
| `aktif` bool | bila false, template tidak dipakai otomatis (fallback ke default / lewati) |
| `diubah_oleh` FK users nullable, `timestamps` | jejak perubahan |

**Resolver** `App\Support\WhatsappTemplate` diperluas (atau service baru `WaTemplateService`):

- `teks(string $kode, array $data): ?string` — ambil baris template aktif, ganti placeholder
  dari `$data`. Bila baris tidak ada / `aktif=false` → kembalikan `null` (pemanggil memutuskan
  lewati atau pakai default bawaan `WhatsappTemplate::…()` sebagai fallback).
- **Daftar placeholder** per kode didokumentasikan di halaman admin (helper text + tombol
  "sisipkan placeholder") agar admin tidak salah ketik.
- **Migrasi awal (seeder)** mengisi `wa_templates` dari teks `WhatsappTemplate` yang sekarang,
  sehingga perilaku awal **identik** dengan sekarang, lalu admin tinggal mengedit.

**Halaman admin** `app/Filament/Pages/KelolaTemplateWa.php` (+ blade), grup navigasi **WhatsApp**,
izin Owner/Admin (pola `PengaturanBeranda::canAccess()`), **atau** sebagai `WaTemplateResource`
(CRUD tabel) — keputusan §6-2. Untuk awal usul **Resource CRUD** (tambah/ubah/hapus lebih
natural daripada halaman form tunggal).

### 4.2 Notifikasi otomatis saat teknisi berangkat

**Pemicu (server, bukan JS):** di `TeknisiService::berangkat()` setelah `$order->save()`
(`app/Services/TeknisiService.php:63`):

```php
// pseudocode — bukan kode final
$order->status = OrderStatus::MenujuLokasi;
$order->save();

if (config('wa.notif_berangkat', true)) {
    KirimNotifWaJob::dispatch($order->id, 'berangkat'); // queue, setelah commit
}
```

- Pakai **Laravel Event + Listener** (`App\Events\OrderBerangkat` → `App\Listeners\KirimNotifBerangkat`)
  **atau** langsung `Job` dari service — keputusan §6-3. Usul: **Job via queue** (`KirimNotifWaJob`)
  supaya request dengan tetap cepat & tahan gagal (retry).
- **Idempoten**: simpan penanda (mis. `orders.berangkat_notif_at` atau cek `wa_logs` terakhir
  untuk `related_type=Order`, `jenis_kirim=berangkat`) agar tidak kirim dobel bila slider
  ditekan dua kali / job retry.
- **Target**: `customer.no_hp` (dinormalisasi `WhatsappService::normalizeNumber()`). Bila kosong →
  **lewati + catat** alasan di log (jangan gagal diam-diam).
- **Isi**: template `berangkat` (§4.1) diisi placeholder: `{nama}`, `{nama_usaha}`, `{layanan}`
  (dari `Order::ringkasanLayanan()`), `{alamat}` (alamat order/customer — lihat dev-plan/14
  multi-alamat), `{teknisi}` (user yang berangkat), `{jadwal}` bila ada. Default bawaan bila
  template kosong.
- **Bunyi notifikasi (opsional, sisa)**: sebutkan estimasi/ETA — **ditunda** ("sisa disesuaikan").

### 4.3 Notifikasi pembayaran selesai + invoice

Dua jalur (boleh keduanya):

1. **Manual (utama, sesuai permintaan "dikirim oleh admin")** — di `ViewInvoice`, saat status
   invoice `Lunas` (`InvoiceStatus::Lunas`), tampilkan tombol **"Kirim Notif Pembayaran + Invoice"**
   yang memakai template **`pembayaran_lunas`** sebagai caption + **PDF invoice** sebagai dokumen
   (memakai jalur `kirimDokumen` yang sudah ada, `ViewInvoice.php:105`). Tombol "Kirim via WA"
   existing tetap sebagai jalur umum.
2. **Semi-otomatis (opsional)** — setelah `PaymentService::finalizeLunas()` membuat invoice
   (bila alur create invoice terpicu), bila `config('wa.notif_lunas')` aktif → `KirimNotifWaJob`
   jenis `pembayaran` (tetap tunduk kill-switch + device terhubung + toggle). Default usul:
   **manual dulu**, auto menyusul (keputusan §6-4) — agar tidak salah kirim saat data belum rapi.

**Isi** template `pembayaran_lunas`: `{nama}`, `{nama_usaha}`, `{nomor_invoice}`, `{total}`,
`{tautan_invoice}` (dari `InvoiceService::urlPublik()`), tanggal bayar. Lampiran: PDF invoice
(`InvoicePdfService::render()` → `WhatsappService::simpanSnapshot()` → `kirimDokumen()`).
Catat `jenis_kirim = 'pembayaran'`, `related = Invoice`.

### 4.4 Device pengirim notifikasi & keamanan (anti-IDOR)

Notifikasi otomatis **tidak** berasal dari sesi admin, jadi aturan device harus eksplisit:

- **Device pengirim ditentukan sistem**, bukan input klien. Usul: setting
  `BusinessInfo`/pengaturan WA → **"Device Notifikasi"** (pilih salah satu `wa_devices`); bila
  kosong → **fallback** ke device pertama yang `connection_status = terhubung`.
- `WhatsappService::kirimTeksSistem(WaDevice $device, string $target, string $pesan, ?Model $related, string $jenisKirim): WaLog`
  **baru**: menulis `wa_logs` dengan `user_id = null` (atau `user_id` = pemilik device agar
  tampil di riwayat dengan jelas — keputusan §6-5) dan **tanpa** cek rate limit per-user, tetapi
  tetap **rate limit global** (mis. `wa.rate_limit_sistem` per menit) + **pre-flight** `status()`
  + kill-switch `WA_AKTIF`.
- Jangan pernah mengambil `wa_device_id` dari request. Template & target juga dari data server.

### 4.5 Log & riwayat

- Tambah nilai `jenis_kirim` baru: **`berangkat`**, **`pembayaran`** (dan `invoice_kirim` bila
  mau memisahkan invoice biasa dari pembayaran) di `WaLogResource` filter.
- Semua notif masuk `wa_logs` → otomatis bisa **Kirim Ulang** dari Riwayat (jalur `kirimUlang`
  sudah mendukung `send-teks`; untuk dokumen `send-document`). Karena payload final disimpan,
  kirim ulang tidak menyusun ulang.
- `related_type/related_id` = `Order` (berangkat) / `Invoice` (pembayaran) sehingga jejak dokumen
  terlihat.

### 4.6 Gating, fallback & pengaturan

- **Kill-switch**: `WA_AKTIF` (sudah ada) — bila false, notif otomatis dilewati (tanpa error ke
  user).
- **Toggle per pemicu** di `.env`/config: `WA_NOTIF_BERANGKAT=true`, `WA_NOTIF_LUNAS=false`
  (default), dan `aktif` per template (§4.1) — supaya "sisa disesuaikan" tanpa deploy.
- **Device belum terhubung** → job dicatat gagal di `wa_logs` + `error_message`, tidak
  menggagalkan aksi berangkat/pembayaran (notif = efek samping, bukan syarat transaksi).
- **`wa.me` cadangan** tetap ada (admin bisa kirim manual dari HP untuk invoice).

---

## 5. Fase eksekusi

Urutan: **27-A → 27-B → 27-C** (boleh dirilis sendiri per fase, tiap fase di-commit).

### 27-A — Template WA yang dikelola admin
- Migrasi `create_wa_templates_table` + model `WaTemplate` + seeder dari `WhatsappTemplate` sekarang.
- `WaTemplateService::teks(kode, data)` (+ dokumentasi placeholder).
- `WaTemplateResource` (CRUD, grup WhatsApp, Owner/Admin) atau `KelolaTemplateWa` page.
- `WhatsappTemplate` existing tetap sebagai **default fallback**.
- **Kriteria selesai:** admin bisa menambah/mengubah/menonaktifkan template; tempat uji
  (Tes Kirim / preview) memakai teks hasil render template; test render placeholder lulus.

### 27-B — Notifikasi otomatis saat teknisi berangkat
- `App\Jobs\KirimNotifWaJob` (+ `WhatsappService::kirimTeksSistem()`).
- Hook di `TeknisiService::berangkat()` (`:63`) setelah commit status.
- Setting **Device Notifikasi** + config `WA_NOTIF_BERANGKAT` + template `berangkat`.
- Penanda idempoten + `jenis_kirim='berangkat'` + filter riwayat.
- **Kriteria selesai:** menyimpan slider berangkat → **1 baris `wa_logs` jenis `berangkat`**
  terkirim ke `customer.no_hp`; bila nomor kosong/device putus/`WA_AKTIF=false` → dilewati dengan
  catatan (aksi berangkat tetap sukses); ditekan dua kali → **tidak** kirim dobel.

### 27-C — Notifikasi pembayaran selesai + invoice
- Tombol **"Kirim Notif Pembayaran + Invoice"** di `ViewInvoice` (muncul saat `Lunas`) memakai
  template `pembayaran_lunas` + PDF invoice (`kirimDokumen`, `jenis_kirim='pembayaran'`).
- (Opsional) hook di `PaymentService::finalizeLunas()` bila `WA_NOTIF_LUNAS=true`.
- **Kriteria selesai:** dari halaman Invoice `Lunas`, admin kirim notif + PDF; tercatat
  `jenis_kirim='pembayaran'` & `related=Invoice`; bisa kirim ulang; `wa.me` tetap ada.

---

## 6. Berkas terdampak & test

### 6.1 Berkas (rencana)

Baru:
- `database/migrations/xxxx_create_wa_templates_table.php`
- `app/Models/WaTemplate.php`
- `database/seeders/WaTemplateSeeder.php`
- `app/Services/WaTemplateService.php`
- `app/Filament/Resources/WaTemplateResource.php` (+ Pages) **atau** `app/Filament/Pages/KelolaTemplateWa.php` + view
- `app/Jobs/KirimNotifWaJob.php`
- `app/Events/OrderBerangkat.php` + `app/Listeners/KirimNotifBerangkat.php` (bila pilih event)

Diubah:
- `app/Services/WhatsappService.php` — tambah `kirimTeksSistem()` (+ rate limit global).
- `app/Services/TeknisiService.php` — hook di `berangkat()` (`:63`).
- `app/Services/PaymentService.php` — hook opsional di `finalizeLunas()`.
- `app/Filament/Resources/InvoiceResource/Pages/ViewInvoice.php` — tombol notif pembayaran.
- `app/Filament/Resources/WaLogResource.php` — filter `berangkat`/`pembayaran`.
- `app/Support/WhatsappTemplate.php` — default `berangkat()` / `pembayaranLunas()`.
- `app/Models/Order.php` — (bila perlu) kolom penanda `berangkat_notif_at`.
- `config/wa.php` + `.env.example` — `WA_NOTIF_BERANGKAT`, `WA_NOTIF_LUNAS`, `WA_RATE_LIMIT_SISTEM`.
- `app/Filament/Pages/WhatsappDevice.php` (+ blade) — pemilih **Device Notifikasi** (opsional).

### 6.2 Test (Pest, pola repo)

`tests/Feature/WaTemplateTest.php` (27-A):
- render placeholder mengganti semua token; token tak dikenal dibiarkan / dikosongkan (pilih satu);
- template nonaktif → `teks()` mengembalikan `null`;
- CRUD template hanya Owner/Admin.

`tests/Feature/NotifBerangkatTest.php` (27-B):
- `berangkat()` dengan `WA_AKTIF=true` + device terhubung (fake HTTP) → 1 `wa_logs`
  `jenis_kirim=berangkat`, `related=Order`, target = nomor customer;
- customer tanpa `no_hp` → tidak kirim, aksi berangkat tetap sukses (status `menuju_lokasi`);
- `WA_AKTIF=false` / device putus → tidak kirim, tetap tercatat alasan;
- ditekan dua kali (idempoten) → hanya 1 kirim;
- `kirimTeksSistem()` menulis log + tetap pre-flight.

`tests/Feature/NotifPembayaranTest.php` (27-C):
- invoice `Lunas` → aksi kirim notif menulis `wa_logs` `jenis_kirim=pembayaran` + snapshot PDF
  ada di `Storage::fake`;
- `Http::assertSent` memuat caption hasil template + `namafile` invoice;
- bisa `kirimUlang`.

Regresi: `tests/Feature/WhatsappKirimTest.php`, `WhatsappDeviceTest.php`, `InvoiceTest.php`,
`TeknisiWorkflowTest.php` tetap lulus.

---

## 7. Risiko & pertanyaan terbuka

### Risiko
- **R1 — Notif mengganggu transaksi:** kirim WA bisa lambat/gagal. Mitigasi: **queue** +
  notif tidak boleh menggagalkan aksi berangkat/pembayaran (efek samping, bukan syarat).
- **R2 — Device pengirim tidak jelas:** notif otomatis bukan aksi user. Mitigasi: setting
  **Device Notifikasi** eksplisit + fallback device terhubung + `user_id` log yang jelas.
- **R3 — Spam / blokir WhatsApp:** kirim otomatis tiap berangkat. Mitigasi: kill-switch, toggle
  per pemicu, rate limit global, dan template bisa dinonaktifkan.
- **R4 — Nomor customer kosong/salah:** notif tidak terkirim. Mitigasi: normalisasi + catat
  alasan di `wa_logs`, tampilkan badge "belum bisa dinotif" (sisa disesuaikan).
- **R5 — Template admin salah placeholder:** pesan berisi token mentah. Mitigasi: helper text +
  daftar placeholder + preview di halaman template.
- **R6 — Data sensitif:** isi template & config rahasia tetap di `.env`, konten di DB (aman).

### Pertanyaan terbuka (untuk "sisa disesuaikan")
- **a.** Notif berangkat: **otomatis penuh** atau **preview + konfirmasi admin** dulu?
- **b.** Perlu **ETA/estimasi jam tiba** di pesan berangkat? (dari mana datanya?)
- **c.** Notif pembayaran: **manual admin** dulu (usul) atau **otomatis saat lunas**?
- **d.** Device pengirim notifikasi: satu **device pusat** atau ikut device teknisi?
- **e.** Perlu juga notif **selesai pengerjaan** (sebelum bayar) & **resi**? (jalur sudah ada)
- **f.** Notif ke **grup internal** (monitoring) selain ke customer?

---

## 8. Keputusan yang diminta

1. Bangun **template WA yang dikelola admin** (tabel + resource/page)? Usul: **ya**. — setuju/tidak
2. Template dikelola sebagai **Resource CRUD** (`WaTemplateResource`) vs **halaman form tunggal**?
   Usul: **Resource CRUD**. — pilih
3. Pemicu berangkat via **Job queue langsung** vs **Event + Listener**? Usul: **Job queue**. — pilih
4. Notif pembayaran: **manual (tombol admin) dulu** vs **auto saat lunas**? Usul: **manual dulu,
   auto menyusul**. — pilih
5. Device pengirim notifikasi: **setting "Device Notifikasi"** + fallback device terhubung?
   Usul: **ya**. — setuju/tidak
6. `wa_logs.user_id` untuk notif sistem: **null** vs **pemilik device**? Usul: **pemilik device**
   (agar muncul masuk akal di riwayat, tetap ditandai `jenis_kirim`). — pilih
7. Idempotensi berangkat: penanda **kolom `orders.berangkat_notif_at`** vs **cek `wa_logs`**?
   Usul: **cek `wa_logs`** (tanpa migrasi kolom baru). — pilih
8. Toggle `.env`: `WA_NOTIF_BERANGKAT=true`, `WA_NOTIF_LUNAS=false`? Usul: **ya**. — setuju/ubah
9. Rate limit notif sistem terpisah? Usul: `WA_RATE_LIMIT_SISTEM` (mis. 20/menit). — setuju/ubah
10. Urutan eksekusi **27-A → 27-B → 27-C**, tiap fase bisa dirilis sendiri. — setuju/tidak

---

## 9. Catatan "sisa disesuaikan"

Setelah 27-A..27-C terpasang, item berikut **sengaja ditunda** dan akan disesuaikan setelah
melihat pemakaian nyata (semuanya sudah punya "rumah" di desain ini, tinggal isi):

- Kata-kata final tiap template (admin ubah sendiri lewat menu, tanpa deploy).
- ETA/jam tiba & tautan pelacakan teknisi di pesan berangkat.
- Notif tambahan: **selesai pengerjaan**, **reminder tagihan** (sejalan dev-plan/23 §5.8 / 23-D).
- Notifikasi ke **grup internal** memakai `WhatsappService::kirimGrup()` yang sudah ada.
- Badge di UI ("customer sudah dinotif berangkat", "invoice sudah dikirim") dari `wa_logs`.
