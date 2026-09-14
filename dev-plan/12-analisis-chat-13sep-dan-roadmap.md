# Analisis Chat Klien (13 Sept 2026) & Roadmap Lanjutan

> STATUS: **SEMUA ITEM §3.1–§3.13 SELESAI** — hasil analisis `chat.md`
> (ekspor WA grup "PACCING - ONE GATE INTEGRATED SYSTEM", 11-13 Sept
> 2026). §4 poin 1-5 (wajib-sebelum-1-Okt) maupun poin 6-10 (realistis
> Stage 2) sudah selesai semua, termasuk Portal Customer (§3.6) setelah
> keputusan multi-user dijawab client (1 akun per customer). Tidak ada
> lagi item terbuka dari roadmap ini.

## 0. Konteks penting: deadline

- **1 Oktober 2026**: sistem harus *"running well"* (kata Ust Ranto).
- **Hari ini (13 Sept) s.d. 30 Sept**: tahap uji coba & pematangan sistem.
- Artinya sisa waktu efektif **~2,5 minggu**. Daftar di bawah ini jauh lebih
  besar dari itu kalau dikerjakan penuh — perlu pemilahan wajib-Oktober vs
  boleh-menyusul (lihat §4).

## 1. Sudah selesai dari chat ini

| Item chat | Status |
|---|---|
| "tambah lokasi map pada admin dan tampil pada portal teknisi" | ✅ Selesai (commit `9eecc72`) |
| "tombol geser pada teknisi tidak bisa, pada HP" | ✅ Selesai (commit `3814363`) |
| bug pembulatan koordinat (ditemukan saat testing, bukan dari chat) | ✅ Selesai (commit `43ffab7`) |
| Import Excel Customer + kolom `jenis` (company/perorangan) + `email` (§2) | ✅ Selesai (commit `1e4863e`, `ad3bc93`) — template dropdown terstandar |

## 2. Dampak langsung ke Import Excel Customer (sedang dikerjakan)

Client menyebut format data eksplisit (13:50, 14:26):

> *"contoh data dibutuhkn untuk upload pakai excel: 1. database costumer,
> (nama, wa, map, alamat, catatan, **jenis (company, perorangan)**)..."*

Dua penyesuaian yang relevan ke pekerjaan yang **sedang berjalan**
(`CustomerImportService`, belum di-commit):

1. **Kolom `jenis` (company / perorangan) wajib ada di Customer** — bukan
   cuma soal data, ini men-drive keputusan tampilan/wajib-upload di beberapa
   fitur lain (lihat §3.7 pembayaran, §3.6 surat jalan). **Rekomendasi:
   tambahkan sekarang** ke migration + form + import, sebelum fitur import
   di-commit — jauh lebih murah daripada menambah field ke ribuan data yang
   sudah terlanjur di-import tanpa `jenis`.
2. **Kolom lokasi di file import yang sama** — client maunya link map jadi
   satu kolom di file yang sama (bukan isi manual satu-satu di UI setelah
   import). Bisa pakai ulang `GoogleMapsLinkService` yang sudah ada: kolom
   opsional `link_maps` di template, di-resolve otomatis saat proses import
   (baris yang linknya gagal di-resolve tetap masuk, cuma lokasinya kosong —
   tidak boleh gagalkan seluruh baris).

**Update — sudah dikerjakan:**
- Poin 1 (kolom `jenis`) ✅ selesai — ada di migration, form, tabel, filter,
  dan Import Excel (dropdown, default `perorangan`).
- Poin 2 (kolom link map di file import) — **keputusan: TIDAK dulu**.
  Resolve link Google Maps butuh request internet per baris; untuk ribuan
  baris sekaligus berisiko lambat/timeout/kena rate-limit. Lokasi tetap
  diisi manual per-customer pakai fitur "Ambil Koordinat" yang sudah ada.

## 3. Backlog baru per modul (hasil audit kode existing)

Legenda: ✅ EXISTS · 🟡 PARTIAL (ada tapi beda dari yg diminta) · ❌ MISSING

