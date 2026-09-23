# Usulan — List Order Teknisi Tampilkan Info "Foto Kurang" + Rapikan Order Detail Jadi Tab

> STATUS: **📝 USULAN — BELUM DIEKSEKUSI.** Menunggu diskusi & persetujuan
> user sebelum mulai coding (sesuai permintaan: "bisa kita buat plan untuk
> rapikn tampilann?").

## 1. Konteks — dua keluhan dari sesi ini

Kutipan user: *"pada list orderan terknisi ada yg perlu dilengkap
keterangnnya tapi tidak ada info apa itu, dan mngkn ketika dibuka ordersan
bisa kita buat plan untuk rapikn tampilann? misnya ada tab 1,2,3 , 1 ttg
info orderan, 2 bukti foto2, dan 3 untuk pembayaran?"*

Dua permintaan terpisah, ditangani dalam satu plan karena saling berkaitan
(area kerja yang sama, teknisi mobile UI):

**(A) List order teknisi tidak menjelaskan "perlu dilengkapi apa"**

Ditelusuri di `app/Livewire/Teknisi/JadwalHariIni.php:13-24` (tab "Jadwal
Saya", order aktif) dan `app/Livewire/Teknisi/RiwayatPengerjaan.php:16-21`
(tab "Riwayat Pengerjaan") — keduanya menyertakan order berstatus
`ButuhFollowup`, dan Riwayat juga menyertakan `Selesai`. Blade-nya
(`jadwal-hari-ini.blade.php:38`, `riwayat-pengerjaan.blade.php:20`) cuma
render `<x-teknisi-status-badge>` — badge warna generik dari
`resources/views/components/teknisi-status-badge.blade.php:10-19` (mis.
"Selesai" hijau, "Butuh Followup" oranye).

Sejak dev-plan/17 (foto wajib), ada order yang **statusnya sudah
"Selesai"/"Butuh Followup" tapi foto wajibnya belum lengkap**
(`TeknisiService::fotoWajibKurang()` mengembalikan array tidak kosong,
dicek berdasarkan `ditutup_pada` masih null). Order itu tampil di list
persis sama seperti order yang benar-benar sudah beres — **tidak ada
tanda apa pun** yang bilang "order ini masih perlu foto dilengkapi, dan
akan memblokir Anda berangkat ke order berikutnya". Teknisi baru tahu
kalau: (1) buka detail order itu dan lihat kotak "Lengkapi Foto Wajib", atau
(2) mencoba berangkat ke order lain dan kena block pesan error
(`TeknisiService::berangkat()`, dev-plan/17 §B63). Ini persis keluhan user.

**(B) `order-detail.blade.php` sudah 762 baris, satu scroll panjang**

Ditelusuri strukturnya (garis besar, urutan tampil sekarang):
1. Kembali + kartu customer + peta lokasi (baris 3–127)
2. Ping GPS latar belakang, khusus saat menuju lokasi (128–154)
3. Slider "Mulai Berangkat" / "Check-in Sekarang" (+ foto titik pertama
   Games 2) (155–209)
4. Tombol "Terkendala/Gagal" (210–236)
5. Tombol "Ada Perbaikan" (238–280)
6. Form laporan: Catatan Pengerjaan + Material dipakai (284–325)
7. "Foto per Layanan" — grid upload per `order_item` sesuai template
   (dev-plan/17) (326–421)
8. Checkbox "Butuh Followup" / "Klaim Garansi" + tombol submit laporan
   (423–450)
9. Kotak "Lengkapi Foto Wajib" (dev-plan/17 §B63, amber) (452–483)
10. Layar sukses setelah slider "Selesaikan Order" (B32) (484–500)
11. "Info Pembayaran": ringkasan tagihan, pilih metode, upload bukti
    bayar, channel QRIS/rekening/tunai, bagikan resi, slider "Selesaikan
    Order" (501–725)
12. Galeri semua foto laporan (726–762)

Tiga blok besar (#6–9 laporan+foto, #11 pembayaran, #1–5 info+aksi order)
sudah punya batas alami — cocok dengan usulan tab 1/2/3 user.

## 2. Usulan Ringkas

- **(A)** Tambah indikator "Foto Kurang" di kartu list (Jadwal & Riwayat),
  hanya muncul kalau `fotoWajibKurang($order) !== []`, isinya jumlah/daftar
  singkat foto yang kurang — supaya jelas dari list, tanpa perlu buka
  detail dulu.
- **(B)** Bungkus konten `order-detail.blade.php` jadi 3 tab: **Info
  Order** (kartu customer, peta, aksi slider berangkat/check-in/terkendala/
  ada perbaikan, catatan pengerjaan, material) · **Laporan & Foto**
  (foto per layanan, kotak lengkapi foto wajib, submit laporan, galeri
  hasil) · **Pembayaran** (seluruh blok Info Pembayaran). Murni perubahan
  tampilan (Livewire, satu komponen, satu request) — tidak mengubah logika
  `TeknisiService` sama sekali.

## 3. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B65 — Bentuk indikator "foto kurang" di list
- **(a) Badge kedua kecil di bawah badge status**, mis. pil oranye
  "⚠ 2 Foto Kurang" — muncul hanya kalau `fotoWajibKurang() !== []`.
  Tidak mengubah makna badge status yang sudah ada (Selesai/Butuh
  Followup tetap apa adanya), cuma menambah info.
- (b) Ganti warna/isi badge status itu sendiri jadi "Perlu Dilengkapi" —
  berisiko rancu dengan status order asli (`OrderStatus` enum) yang
  dipakai di banyak tempat lain (laporan admin, dsb.), dan status order
  yang sebenarnya (Selesai/Butuh Followup) jadi hilang dari tampilan
  teknisi.

Rekomendasi: **(a)** — informasi tambahan, bukan mengganti status yang
sudah benar.

### B66 — Query per order di list bisa mahal (N+1)?
`fotoWajibKurang()` (app/Services/TeknisiService.php) melakukan query
`WorkReportPhoto` + loop `order_items` per order. Dipanggil per baris di
list bisa jadi N+1 kalau tidak hati-hati.
- **(a)** Eager-load `orderItems` + hitung `fotoWajibKurang()` di PHP
  level Livewire component (`JadwalHariIni`/`RiwayatPengerjaan`), tapi
  **query `WorkReportPhoto` di-batch sekali** (bukan per order) — ubah
  `fotoWajibKurang()` supaya bisa terima query foto yang sudah di-load,
  atau tambah method baru khusus list yang query semua foto utk semua
  order dalam 1 query lalu grouping. Riwayat dipaginate 10/halaman, Jadwal
  cuma order aktif hari ini (kecil) — jadi N+1 di skala ini **kemungkinan
  besar tidak masalah performa nyata**, tapi tetap baiknya query 1x sesuai
  kebiasaan kode di service ini.
- (b) Biarkan `fotoWajibKurang()` dipanggil apa adanya per baris (N+1
  ringan) — lebih simpel, tapi menyalahi kebiasaan proyek ini yang selalu
  eager-load relasi.

Rekomendasi: **(a)**, tapi implementasinya ringan — cukup pastikan
`with(['orderItems', 'workReports.photos'])` di query list lalu proses
filter di PHP tanpa query tambahan per baris (tidak perlu ubah signature
`fotoWajibKurang()` di service kalau eager-load relasinya sudah benar,
karena Eloquent tidak query ulang relasi yang sudah di-load).

### B67 — Pembagian konten 3 tab: form laporan (catatan+material+foto)
dibundel jadi satu, atau dipecah lintas tab?
Form laporan sekarang 1 kesatuan: Catatan Pengerjaan + Material + Foto per
Layanan + tombol submit, satu `wire:click="submitLaporan"`.
- **(a) Tab "Laporan & Foto" berisi SEMUA field laporan** (catatan,
  material, foto per layanan, tombol submit, kotak lengkapi foto wajib,
  galeri hasil) — form tetap 1 kesatuan utuh, cuma dipindah ke tab
  tersendiri. Tab "Info Order" jadi murni info + aksi status
  (customer, peta, slider berangkat/check-in/terkendala/ada perbaikan).
- (b) Catatan+Material di tab "Info Order", Foto dipisah ke tab "Laporan &
  Foto" sendiri, tombol submit di tab foto — field laporan Livewire tetap
  tersimpan lintas tab (tab switch murni CSS show/hide, bukan re-render),
  jadi secara teknis tetap bisa submit gabungan. Tapi teknisi harus
  bolak-balik 2 tab buat isi 1 laporan — berisiko bingung/kelewatan field.

Rekomendasi: **(a)** — cocok dengan usulan asli user ("2 bukti foto2"
mencakup seluruh alur foto+laporan), dan lebih aman UX-nya (1 form, 1 tab,
tidak perlu gonta-ganti tab di tengah isi laporan).

### B68 — Cara tab diimplementasikan (Livewire vs Alpine murni)
- **(a) Alpine.js `x-data`/`x-show` murni di blade**, tanpa state
  Livewire baru — tab aktif cuma UI lokal browser, tidak perlu round-trip
  server, semua field yang sudah `wire:model` tetap kepegang normal
  karena Livewire tidak re-render saat ganti tab (cuma toggle CSS
  `display`). Konsisten dgn pola project — Livewire sudah dipakai luas
  utk Alpine inline di blade lain di proyek ini (slider, dsb).
- (b) Tambah `public string $tabAktif` di `OrderDetail.php`, ganti tab
  lewat `wire:click` — tiap ganti tab jadi 1 request Livewire (network
  round-trip), tidak perlu utk sekadar toggle tampilan.

Rekomendasi: **(a)** — lebih cepat (tanpa network round-trip), tidak
menambah state ke Livewire component yang sudah kompleks.

### B69 — Badge notifikasi di tab (mis. tab "Laporan & Foto" dikasih titik
merah kalau foto wajib masih kurang)?
- **(a) Ya** — titik/angka kecil di tab "Laporan & Foto" kalau
  `$this->fotoWajibKurang !== []` (computed property sudah ada, tinggal
  pakai), supaya begitu buka order langsung kelihatan tab mana yang perlu
  perhatian tanpa harus klik semua tab.
- (b) Tidak usah, biar sederhana — user harus klik tab satu-satu utk tahu.

Rekomendasi: **(a)** — murah (computed property sudah ada dari dev-plan/17,
`getFotoWajibKurangProperty()`), konsisten dgn tujuan awal keluhan user
(kurang info di depan mata).

## 4. Desain Teknis (ringkas)

**(A) List indicator:**
- `JadwalHariIni::render()` & `RiwayatPengerjaan::render()`: tambah
  `->with(['orderItems', 'workReports.photos'])` ke query (sebagian sudah
  ada `with([...])`, tinggal tambah `orderItems`/`workReports.photos` yang
  belum ada).
- Di blade (`jadwal-hari-ini.blade.php`, `riwayat-pengerjaan.blade.php`),
  panggil `app(TeknisiService::class)->fotoWajibKurang($order)` per baris
  (aman N+1-wise krn relasi sudah eager-loaded — lihat B66) hanya utk
  order berstatus Selesai/ButuhFollowup, tampilkan badge kedua kalau count
  > 0: `⚠ {count} Foto Kurang`.
- Alternatif lebih bersih: tambah accessor/scope kecil di `Order` model
  atau method baru `TeknisiService::orderPerluFotoTambahan(Collection
  $orders): array` yang mengembalikan `[$orderId => $jumlahKurang]` dalam
  1 pemanggilan per list — hindari pemanggilan method service bolak-balik
  di blade. (Keputusan detail ini kecil, bisa diputuskan saat eksekusi,
  tidak perlu B-decision terpisah.)

**(B) Tab di order-detail.blade.php:**
- Bungkus 3 blok besar (Info Order / Laporan & Foto / Pembayaran) masing-
  masing dalam `<div x-show="tab === 'info'">` dst., dengan `x-data="{
  tab: 'info' }"` di wrapper terluar (atau pertahankan tab yang sedang
  aktif sesuai status order — mis. kalau order baru mulai, default buka
  tab "Info Order"; kalau laporan sudah disubmit tapi foto wajib kurang,
  default buka tab "Laporan & Foto"; kalau sudah lunas semua, default
  "Pembayaran" — detail ini didiskusikan saat eksekusi, bukan B-decision
  wajib karena tidak mengubah logika, cuma UX default tab mana yg kebuka).
- Header tab (3 tombol) ditaruh persis di bawah kartu customer/peta,
  sticky di atas biar gampang diakses walau discroll.
- Tidak ada perubahan pada `OrderDetail.php` (Livewire component class)
  sama sekali kecuali (opsional, B69) expose computed property yang sudah
  ada ke badge tab.

## 5. Alur Pengguna

**(A)** Teknisi buka tab "Jadwal Saya" / "Riwayat Pengerjaan" → order yang
statusnya sudah "Selesai"/"Butuh Followup" tapi foto wajib belum lengkap
menampilkan badge kedua "⚠ 2 Foto Kurang" di kartunya → teknisi tahu
sebelum tap masuk kalau order ini masih ada PR, dan tahu order mana yang
harus diselesaikan dulu sebelum bisa berangkat ke order lain (sesuai gate
`berangkat()` dev-plan/17 §B63).

**(B)** Teknisi tap order dari list → masuk halaman detail → lihat 3 tab
di atas: "Info Order" (default) — lihat alamat/peta/status, geser
berangkat/check-in kalau perlu. Pindah tab "Laporan & Foto" — isi catatan,
material, upload foto per layanan, submit laporan, lihat kotak "Lengkapi
Foto Wajib" kalau ada sisa. Pindah tab "Pembayaran" — pilih metode, upload
bukti bayar, geser "Selesaikan Order".

## 6. Definisi "Selesai"

- [ ] List "Jadwal Saya" & "Riwayat Pengerjaan" menampilkan badge kedua
      "⚠ N Foto Kurang" pada order yang `fotoWajibKurang() !== []`, tidak
      menampilkan apa pun pada order yang sudah lengkap.
- [ ] Query list tidak menambah N+1 baru (foto di-eager-load bareng query
      utama, bukan query tambahan per baris).
- [ ] `order-detail.blade.php` terbagi 3 tab (Info Order / Laporan &
      Foto / Pembayaran) sesuai B67(a), navigasi tab pakai Alpine murni
      (B68a), tanpa network round-trip.
- [ ] Semua `wire:model` yang sudah ada tetap berfungsi persis sama
      setelah dipindah ke dalam tab (foto upload, submit laporan, slider
      aksi, dll) — dites manual di browser (upload foto per tab, submit
      laporan, geser slider berangkat/selesaikan order) SETELAH eksekusi.
- [ ] Tab "Laporan & Foto" menampilkan badge kalau foto wajib kurang
      (B69a).
- [ ] Test Pest baru utk bagian (A) — assert badge/teks foto-kurang
      muncul di response `Livewire::test(JadwalHariIni::class)` /
      `RiwayatPengerjaan::class)` sesuai kondisi. Bagian (B) murni
      tampilan/Alpine — dites via `assertSee` pada tab markup + regresi
      test lama (submit laporan, upload foto, dsb.) tetap hijau tanpa
      perubahan (karena tidak ada logic yang berubah).
- [ ] `php artisan test` penuh hijau, Pint bersih.
- [ ] Dicatat di `02-keputusan-eksekusi.md` setelah selesai.

## 7. Pertanyaan Terbuka (tidak menghalangi eksekusi, tapi baiknya
dikonfirmasi user dulu)

1. Default tab yang terbuka saat order baru dibuka — ikut status order
   (lihat §4) atau selalu "Info Order"? (Rekomendasi: ikut status, biar
   teknisi langsung diarahkan ke hal yang perlu dikerjakan — tapi ini
   nice-to-have, bisa juga selalu default "Info Order" dulu versi paling
   sederhana.)
2. Apakah badge "Foto Kurang" di list perlu juga menyebutkan **nama order
   item/kategori layanan** (mis. "Cuci AC: 2 foto kurang") atau cukup
   angka total saja? (Rekomendasi: angka total saja di list — detail
   lengkap toh sudah ada begitu masuk tab "Laporan & Foto".)

## 8. Batas & Non-Tujuan (versi ini)

- **Tidak** mengubah logika bisnis apa pun di `TeknisiService` — murni
  tampilan (list + tab). `fotoWajibKurang()`, `berangkat()`,
  `submitLaporan()`, `lengkapiFotoWajib()` semua dipakai apa adanya.
- **Tidak** menyentuh tampilan admin (`OrderResource` Filament) — hanya
  UI teknisi mobile.
- **Tidak** mengubah `OrderStatus` enum atau badge warnanya — indikator
  foto-kurang adalah badge KEDUA, terpisah dari badge status.
- **Tidak** menambah tab/halaman baru di luar order-detail — cuma
  reorganisasi konten yang sudah ada jadi 3 kelompok.

## 9. Urutan Eksekusi (draft, dieksekusi SETELAH user setuju plan ini)

1. (A) List indicator — ubah query `JadwalHariIni`/`RiwayatPengerjaan`
   (eager-load), tambah badge kedua di kedua blade, test Pest.
2. (B) Tab order-detail — bungkus 3 blok besar dengan Alpine `x-show`,
   tambah header tab, badge tab (B69), tanpa ubah `OrderDetail.php`.
3. Test manual di browser: upload foto di tiap tab, submit laporan,
   slider berangkat/check-in/selesaikan order, upload bukti bayar — pastikan
   semua tetap berfungsi persis sama setelah pindah tab.
4. `php artisan test` penuh + Pint.
5. Update `02-keputusan-eksekusi.md`, commit (tunggu instruksi "push"
   eksplisit dari user sebelum push, sesuai kebiasaan).
