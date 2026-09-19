# Usulan — Mode Absensi: QR Kode atau Titik Lokasi (GPS), Admin yang Pilih

> STATUS: **✅ SELESAI DIEKSEKUSI (19 September 2026)** — B70 (boleh
> banyak lokasi aktif), B71 (mode global, admin pilih), B72 (radius
> per-lokasi, default **50 meter** sesuai permintaan user "mereka harus
> benar-benar berada di kantor" — lebih ketat dari draf awal 100m), B73
> (GPS = kemudahan, foto selfie tetap verifikasi utama), B74 (tidak ada
> jalur approval manual). Koordinat lokasi diisi lewat Link Google Maps
> (pakai ulang `GoogleMapsLinkService` + peta Leaflet yang sudah ada utk
> Customer). 22 test Pest baru, suite penuh hijau.

## 1. Konteks

Kutipan user: *"untuk absensi apakah bisa dua opsi, sesuai admin yg pilih,
pakai mode scan barcode atau berbasis titik lokasi? dan lokasi bisa
disimpan beberapa sisa pilih yg mana lokasi kantor yg aktif."*

Ini sebenarnya **sudah diantisipasi sejak awal** — dev-plan/15 (usulan
absensi barcode, §8 Batas & Non-Tujuan):

> *"Bukan sistem lokasi GPS/geofencing — validasi 'teknisi benar-benar di
> kantor' mengandalkan kode fisik yang ditempel di sana (B41), bukan
> koordinat GPS. **Bisa jadi usulan lanjutan bila dirasa kurang.**"*

dan kolom `attendance_codes.lokasi` sudah sengaja ditambah dari awal (B41)
supaya desainnya siap diperluas ke banyak lokasi nanti. Jadi usulan ini
persis melanjutkan rencana itu, bukan perubahan arah baru.

**Yang sudah ada & bisa dipakai ulang:**
- Pola "geolocation dari browser" **sudah berjalan** di
  `resources/views/livewire/teknisi/order-detail.blade.php:144-146` —
  `navigator.geolocation.getCurrentPosition()` lalu
  `$wire.updateLokasi(lat, lng)` (dipakai utk lacak posisi teknisi selagi
  "Menuju Lokasi" customer). Mekanisme yang sama tinggal dipakai ulang utk
  absen.
- `Customer` model sudah punya `latitude`/`longitude` (dev-plan/14) —
  pola kolom koordinat + tampil di peta sudah ada presedennya.
- `AttendanceCode` (kode QR) sudah field `lokasi` (label bebas teks) —
  tapi BUKAN koordinat, cuma nama lokasi buat dicatat di riwayat.

## 2. Usulan Ringkas

- **Tabel baru `attendance_locations`**: admin simpan beberapa titik
  kantor/cabang (nama, latitude, longitude, radius meter, aktif/tidak) —
  bisa lebih dari satu aktif sekaligus (mis. kantor pusat + gudang cabang,
  keduanya sah utk absen).
- **1 pengaturan mode absensi** (di halaman "Pengaturan Absensi" yang
  sudah ada): admin pilih **"Scan QR"** atau **"Titik Lokasi (GPS)"** —
  berlaku utk SEMUA teknisi sekaligus, bukan per-teknisi.
- **Mode GPS**: teknisi buka Absensi → tombol "Absen dari Sini" → browser
  minta izin lokasi → jarak dihitung ke SEMUA lokasi aktif (rumus
  Haversine) → lolos kalau ada 1 lokasi dalam radiusnya → lanjut ke form
  foto selfie (SAMA seperti sekarang, foto tetap wajib di kedua mode).
- **Mode QR** tetap seperti sekarang (scan in-app yang baru dibangun),
  tidak berubah.

## 3. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B70 — Bisa berapa lokasi aktif sekaligus?
- **(a) Boleh lebih dari 1 aktif bersamaan.** Absen sah kalau teknisi
  berada dalam radius SALAH SATU lokasi aktif. Cocok kalau ada lebih dari
  1 kantor/gudang yang sah dipakai absen (mis. kantor pusat + gudang).
- (b) Cuma boleh 1 aktif sekaligus (pola sama seperti `AttendanceCode`
  sekarang — bikin baru otomatis nonaktifkan yang lama). Lebih sederhana,
  tapi tidak mendukung 2 kantor beroperasi bersamaan.

Rekomendasi: **(a)** — sesuai kata user "lokasi bisa disimpan beberapa,
[lalu] pilih yg mana yg aktif" dibaca sebagai "toggle aktif per baris",
bukan "cuma 1 boleh nyala".

### B71 — Mode dipilih global (semua teknisi sama) atau bisa campur?
- **(a) 1 pengaturan mode, berlaku semua teknisi** — Admin pilih "Scan
  QR" ATAU "Titik Lokasi" di Pengaturan Absensi, semua teknisi ikut mode
  itu. Simpel, konsisten, gampang diaudit HR ("bulan ini semua absen
  pakai metode apa").
- (b) Kedua mode aktif bersamaan, teknisi bebas pilih salah satu tiap
  hari — lebih fleksibel (mis. kalau GPS di lokasi tertentu tidak
  akurat, bisa fallback scan QR), tapi lebih rumit diaudit & desain UI-nya
  (harus tampilkan pilihan mode ke teknisi tiap absen).

Rekomendasi: **(a)** sesuai kalimat user ("sesuai admin yg pilih... pakai
mode X atau Y") — dibaca sebagai satu keputusan admin, bukan pilihan
bebas teknisi. **(b)** bisa jadi fase lanjutan kalau (a) ternyata kurang
di lapangan.

### B72 — Radius toleransi jarak
Berapa meter toleransi "dianggap di lokasi"? GPS HP standar akurasinya
~5-20 meter di luar ruangan, bisa lebih buruk di dalam gedung/dekat
gedung tinggi.
- **(a) Radius per-lokasi, admin isi sendiri saat tambah lokasi**
  (default 100 meter) — fleksibel, lokasi di area padat bisa dikecilkan,
  area lapang bisa diperbesar.
- (b) Radius global 1 angka utk semua lokasi (pengaturan tunggal) — lebih
  simpel tapi tidak fleksibel kalau karakteristik tiap lokasi beda.

Rekomendasi: **(a)**.

### B73 — GPS bisa "dipalsukan" (fake GPS/rooted phone) — apa toleransinya?
Ini keterbatasan teknis nyata: aplikasi fake-GPS bisa membuat HP
melaporkan lokasi palsu ke browser. Sistem QR fisik tidak kena masalah
ini (kode cuma ada di lokasi fisik).
- **(a) Diterima sebagai keterbatasan yang disadari** — foto selfie tetap
  wajib di kedua mode (verifikasi manusia oleh HR/Admin saat approve),
  GPS di sini fungsinya lebih ke **kemudahan/kecepatan**, bukan
  pengganti verifikasi manusia. Sama seperti kode QR yang juga bisa
  difoto orang lain lalu dikirim (tidak 100% anti-curang juga).
- (b) Tambah pengecekan `navigator.geolocation` punya field akurasi
  (`coords.accuracy`) — tolak kalau akurasi kelewat buruk (mis. >100m,
  indikasi tidak pakai GPS asli/sinyal lemah). Sedikit lebih ketat, tapi
  tetap bisa dilewati fake-GPS app yang canggih (bisa report accuracy
  bagus palsu juga).

Rekomendasi: **(a)** dengan tambahan ringan dari **(b)** (tolak kalau
`accuracy` di atas ambang wajar, mis. >150m — bukan anti-curang total,
tapi menyaring kasus GPS memang belum siap/di dalam gedung beton, bukan
override manual). Foto selfie tetap linchpin verifikasi utamanya.

### B74 — Kalau GPS ditolak/gagal (izin lokasi di-deny, atau GPS tidak
akurat/tidak nemu titik manapun dalam radius)?
- **(a) Tampilkan pesan jelas + jarak ke lokasi terdekat** (mis. "Anda
  120m dari Kantor Pusat, radius yang diizinkan 100m — mendekat lalu
  coba lagi"), tombol coba lagi. Tidak ada jalur manual override di app
  — kalau memang bermasalah terus, hubungi admin (sama seperti kode QR
  hilang/tidak kebaca sekarang, tidak ada override otomatis juga).
- (b) Tambah tombol "Ajukan Manual" yang mengirim notifikasi ke Admin/HR
  utk approve absen manual tanpa GPS/QR — lebih ramah kalau device/lokasi
  bermasalah, tapi nambah 1 alur approval baru yang tidak diminta.

Rekomendasi: **(a)** — tetap dalam lingkup yang diminta user. (b) bisa
diusulkan terpisah kalau di lapangan sering kejadian device
bermasalah.

## 4. Desain Teknis (ringkas)

**Migrasi baru** `attendance_locations`:
```
id, nama, latitude (decimal 10,7), longitude (decimal 10,7),
radius_meter (unsigned int, default 100), aktif (bool, default true),
dibuat_oleh (FK users), timestamps
```

**`AttendanceSetting`**: tambah kolom `mode_absensi` (enum/string:
`qr` | `lokasi`, default `qr` — supaya tidak breaking di hosting yang
sudah pakai QR).

**`AttendanceLocationService`** (baru, pola sama seperti
`AttendanceCodeService`): CRUD lokasi (role Admin/HR/Owner sama seperti
kode QR), `hitungJarakMeter(lat1, lng1, lat2, lng2)` (Haversine),
`lokasiTerdekatDalamRadius(lat, lng): ?AttendanceLocation` (null kalau
tidak ada yang masuk radius manapun).

**`AttendanceService::catatDatang()` / `catatPulang()`**: signature
diperluas terima salah satu bukti kehadiran — `?string $kode` (mode QR)
ATAU `?array $koordinat` (`['lat' => ..., 'lng' => ...]`, mode lokasi) —
divalidasi sesuai `AttendanceSetting::mode_absensi` saat ini. Validasi
kode TETAP jalan seperti sekarang kalau mode QR; validasi jarak baru
kalau mode lokasi. Struktur ledger/`DailyAttendance` yang sudah ada
TIDAK berubah (cuma cara memvalidasi "sudah di lokasi" yang beda).

**`AbsensiScan` (Livewire)**: `getStateProperty()` & alur blade-nya
ditambah cabang baru — kalau `mode_absensi === 'lokasi'`, state
`perlu_kode` (yang sekarang nampilin tombol "Scan Kode QR") diganti
tampilan "Absen dari Sini" (tombol minta geolocation, bukan kamera QR).
`AttendanceCodeService`/scanner QR TIDAK dihapus, cuma tidak dipakai
saat mode = lokasi (kode lama tetap ada di riwayat kalau nanti mode
dibalik lagi ke QR).

**Filament**: resource baru "Lokasi Absensi" (mirip "Kode Absensi"),
form-nya pakai ULANG persis fieldset "Lokasi Alamat" dari
`AddressesRelationManager` (Link Google Maps → tombol "Ambil Koordinat"
→ `GoogleMapsLinkService::resolveCoordinates()` → isi latitude/longitude
→ peta Leaflet `lokasi-picker.blade.php` buat koreksi manual), tambah 1
field baru `radius_meter` dan toggle `aktif`. Plus 1 field baru
(`mode_absensi`, Select QR/Lokasi) di halaman "Pengaturan Absensi" yang
sudah ada.

## 5. Pertanyaan Terbuka (perlu dijawab sebelum eksekusi)

1. ~~Lat/long lokasi kantor diisi bagaimana?~~ **✅ DIJAWAB 19 Sep** —
   sama seperti tambah alamat Customer sekarang: admin tempel **Link
   Google Maps**, klik "Ambil Koordinat" → `GoogleMapsLinkService`
   (sudah ada, `app/Services/GoogleMapsLinkService.php`, generik/tidak
   terikat ke Customer) yang parse koordinat dari link (support link
   penuh maupun short link `maps.app.goo.gl`) → isi `latitude`/
   `longitude` otomatis, ditampilkan di peta interaktif (Leaflet, sudah
   ada juga — `resources/views/filament/forms/components/lokasi-picker.
   blade.php`) buat koreksi pin manual kalau meleset. **Tidak perlu bikin
   apa pun baru utk ini** — form "Lokasi Absensi" tinggal pakai ulang
   persis fieldset yang sama dgn
   `CustomerResource\RelationManagers\AddressesRelationManager` (lihat
   §4).
2. **Switch mode di tengah jalan** — kalau admin pindah dari QR ke
   Lokasi (atau sebaliknya), riwayat absensi lama (yang tercatat pakai
   kode QR) tetap ditampilkan apa adanya di Rekap Absensi kah? (Jawaban
   default: ya, cuma metode ke depan yang berubah, data lama tidak
   diapa-apakan.)
3. Radius default 100 meter — sudah pas, atau ada angka lain yang lebih
   cocok dgn kondisi lokasi kantor asli? Perlu tahu perkiraan luas
   area kantor/parkiran di lokasi sebenarnya.

## 6. Batas & Non-Tujuan (versi ini)

- **Tidak** menghapus mode QR — dua-duanya tetap ada di kode, admin
  tinggal switch pengaturan, bisa balik kapan saja.
- **Tidak** membangun anti-spoofing GPS yang canggih (mis. deteksi
  developer mode, mock location Android) — di luar cakupan wajar utk
  aplikasi web biasa; foto selfie tetap jadi verifikasi utama (lihat
  B73).
- **Tidak** mengubah struktur `DailyAttendance`/ledger insentif
  (`technician_incentives`) — Games 1-4 tetap dihitung dari jam
  datang/pulang yang tercatat, terlepas dari QR atau GPS yang
  memvalidasinya.
- **Tidak** menambah alur approval manual baru (lihat B74) di versi ini.

## 7. Urutan Eksekusi (draft — SETELAH pertanyaan §5 dijawab & B70-B74
disetujui)

1. Migrasi `attendance_locations` + kolom `mode_absensi` di
   `attendance_settings`.
2. `AttendanceLocationService` (Haversine, CRUD, role guard) + test.
3. Perluas `AttendanceService::catatDatang()`/`catatPulang()` menerima
   kode ATAU koordinat sesuai mode aktif + test.
4. UI teknisi (`AbsensiScan` + blade): cabang tampilan GPS vs QR sesuai
   mode.
5. Filament: resource "Lokasi Absensi" + field mode di Pengaturan
   Absensi.
6. `php artisan test` penuh + Pint, update `02-keputusan-eksekusi.md`,
   commit (tunggu instruksi "push" eksplisit sesuai kebiasaan).