### 3.1 Status order dinamis ("Ada Perbaikan")
✅ **Selesai** — bukan status terpisah (keputusan final 13 Sept beda dari
usulan awal di sini, lihat
[`13-ada-perbaikan-kategori-dan-pengeluaran-trip.md`](13-ada-perbaikan-kategori-dan-pengeluaran-trip.md)
§2): order TETAP `dikerjakan`, cuma dapat flag
`orders.perbaikan_menunggu_konfirmasi` + catatan/estimasi dari teknisi
(tombol "Ada Perbaikan"). Admin lihat notice di Orderan Harian & detail
order, lalu Setujui (`OrderService::setujuiPerbaikan` — tambah baris
`order_items` baru, harga hasil deal) atau Tolak
(`tolakPerbaikan`). Sparepart yg dipakai teknisi tetap dicatat terpisah
lewat `WorkReportMaterial` (§3.9) — sengaja tidak digabung otomatis ke
biaya jual di atas (keputusan 13 Sept: modal stok vs harga jual customer
independen).

### 3.2 Pengeluaran operasional per order/trip
✅ **Selesai** — `expenses.order_id` (nullable) + field "Order Terkait"
opsional di form Expense (`ExpenseResource`, dipakai admin utk SEMUA
pengeluaran). Nominal selalu manual/bisa diedit (bukan dikunci Rp50rb —
keputusan 13 Sept: beda kasus beda nominal), admin tetap satu-satunya
yg input (konsisten dgn kebijakan sekarang, teknisi tidak mencatat
pengeluaran). `Order::expenses()` relasi baru; margin per order kini
bisa dihitung (`total() - biaya operasional terkait - modal material`).

### 3.3 Halaman Orderan Harian (admin)
✅ **Selesai** — `App\Filament\Pages\OrderanHarian` (menu "Orderan Harian"):
daftar semua order lintas teknisi pada satu tanggal (default hari ini,
bisa pindah tanggal via date picker + tombol "Hari Ini"), ringkasan
jumlah per status, kolom jenis pelanggan & status laporan, link "Lihat"
ke detail order. Akses Admin/Finance/HR spt PetaTeknisi. 8 test baru
(`tests/Feature/OrderanHarianPageTest.php`).

### 3.4 Import Excel dispatch massal (100 ruangan sekaligus assign)
✅ **Selesai** — `OrderDispatchImportService`, header action "Buat Order
Massal" di tab "Unit AC" customer (berdampingan dgn "Import Excel" unit).
Beda dari Import Customer/Import Unit AC: ini bulk-create **SATU Order**
dgn **banyak `order_items`** sekaligus (1 baris file = 1 unit AC yg
SUDAH terdaftar, §3.10) — cocok utk 1 kunjungan/trip yg mencakup banyak
ruangan sekaligus (semua order_item boleh didokumentasikan fotonya
masing-masing lewat fitur foto-per-kategori, §3.8). Form (bukan per
baris file): Jenis Layanan (katalog, wajib), harga default (opsional,
override per unit di file), tanggal/jam jadwal, Assign Tim (opsional,
pakai §3.13). File cuma perlu kolom `kode_unit` (wajib, harus match Unit
AC customer ini) + `harga`/`catatan` opsional per baris. Maks 500 baris.
8 test baru (`tests/Feature/OrderDispatchImportTest.php`).

### 3.5 Re-assign PIC fleksibel di hari-H
✅ **Selesai** — aksi baru "Ganti PIC" (`OrderService::gantiPic()`),
terpisah dari "Assign Teknisi" (kini cuma muncul kalau order belum punya
PIC). Ganti PIC tidak mereset status order (beda dari `assignTechnician`
yg selalu balik ke `terjadwal`) — jadi bisa dipakai saat order sudah
`menuju_lokasi`/`dikerjakan` dan PIC-nya berhalangan mendadak. Attendance
terbuka PIC lama ikut ditutup, PIC lama lepas dari `order_technicians`,
riwayat dicatat ke `catatan_admin`. 15 test baru
(`tests/Feature/OrderGantiPicTest.php`).

### 3.6 Portal Klien/Corporate (asset AC, histori, auto-reminder)
✅ **Selesai** (**client sendiri bilang ini Stage 2**, KECUALI utk
korporat banyak unit spt Dafi/Kalla — itu disetujui masuk tahap 1 juga).
- Model "Unit AC" per customer + penautan ke order — §3.10, **✅
  selesai**.
- Halaman histori pencucian per unit — **✅ selesai**: aksi "Histori" per
  baris di tab "Unit AC" (`CustomerAcUnit::orderItems()`, modal tabel
  tanggal/layanan/status order/teknisi/harga, `resources/views/filament/
  customer-ac-unit-histori.blade.php`).
- Auto-reminder per kategori customer — **✅ selesai**:
  `PaymentService::buatReminder()` (trigger otomatis dari pembayaran
  lunas) sekarang pakai interval 1 bulan utk `CustomerJenis::Company`,
  3 bulan utk lainnya (rumahan/perorangan) — sebelumnya selalu ambil
  angka tetap dari `service_catalog.interval_bulan`. Katalog tetap jadi
  gerbang boolean "perlu reminder berkala atau tidak" (`interval_bulan
  === null` → tanpa reminder, mis. PengadaanAc). `buatReminderManual`
  (§B35, admin manual) TIDAK diubah — override manual sudah tersedia di
  situ. 3 test baru (`tests/Feature/ServiceReminderKategoriTest.php`), +
  2 test baru utk histori (`tests/Feature/CustomerAcUnitHistoriTest.php`).
- **Portal login customer — ✅ selesai.** Keputusan 13 Sept soal
  multi-user **dijawab final: 1 akun per customer** (bukan multi-user
  staf) — lihat
  [`portal-customer/01-konsep-portal-customer.md`](portal-customer/01-konsep-portal-customer.md)
  §4 (update). Login pakai data customer yg sudah ada: kolom
  `customers.password` baru (nullable — null = portal belum diaktifkan
  utk customer itu), `customers.email` jadi username. Guard Laravel
  terpisah `customer` (`config/auth.php`), route `/portal/login` &
  `/portal` (dashboard: daftar Unit AC + kapan terakhir dikerjakan +
  notice servis berikutnya), `/portal/logout`. Admin aktifkan/reset lewat
  aksi "Atur Password Portal"/"Cabut Akses Portal" di `CustomerResource`
  (`CustomerPortalService`). Tamu yg akses `/portal/*` diarahkan ke
  `/portal/login` sendiri (bukan `/login` admin/teknisi) via
  `redirectGuestsTo` di `bootstrap/app.php`. 14 test baru
  (`tests/Feature/CustomerPortalTest.php`).

### 3.7 Bukti pembayaran per laporan teknisi + beda instansi/rumahan
✅ **Selesai** — kolom `orders.jenis_pelanggan` (default dari `customer.jenis`
saat order dibuat, bisa di-override admin di form Order/SPK) dan
`orders.bukti_pembayaran` (foto, upload via portal teknisi, bukan WA).
`TeknisiService::uploadBuktiPembayaran()` — bisa diganti bebas sebelum
order ditutup. Guard di `tutupOrder()`: wajib ada bukti utk rumahan
(termasuk order lama tanpa jenis_pelanggan — default konservatif),
opsional utk instansi. 11 test baru
(`tests/Feature/OrderBuktiPembayaranTest.php`).

### 3.8 Foto laporan per kategori pekerjaan (bukan cuma before/after)
✅ **Selesai** — perbedaan requirement Isha vs teknisi lapangan sudah
diklarifikasi & diputuskan final 13 Sept (urutan slot Cuci AC pakai
usulan teknisi lapangan: `outdoor_proses → indoor_proses →
indoor_sebelum → indoor_sesudah_suhu`), detail di dev-plan/13 §3.
`ServiceType` diperluas (`TambahFreon, Instalasi, Relokasi, Bongkar`).
Tabel baru `work_report_photos` (`work_report_id`, `order_item_id`,
`slot`, `path`, `urutan`) — per **order_item**, bukan per order
(jawaban §5 pertanyaan 2: multi-kategori dalam satu order lewat baris
`order_items`, bukan order terpisah). Template slot per kategori di
`App\Support\FotoLaporanSlot`. `WorkReport.foto_sebelum`/`foto_sesudah`
lama tetap dipertahankan utk data lama.

### 3.9 Tombol "Terkendala/Gagal" + reschedule
✅ **Selesai** — status baru `OrderStatus::Terkendala`, kolom
`alasan_kendala`, tombol "Terkendala/Gagal" di portal teknisi
(`TeknisiService::tandaiKendala`, dari Terjadwal/MenujuLokasi/Dikerjakan,
alasan wajib diisi, attendance terbuka ikut ditutup), aksi "Jadwalkan
Ulang" di admin (`OrderService::reschedule`, kembali ke Terjadwal dgn
jadwal baru) + tetap bisa dibatalkan dari status ini. 17 test baru,
lihat `tests/Feature/OrderKendalaTest.php`.

### 3.10 Data AC Unit per customer (terutama korporat)
✅ **Selesai** — master data (commit `23bac43`) + penautan ke
order/laporan (lanjutan, hari ini): tab "Unit AC" di Data Customer kini
tampil utk **semua jenis customer, termasuk rumahan** (sebelumnya
khusus Company — dibuka krn rumahan jg bisa punya beberapa AC).
`orders.customer_ac_unit_id` (utk baris order_item pertama, sama pola
dgn service_catalog_id/jumlah_unit) & `order_items.customer_ac_unit_id`
(per baris, termasuk baris tambahan lewat "Tambah Layanan"/"Setujui
Perbaikan") — opsional, backward-compatible utk customer yg belum
punya data Unit AC terdaftar. Divalidasi harus milik customer yg sama
(`OrderService::resolveAcUnit()`). Muncul di: form Buat Order (select
"Unit AC" reaktif terhadap customer dipilih), form Tambah
Layanan/Setujui Perbaikan, infolist "Rincian Layanan" & "Foto per
Kategori", form submitLaporan teknisi (label unit di samping nama
layanan), dan Surat Jalan (kolom Unit AC per baris pekerjaan). 12 test
baru (`tests/Feature/OrderAcUnitLinkTest.php`), 439 test total lulus.

### 3.11 Verifikasi/approval laporan oleh admin
✅ **Selesai** — kolom `work_reports.diverifikasi_pada`/`diverifikasi_oleh`,
aksi "Verifikasi Laporan" di tabel Order admin (`OrderService::verifikasiLaporan`,
Admin/Owner saja), kolom badge "Laporan" (Terverifikasi/Menunggu Verifikasi).
**Gate nyata**: `PaymentService::recordPayment()` menolak pelunasan (bukan
DP) selama laporan terakhir order belum diverifikasi — order tanpa
laporan sama sekali tidak terhalang. 10 test baru
(`tests/Feature/VerifikasiLaporanTest.php`).

### 3.12 Surat Jalan (khusus korporat)
✅ **Selesai** — pola sama persis dgn resi (B14a): token publik acak
(`orders.surat_jalan_token`, `Order::pastikanSuratJalanToken()`) + halaman
cetak (`resources/views/surat-jalan.blade.php`, tombol "Cetak / Simpan
PDF" pakai `window.print()`). Isi: kop usaha, customer, lokasi, jadwal,
tim teknisi, tabel daftar pekerjaan (dari `order_items` — bukan
`CustomerAcUnit`, krn belum ada penautan order ↔ unit spesifik, lihat
§3.10 lanjutan), kolom tanda tangan teknisi/customer. Aksi "Surat Jalan"
di tabel admin (`OrderResource`) cuma muncul utk `jenis_pelanggan =
company` & order belum batal, buka link publik di tab baru. 5 test baru
(`tests/Feature/SuratJalanTest.php`).

### 3.13 Tim Teknisi permanen (1 tim = 2 teknisi, assign by tim bukan pilih orang)
✅ **Selesai** — model baru `Team` (nama, aktif, anggota via
`team_members`, PIC = anggota pertama dipilih saat create/edit) + menu
admin "Tim Teknisi" (`TeamResource`, di grup Manajemen). Aksi baru
"Assign Tim" di tabel Order (`OrderService::assignTeam()`) — pilih tim,
seluruh anggotanya (PIC + lainnya) otomatis ditugaskan lewat
`assignTechnician`/`tambahTeknisi` yg sudah ada (`order_technicians`
ad-hoc TETAP jadi sumber kebenaran siapa yg benar-benar bertugas;
`orders.team_id` cuma jejak tim baku mana yg dipakai). Muncul
berdampingan dgn "Assign Teknisi" (keduanya cuma tampil kalau order
belum punya PIC). 8 test baru (`tests/Feature/TeamTest.php`).

## 4. Usulan pentahapan (mengingat deadline 1 Okt)

**Wajib sebelum 1 Okt (blocking "running well" versi client) — SEMUA SELESAI:**
1. ~~Penyesuaian Import Customer (`jenis` + kolom map)~~ — §2, **✅ selesai**.
2. ~~Tombol Terkendala/Gagal + reschedule dasar~~ — §3.9, **✅ selesai**.
3. ~~Bukti pembayaran per laporan (minimal upload, rumahan vs instansi)~~
   — §3.7, **✅ selesai**.
4. ~~Verifikasi admin per laporan~~ — §3.11, **✅ selesai** (client tegas:
   *"tidak bisa jalan ke titik berikutnya jika laporan kerja belum selesai"*
   — diimplementasikan sbg gate pelunasan pembayaran, bukan slider teknisi,
   supaya tidak mengganggu alur lapangan).
5. ~~Halaman Orderan Harian admin~~ — §3.3, **✅ selesai**.

**Realistis Stage 2 (client sendiri sudah bilang, KECUALI korporat besar):**
6. ~~Portal Klien/Corporate + auto-reminder per kategori~~ — §3.6,
   **✅ selesai semua** (histori per unit, auto-reminder per kategori,
   DAN portal login-nya sendiri — keputusan 13 Sept soal multi-user
   sudah dijawab: 1 akun per customer).
7. ~~Import Excel dispatch massal (100 ruangan)~~ — §3.4, **✅ selesai**.
8. ~~Surat Jalan~~ — §3.12, **✅ selesai**.
9. ~~Tim Teknisi permanen (SPK)~~ — §3.13, **✅ selesai**.
10. ~~Foto laporan per kategori + status "Ada Perbaikan" + pengeluaran
    per-trip~~ — §3.1, §3.2, §3.8, **✅ selesai** (lihat dev-plan/13).

**Semua item §3.1–§3.13 dari roadmap 13 Sept sudah selesai.**

## 5. Pertanyaan terbuka (perlu dikonfirmasi ke client sebelum dev §3.1/3.2/3.8)

1. **Foto laporan**: Isha usul (14:59) `outdoor sebelum → indoor proses →
   outdoor sesudah → indoor sesudah+suhu`; teknisi lapangan usul (16:33)
   urutan beda (`outdoor proses → indoor proses → indoor sebelum → sesudah
   +suhu`). Mana yang final? Ini menentukan struktur kolom foto di DB.
2. Kategori pekerjaan (cuci ac/tambah freon/service/instalasi/relokasi/
   bongkar) — benar-benar **multi-select per order**, atau tiap kategori
   jadi order terpisah?
3. Pengeluaran "uang operasional" Rp50rb per tim per trip — apakah nominal
   ini **default yang bisa diedit**, atau fixed, dan siapa yang input
   (teknisi lapor / admin catat)?
4. Prioritas §4 di atas — apakah urutan wajib-sebelum-1-Okt ini sesuai
   dengan yang client maksud, atau ada yang harus digeser?
