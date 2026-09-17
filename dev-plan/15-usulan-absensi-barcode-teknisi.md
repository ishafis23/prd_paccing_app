# Usulan — Absensi Kantor Teknisi via Barcode + Skema Insentif "Games 1–7"

> STATUS: **DRAFT — menunggu diskusi & persetujuan** (chat Ust Ranto/Bendahara
> YDF, 17 September 2026: absensi dasar → rincian skema "Games 1–7" →
> disclaimer Games 1 yang menjawab B47 & toleransi lembur, semua di hari
> yang sama). Belum dicatat di `02-keputusan-eksekusi.md` — pindah ke sana
> begitu opsi B39–B55 di bawah disetujui.

## 1. Konteks

Arahan dari chat (17 Sep, Ust Ranto — Bendahara YDF): perlu menu **absensi
kantor untuk teknisi**, terpisah dari absensi lapangan yang sudah ada
(check-in/out per kunjungan order, tabel `attendances` — lihat
[`teknisi/01-konsep-teknisi.md`](teknisi/01-konsep-teknisi.md)). Alurnya:

1. Admin generate **barcode** (disimpan/ditempel fisik di kantor), bisa
   **expire otomatis** atau **diaktifkan/dinonaktifkan manual**, dan bisa
   **buat barcode baru** kapan saja (barcode lama otomatis tidak berlaku).
2. Teknisi **scan barcode** di kantor lalu **upload foto** — dua kali sehari:
   saat **datang** dan saat **pulang**.
3. Hasilnya tersimpan di **tabel baru** "absensi": tanggal, jam datang, jam
   pulang, foto datang, foto pulang.
4. Ketentuan bonus/denda berbasis jam datang (dari chat, 3 tingkat — **ini
   yang sudah lengkap**):

   | Jam Datang | Konsekuensi |
   |---|---|
   | 07.30 – 07.35 | Dapat **Games 1**, bonus **Rp 7.500** |
   | 07.36 – 08.05 | Datang normal, **tidak dapat bonus** |
   | ≥ 08.06 | **Denda potongan gaji Rp 7.500** |

5. Dua ketentuan **belum lengkap** (diakui sendiri oleh Ust Ranto — "Mutia
   yang ingat, catat"):
   - Bonus untuk **absen pulang**, berdasarkan **omset** tertentu, pada
     **rentang jam pulang** tertentu (PKL ... — belum disebutkan).
   - **Toleransi** keterlambatan **tanpa potong gaji** jika **sehari
     sebelumnya kerja sampai malam**.

Dua poin ini **tidak bisa dieksekusi sekarang** — nominal/ambang belum ada.
Rencana: bangun sistemnya sekarang agar poin 4 (yang sudah lengkap) langsung
jalan, dan poin 5 (yang belum lengkap) tinggal **diisi angkanya** nanti tanpa
migrasi/perubahan kode besar (lihat §3 B42 & §7).

## 1a. Update (hari sama) — Rincian Skema Insentif "Games 1–7"

Ust Ranto mengirim rincian lengkap skema "Games" yang tadinya cuma disebut
sekilas di poin 4 (§1). Ini **memperluas cakupan jauh lebih besar** dari
sekadar absensi datang/pulang — mencakup performa kerja harian (titik
kunjungan, unit AC, omset tim, bahkan cuci motor). Dikutip apa adanya:

| Games | Syarat | Bonus | Bukti Foto Wajib |
|---|---|---|---|
| **1** | Hadir di Base/Warung selambat-lambatnya Pkl 07.35 | Rp 7.500/orang | Foto kehadiran (aplikasi Absensi) |
| **2** | Sampai di rumah customer **titik pertama** selambat-lambatnya Pkl 08.30 | Rp 7.500/orang | Foto pengerjaan **buka cover AC indoor** |
| **3** | Cuci/perawatan motor pagi/sore, maks. **2 orang per motor** | Rp 3.000/orang | Foto pengerjaan |
| **4** | Selesaikan ALL titik, atau min. **9 titik** (jalan berdua) / min. **5 titik** (jalan sendiri), dan sudah di Base/Warung ≤ Pkl 17.45 | Rp 15.000/orang (berdua) / Rp 25.000 (sendiri) | Foto kepulangan |
| **5** | Omset jasa kerja tim ≥ Rp 850.000 (jalan berdua) | "Bracci2" Rp 40.000/orang (berdua) / Rp 100.000 (sendiri) | — (belum disebut) |
| **6** | Selesai 12 unit (berdua) / 6 unit (sendiri) | Rp 50.000/orang | — (belum disebut) |
| **7** | Penilaian customer ke teknisi | *sedang dipikirkan* | — |

Catatan resmi dari pengirim (dikutip apa adanya):
1. Berlaku mulai **22 April 2026**.
2. Skema ini **uji coba**, bisa berubah sewaktu-waktu.
3. Tiap Games **independen** — bisa dapat satu, beberapa, atau semua
   sekaligus, tergantung yang benar-benar dikerjakan.
4. Yang dihitung adalah **pengerjaan utama**, BUKAN pengerjaan **klaim**
   (garansi/rework tanpa tagihan) — titik klaim & unit klaim **tidak masuk**
   hitungan Games 2/4/5/6.

**Catatan pribadi (bukan dari chat, untuk diskusi):** tanggal "22 April 2026"
di catatan #1 janggal — itu **sebelum** tanggal chat ini (17 Sep 2026),
kemungkinan besar teks lama yang di-forward ulang (pengumuman awal uji coba
skema ini di grup teknisi). Perlu dikonfirmasi: yang mau dieksekusi di CRM
ini apakah **skema yang sudah berjalan manual sejak April** dan sekarang
tinggal didigitalkan, atau ada revisi nominal/syarat sejak itu? Lihat §7.

## 1b. Update (hari sama, ke-3) — Disclaimer Games 1 (jawab B47 & toleransi §7)

Ust Ranto kirim ulang Games 1 dengan tambahan **⚠️Disclaimer** yang
menjawab langsung dua pertanyaan terbuka sebelumnya (B47 di §3, dan poin
toleransi dari Mutia di §7 lama). Dikutip apa adanya:

> Hadir di Base/Warung selambat-lambatnya Pkl 07.35. Dapat Bonus Rp
> 7.500/orang.
> - Kedatangan Normal Pkl 07.36–08.05 (tidak ada bonus dan tidak ada denda)
> - Datang Pkl 08.06 dst. Denda Rp 7.500
> - Toleransi telat sampai Pkl 10.00. **Hanya dan hanya jika** semalam kerja
>   lembur sampai Pkl 20.00 malam.

Artinya (rekonsiliasi ke desain yang sudah ada):
- **B47 terjawab** → opsi **(a)** yang dipilih: Games 1 (bonus) dan denda
  telat itu **dua hal terpisah** seperti sudah diasumsikan, cuma jendela
  Games 1 sekarang **cukup 1 batas atas** ("selambat-lambatnya 07.35"),
  bukan jendela dua sisi 07.30–07.35 seperti tebakan awal — jadi datang jam
  07.00 pun tetap dapat Games 1, bukan cuma pas di rentang itu.
- **Toleransi kerja malam terjawab** (yang tadinya "Mutia yang ingat," §7
  poin 8) — **datang dari Ust Ranto sendiri**, bukan Mutia: kalau **hari
  sebelumnya** teknisi kerja/lembur sampai jam 20.00, maka batas denda hari
  ini **mundur dari 08.06 jadi 10.00** (bukan berarti dapat Games 1 — cuma
  meniadakan denda). Satu hal yang **masih perlu dikonfirmasi** (belum
  disebut eksplisit): sumber data "kerja sampai jam 20.00 malam" itu dari
  **`jam_pulang` absen kantor** (`daily_attendances`) hari sebelumnya, atau
  dari jam order/`work_reports` terakhir ditutup? Rekomendasi saya: pakai
  `jam_pulang` absen kantor hari sebelumnya (≥ 20.00) — datanya sudah pasti
  ada begitu fitur absen pulang ini jalan (§1), dan paling sederhana untuk
  dicek otomatis. Tapi ini **asumsi saya**, bukan yang dikonfirmasi Ranto —
  lihat §7.

## 1c. Pemetaan Games → Data yang Sudah Ada vs Perlu Ditambah

| Games | Konsep di skema | Data di sistem sekarang | Gap yang perlu ditambah |
|---|---|---|---|
| 1 | Hadir di kantor ≤ jam tertentu, foto, + toleransi kalau lembur malam sebelumnya | Bisa dipetakan ke **absensi datang** (§1, tabel baru) | — (ambang jam & toleransi sudah jelas — §1b/B47 — tinggal pastikan sumber data "lembur sampai 20.00", lihat §7) |
| 2 | Sampai di **titik pertama** (order pertama hari itu) sebelum jam tertentu, foto "buka cover" | Slider **"Check-in Sekarang"** yang sudah ada (job-site, `attendances`) — dipakai tiap kali teknisi tiba di lokasi customer, jadi timestamp-nya akurat | Perluas slider itu: minta foto tambahan **khusus check-in pertama** hari itu (lihat B48, sudah dikonfirmasi) |
| 3 | Cuci motor, maks 2 orang/motor | Tidak ada konsep ini sama sekali di sistem (motor bukan entitas yang dilacak) | Perlu **log baru** `motor_cleanings` (lihat B49) — tidak terkait Order/Customer |
| 4 | Titik selesai (9/berdua, 5/sendiri) + pulang ≤ jam tertentu, foto | "Titik selesai" = hitung `orders` berstatus selesai hari itu per teknisi; foto = **absensi pulang** (§1) | Perlu hitung jumlah titik per teknisi per hari (query, bukan kolom baru) + exclude klaim (lihat gap "klaim" di bawah) |
| 5 | Omset jasa kerja **tim** ≥ ambang | `payments.total_tagihan` atau `incomes.nominal` per order → bisa dijumlah per tim/hari | Perlu exclude klaim + definisi "omset jasa" (semua tagihan atau cuma jasa, exclude sparepart?) — lihat §7 |
| 6 | Jumlah **unit** AC selesai | `orders.jumlah_unit` (atau granular per `order_items`) → bisa dijumlah per tim/hari | Perlu exclude **unit klaim** — granularitas ada di `order_items`, bukan di level `orders.jumlah_unit` (lihat gap "klaim") |
| 7 | Penilaian customer | Tidak ada sama sekali | **Di luar cakupan versi ini** — pengirim sendiri bilang "sedang dipikirkan" |

**Gap lintas-Games — status setelah konfirmasi 17 Sep (lihat §7):**
- **Penanda "klaim"** — ✅ terjawab: tidak ada di sistem sekarang, tapi
  dikonfirmasi tidak pernah campur dalam 1 order → cukup `orders.is_klaim`
  (lihat B50, direvisi).
- **"Jalan berdua" vs "jalan sendiri"** — dihitung dari jumlah teknisi
  berbeda yang mengerjakan order-order hari itu (`order_technicians`),
  bukan `orders.team_id` statis (lihat B51, keputusan teknis internal,
  tidak perlu konfirmasi eksternal).
- **"Bracci2"** — ✅ terjawab: cuma istilah/sebutan mereka untuk "bonus",
  bukan konsep terpisah. Kategori teknis di kode tetap
  `games5_omset_tim`, label yang ditampilkan ke user boleh pakai kata
  "Bracci2" sesuai istilah mereka sendiri.

## 2. Kenapa BUKAN pakai tabel `attendances` yang sudah ada

Tabel `attendances` sekarang punya makna spesifik: absen **per kunjungan
order** (`order_id` nullable tapi jam_masuk/keluar dicatat otomatis oleh
`TeknisiService::checkIn/checkOut` saat teknisi kerja di lokasi customer —
lihat `app/Services/TeknisiService.php`). Kalau dipakai ulang untuk absen
kantor harian, akan tercampur dua makna berbeda (banyak baris per hari kalau
teknisi kerjakan banyak order, tidak ada kolom foto/bonus/denda) dan
berisiko merusak logika existing (B21 auto-close attendance saat order
ditutup/reassign). Karena itu diusulkan **tabel terpisah** — lihat B39.

## 3. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B39 — Tabel baru vs reuse `attendances`
- **(a) Tabel baru `daily_attendances`** (absen kantor harian, 1 baris per
  teknisi per tanggal) terpisah total dari `attendances` (job-site). Nama
  tabel gaya Inggris konsisten dengan skema lain (`attendances`,
  `work_reports`, dst — kolom tetap bahasa Indonesia).
- (b) Reuse `attendances` + tambah kolom foto/bonus/denda, `order_id`
  dikosongkan untuk baris absen kantor.

Rekomendasi: **(a)** — hindari campur makna & risiko regresi ke fitur
check-in lapangan yang sudah jalan.

### B40 — Bentuk & siklus hidup "barcode"
- **(a) QR code berisi URL unik** (mis.
  `https://domain/teknisi/absensi/{kode}`) yang dicetak/ditempel di kantor.
  Teknisi scan pakai **kamera HP bawaan** (bukan scanner dalam-app) →
  browser langsung buka link tsb → kalau belum login diarahkan login dulu
  (route tetap di bawah `auth + role:teknisi`, jadi tidak ada endpoint
  publik tanpa login). Admin generate lewat tombol "Buat Kode Baru" →
  otomatis nonaktifkan kode lama (hanya **1 kode aktif** berlaku di satu
  waktu); opsional isi tanggal kedaluwarsa; toggle aktif/nonaktif manual
  kapan saja.
- (b) Barcode 1D + kode angka pendek, discan pakai scanner khusus barang.
  Lebih rumit (perlu hardware & library scan-1D), tidak perlu untuk kasus
  ini (yang scan adalah HP teknisi sendiri).

Rekomendasi: **(a)** — QR + URL adalah yang paling simpel dievaluasi: kamera
bawaan HP Android/iOS sudah bisa buka link QR tanpa install apa pun,
dan tidak perlu library JS QR-reader di halaman.

### B41 — Alur & tempat scan
- **(a) Satu kode aktif untuk seluruh kantor** (bukan per-teknisi). Kertas
  QR ditempel di 1 titik kantor. Siapa pun teknisi yang login lalu scan,
  sistem tahu siapa yang absen dari sesi login-nya (`auth()->user()`), bukan
  dari kode itu sendiri. Kode cuma **bukti bahwa orang itu benar-benar di
  kantor** (kode fisik, tidak bisa discan dari rumah kalau kertasnya
  memang di kantor) — sekaligus alasan kenapa expire/nonaktifkan penting
  (kalau kertas hilang/difoto orang, admin bisa langsung nonaktifkan &
  buat baru).
- (b) Kode unik per teknisi (QR berbeda tiap orang, ditempel di kantor juga)
  — lebih ribet cetak & kelola, tidak menambah keamanan berarti karena
  tetap 1 lokasi fisik yang sama.

Rekomendasi: **(a)**. **✅ Dikonfirmasi Ranto** — intinya teknisi **wajib**
scan kode fisik yang ada di kantor & sedang aktif (bukan sekadar login),
baru bisa lanjut upload foto datang/pulang. Ranto juga minta dipertimbangkan
kalau nanti mau pasang titik barcode di **cabang lain** — untuk itu, tabel
`attendance_codes` (§4) ditambah kolom `lokasi` (nullable, label bebas
mis. "Kantor Pusat") **dari sekarang**, meski logikanya tetap "1 kode aktif
global" dulu — begitu ada cabang kedua, tinggal ubah aturan jadi "1 kode
aktif per lokasi" tanpa migrasi baru (kolomnya sudah ada).

### B42 — Ketentuan bonus/denda: hardcode vs konfigurasi admin
- **(a) Tabel pengaturan 1-baris `attendance_settings`** (pola sama seperti
  `business_infos` — lihat `09-usulan-info-usaha.md`): jam mulai/selesai
  bonus, nominal bonus, jam batas normal, jam mulai denda, nominal denda —
  semua **bisa diubah Admin/HR lewat halaman**, tanpa deploy. Seluruh
  ambang Games & toleransi (§1b) ikut ditaruh di tabel yang sama.
- (b) Hardcode angka di `AturanAbsensiService` (ubah = perlu deploy ulang).

Rekomendasi: **(a)** — ini aturan bisnis/payroll, wajar kalau bendahara mau
ubah nominal sendiri sewaktu-waktu (mis. inflasi, negosiasi) tanpa nunggu
developer.

### B43 — Siapa yang boleh kelola kode & lihat rekap
- **(a) Admin & HR** boleh generate/aktifkan/nonaktifkan kode + ubah
  `attendance_settings`. **Owner, Admin, HR, Finance** boleh lihat rekap
  absensi (Finance perlu untuk hitung bonus/denda ke gaji).
- (b) Hanya Owner.

Rekomendasi: **(a)** — konsisten dengan pola role existing
(`OrderanHarian`/`PetaTeknisi` pakai `Admin, Finance, Hr`).

### B44 — Foto & kuota penyimpanan
- **(a)** Foto datang/pulang masuk folder baru `absensi/` di disk `public`,
  **ikut guard `StorageQuotaService`** (B25) sebelum simpan — konsisten
  dengan foto laporan kerja. **Tidak** ikut pembersihan otomatis 60 hari
  (`foto:bersihkan`) di versi ini — foto absensi dipakai untuk audit
  gaji/bonus, retensinya beda urusan dari foto laporan servis. Kalau nanti
  perlu retensi sendiri, jadi usulan terpisah.
- (b) Ikut aturan pembersihan 60 hari yang sama dengan `work-reports/`.

Rekomendasi: **(a)** — hindari foto bukti bonus/denda hilang sebelum sempat
dipakai HR proses gaji bulanan.

### B45 — Override manual (safety valve) untuk pengecualian
- **(a)** Tiap baris `daily_attendances` punya kolom `dikecualikan_denda`
  (boolean) + `catatan_admin` (text nullable) — Admin/HR bisa tandai
  "kecualikan dari denda" secara manual dengan alasan, **terlepas dari**
  aturan toleransi otomatis (lembur malam, §1b) sudah cocok kasusnya atau
  belum. Berguna untuk kasus di luar aturan baku (izin dadakan, force
  majeure, dll) yang tidak akan pernah bisa dicakup rumus otomatis.
- (b) Tidak ada override — semua murni otomatis dari jam scan.

Rekomendasi: **(a)** — tetap perlu meski aturan toleransi sudah ada,
karena selalu ada kasus di luar rumus yang butuh keputusan manual.

### B46 — Absen pulang tanpa data omset/aturan lengkap
- **(a)** Kolom `jam_pulang` + `foto_pulang` **dicatat sekarang** (scan sore
  hari, status berubah dari "sudah datang" → "sudah pulang"). Kolom
  `bonus_pulang_nominal` & `omset_saat_pulang` disiapkan **nullable**,
  **tidak dihitung otomatis dulu** sampai ketentuan dari Mutia didapat —
  Admin/HR isi manual kalau perlu sementara ini (lewat rekap), sistem tidak
  menolak/menahan proses absen pulang karena aturan belum ada.
- (b) Tunda seluruh fitur absen pulang sampai aturan lengkap.

Rekomendasi: **(a)** — jangan blokir fitur yang sudah jelas (rekam jam +
foto pulang) hanya karena satu sub-aturan bonus belum lengkap.

> **Update:** setelah rincian Games 1–7 masuk (§1a), "bonus pulang berbasis
> omset" ternyata ada **dua** skema berbeda (Games 5 = omset, Games 6 = unit),
> plus Games 4 (bonus kepulangan berbasis jumlah titik). B46 di atas tetap
> berlaku sebagai fallback umum; desain konkret tiap Games ada di B52–B54.

### B47 — Rekonsiliasi jam Games 1 vs tabel 3-tingkat di §1 poin 4 — **TERJAWAB (§1b)**
~~Chat pertama (§1) bilang jendela bonus 07.30–07.35; rincian Games 1 awal
cuma bilang "Pkl 07.30" tanpa jendela, tidak jelas apakah denda ≥08.06
masih berlaku.~~ Sudah dijawab langsung oleh Ust Ranto lewat disclaimer di
§1b: opsi **(a)** yang berlaku — Games 1 (bonus) dan denda telat **dua
aturan terpisah**, jendela Games 1 cukup **1 batas atas** (≤ 07.35, bukan
rentang 07.30–07.35):

| Jam datang | Games 1 (bonus Rp7.500) | Denda |
|---|---|---|
| ≤ 07.35 | ✅ dapat | tidak ada |
| 07.36 – 08.05 | ❌ tidak dapat | tidak ada |
| ≥ 08.06 (tanpa toleransi) | ❌ tidak dapat | Rp 7.500 |
| ≥ 08.06 s.d. 10.00, **dengan** toleransi lembur malam sebelumnya (≥20.00) | ❌ tidak dapat | tidak ada (toleransi) |
| > 10.00 meski ada toleransi | ❌ tidak dapat | Rp 7.500 |

Ini otomatis juga menjawab pertanyaan toleransi kerja-malam dari Mutia (§7
lama) — datang dari Ranto sendiri, bukan Mutia. Sisa yang perlu
dikonfirmasi cuma **sumber data "lembur sampai 20.00"** — lihat §7 poin 1
(baru) & asumsi di §1b.

### B48 — Checkpoint untuk Games 2 (bukti "titik pertama") — **REVISI setelah arahan Ranto**
Foto kerja yang ada (`work_reports.foto_sebelum/sesudah`) baru tersimpan
**saat submit laporan di akhir pengerjaan**, jadi tidak valid sebagai bukti
waktu *tiba*. Games 2 butuh checkpoint real-time. Rencana awal saya bikin
tombol baru terpisah — **Ranto koreksi**: pakai yang sudah ada saja, yaitu
slider **"Check-in Sekarang"** (`<x-teknisi-slider action="checkIn">`,
`resources/views/livewire/teknisi/order-detail.blade.php:176-180`) yang
memang sudah dipakai teknisi tiap kali tiba di lokasi customer (memicu
`TeknisiService::checkIn()`, mengisi tabel job-site `attendances` yang
sudah ada).
- **(a) (terpilih)** Saat `checkIn()` dipanggil **dan** ini adalah
  check-in **pertama** teknisi hari itu (baris `attendances` pertama untuk
  user+tanggal tsb) → slider minta 1 foto tambahan ("buka cover AC
  indoor") sebelum submit → dicatat sebagai bukti Games 2 ke
  `technician_incentives` (timestamp = waktu slider disubmit, dicocokkan
  ke `jam_games2_batas`). Check-in ke-2 dst hari itu → slider jalan seperti
  biasa, tanpa foto tambahan (bukan titik pertama lagi). **Tidak perlu
  tombol/halaman baru** — cukup perluas slider yang sudah ada.
- (b) Tombol terpisah "Upload Bukti Titik Pertama" (rencana awal) — lebih
  banyak langkah buat teknisi, ditinggalkan.

Rekomendasi & keputusan: **(a)**.

### B49 — Cuci/Perawatan Motor (Games 3) sebagai entitas baru
Tidak terkait Order/Customer sama sekali — aktivitas berdiri sendiri.
- **(a)** Tabel baru `motor_cleanings`: `tanggal`, daftar teknisi yang
  ikut (maks **2 orang**, divalidasi di form — bukan di DB constraint),
  foto bukti, dicatat lewat halaman/menu ringan di app teknisi (mis. dari
  menu Absensi juga, tombol terpisah "Catat Cuci Motor"). Bonus Rp3.000
  **per orang** yang tercatat di baris itu (1 atau 2 orang).
- (b) Tidak dibangun sebagai fitur digital — dicatat manual oleh admin di
  Rekap tanpa form khusus teknisi.

Rekomendasi: **(a)** — kalau tujuannya menggantikan pencatatan manual
seperti absensi lain, sebaiknya konsisten (teknisi input sendiri + foto).

### B50 — Prasyarat: penanda "klaim" di `orders` — **✅ Dikonfirmasi Ranto: selalu 1 kunjungan = 1 status**
Games 2/4/5/6 semua wajib **exclude** pekerjaan klaim, tapi sistem sekarang
**tidak punya kolom ini sama sekali**. Ranto konfirmasi: **1
kunjungan/order tidak pernah campur** klaim & bayar — selalu salah satu.
- **(a) (terpilih)** Tambah `is_klaim` (boolean, default false) di
  **`orders`** (bukan `order_items` — tidak perlu granular karena tidak
  pernah campur). Diisi manual oleh Admin/Teknisi saat input order/laporan
  (checkbox "Ini pekerjaan klaim/garansi, tidak ditagih"). Order dengan
  `is_klaim = true` **exclude total** dari hitungan titik (Games 4), unit
  (Games 6), & omset (Games 5).
- (b) Granular per `order_items` — ditinggalkan, tidak sesuai kenyataan
  lapangan (tidak pernah campur, jadi cuma nambah kompleksitas tanpa guna).

Rekomendasi & keputusan: **(a)**.

### B51 — Sumber kebenaran "jalan berdua" vs "jalan sendiri"
- **(a)** Dihitung otomatis per hari per teknisi: lihat semua `orders` yang
  dikerjakan teknisi itu hari itu (lewat `order_technicians`), ambil
  **jumlah teknisi berbeda** yang muncul bareng di order-order tsb — kalau
  konsisten 2 orang di semua titik hari itu → "berdua", kalau 1 → "sendiri".
  Kalau campur (kadang sendiri kadang bertiga dst) → tidak otomatis
  dihitung, ditandai butuh review Admin/HR manual.
- (b) Pakai `Team`/`team_members` (tim permanen) sebagai sumber kebenaran —
  lebih simpel tapi bisa salah kalau hari itu tim tidak lengkap (1 orang
  izin) sementara `team_id` order tetap tercatat tim yang sama.

Rekomendasi: **(a)** — lebih akurat merefleksikan siapa yang benar-benar
kerja bareng hari itu, meski butuh query, bukan kolom statis.

### B52 — Games 4 (bonus kepulangan: titik + jam)
Hitung otomatis saat teknisi absen pulang (scan sore): jumlah titik
(`orders` selesai hari itu, exclude klaim per B50) dibandingkan ambang dari
`attendance_settings` (9/berdua, 5/sendiri, atau "ALL titik" — perlu
definisi "ALL" = seluruh titik yang ditugaskan hari itu, lihat §7) **dan**
jam scan pulang ≤ ambang (17.45, dari pengaturan). Kalau lolos dua-duanya →
otomatis catat entri insentif Games 4 sesuai B51 (berdua/sendiri).

### B53 — Games 5 & 6 (omset & unit): otomatis penuh, atau semi-manual dulu?
Kedua Games ini baru bisa dihitung otomatis **setelah** B50 (penanda klaim)
ada — dan omset (Games 5) masih perlu definisi "omset jasa" yang persis
(lihat §7, apakah dari `payments.total_tagihan` atau `incomes`, exclude
sparepart atau tidak).
- **(a)** Bangun dulu **infrastrukturnya** (kolom ambang di
  `attendance_settings`, tabel ledger insentif §4) tapi hitungnya **manual**
  oleh Admin/HR di halaman Rekap (input nominal berdasar laporan harian yang
  sudah ada) sampai B50 & definisi omset dikonfirmasi — lalu diotomatiskan
  di fase berikut tanpa ubah skema.
- (b) Tunda total Games 5 & 6 sampai semua prasyarat clear.

Rekomendasi: **(a)** — supaya bonusnya tetap bisa dibayarkan dari hari
pertama (manual dulu), sambil otomatisasi menyusul.

### B54 — Games 7 (penilaian customer)
Pengirim sendiri bilang "sedang dipikirkan". **Tidak didesain di dokumen
ini** — dicatat sebagai usulan lanjutan terpisah begitu kriterianya ada
(lihat §8 Batas & Non-Tujuan).

### B55 — Verifikasi foto bukti (arahan Ranto: "foto yang diupload juga verifikasinya")
Semua Games berfoto (1/2/3/4) butuh foto sebagai syarat — tapi foto **ada**
tidak otomatis berarti foto itu **valid** (bisa salah lokasi, foto lama,
dsb). Ranto minta ada langkah verifikasi.
- **(a)** Tiap entri `technician_incentives` yang berfoto dibuat dengan
  `status_verifikasi = menunggu` dulu (nominal **sudah kehitung & tampil**
  di Rekap, supaya teknisi langsung tahu hasilnya — bukan `pending` yang
  menggantung). Admin/HR bisa buka Rekap Insentif → lihat foto → tombol
  **Setujui**/**Tolak** (+ catatan alasan kalau ditolak). Entri yang
  **ditolak** otomatis **dikeluarkan dari total** yang dipakai Finance
  hitung gaji (nominal tetap tersimpan untuk jejak, tapi tidak dihitung).
  Pola sama seperti `work_reports.diverifikasi_pada/diverifikasi_oleh` yang
  sudah ada di sistem.
- (b) Tidak ada verifikasi — nominal langsung final begitu teknisi submit
  foto, admin cuma bisa override manual lewat B45/catatan (kurang
  memuaskan permintaan Ranto).

Rekomendasi & keputusan: **(a)**.

## 4. Desain Teknis (ringkas)

> Direvisi setelah §1a/§1c: bonus/denda **tidak lagi** disimpan sebagai
> kolom tetap di `daily_attendances` (terlalu banyak kategori — 6 Games +
> denda telat). Dipindah ke **ledger generik** `technician_incentives` (satu
> baris = satu kejadian bonus/denda) supaya menambah kategori baru (Games 7
> dst) tidak perlu migrasi kolom baru lagi.

### Tabel baru
1. **`attendance_codes`** — riwayat kode/QR yang pernah dibuat.
   | Kolom | Tipe | Keterangan |
   |---|---|---|
   | id | bigint PK | |
   | kode | string(40) unique | token acak (`Str::random(40)`), dipakai di URL |
   | status | enum: aktif, nonaktif | hanya 1 baris `aktif` di satu waktu |
   | berlaku_sampai | datetime, nullable | expire otomatis; null = tidak expire (sampai dinonaktifkan manual) |
   | dibuat_oleh | FK → users | |
   | timestamps | | |

2. **`daily_attendances`** — absen kantor harian (1 baris/teknisi/tanggal).
   | Kolom | Tipe | Keterangan |
   |---|---|---|
   | id | bigint PK | |
   | user_id | FK → users | teknisi |
   | attendance_code_id | FK → attendance_codes, nullable | kode yg dipakai saat datang |
   | tanggal | date | |
   | jam_datang | datetime, nullable | |
   | foto_datang | string, nullable | path disk `public/absensi/` |
   | status_datang | enum: bonus, normal, telat | dihitung dari `attendance_settings` saat scan (Games 1 vs denda, B47) |
   | jam_pulang | datetime, nullable | |
   | foto_pulang | string, nullable | |
   | dikecualikan_denda | boolean, default false | override manual (B45) — meniadakan entri `denda_telat` di ledger hari itu |
   | catatan_admin | text, nullable | |
   | timestamps | | |
   | unique(user_id, tanggal) | | |

3. **`technician_incentives`** — ledger generik semua bonus/denda per
   teknisi per hari (baru, gantikan kolom nominal di `daily_attendances`).
   | Kolom | Tipe | Keterangan |
   |---|---|---|
   | id | bigint PK | |
   | user_id | FK → users | |
   | tanggal | date | |
   | kategori | enum: `games1_hadir`, `games2_titik_pertama`, `games3_cuci_motor`, `games4_kepulangan`, `games5_omset_tim`, `games6_unit`, `denda_telat`, `lainnya` | |
   | tipe | enum: bonus, denda | |
   | nominal | decimal | |
   | foto_bukti | string, nullable | path disk `public/absensi/` (kalau bukan dari `daily_attendances`, mis. Games 2/3) |
   | sumber | enum: otomatis, manual | manual dipakai Games 5/6 di fase awal (B53) |
   | referensi | text, nullable | catatan perhitungan (mis. id order-order yang dihitung, jumlah titik/unit/omset) |
   | dicatat_oleh | FK → users, nullable | terisi kalau `sumber = manual` |
   | catatan | text, nullable | |
   | status_verifikasi | enum: `menunggu`, `disetujui`, `ditolak` | default `menunggu` untuk entri berfoto (B55); entri tanpa foto (mis. hasil hitung Games 5/6 manual) langsung `disetujui` |
   | diverifikasi_pada | timestamp, nullable | pola sama seperti `work_reports.diverifikasi_pada` |
   | diverifikasi_oleh | FK → users, nullable | pola sama seperti `work_reports.diverifikasi_oleh` |
   | timestamps | | |
   | unique(user_id, tanggal, kategori) | | cegah dobel hitung otomatis per kategori per hari |

4. **`motor_cleanings`** — log cuci/perawatan motor (Games 3), berdiri
   sendiri dari Order/Customer.
   | Kolom | Tipe | Keterangan |
   |---|---|---|
   | id | bigint PK | |
   | tanggal | date | |
   | foto | string | path disk `public/absensi/` |
   | dicatat_oleh | FK → users | teknisi yang input |
   | timestamps | | |

   + tabel pivot **`motor_cleaning_technicians`** (`motor_cleaning_id`,
   `teknisi_id`) — maksimal 2 baris per `motor_cleaning_id`, divalidasi di
   form/service (bukan DB constraint, karena "maks 2" adalah aturan bisnis
   bisa berubah — lihat pola B24/B42).

5. **`attendance_settings`** — 1 baris pengaturan (pola `business_infos`),
   diperluas untuk menampung seluruh ambang Games (bukan cuma jam datang):
   | Kolom | Tipe | Keterangan |
   |---|---|---|
   | id | bigint PK (selalu 1) | |
   | jam_games1_batas | time | Games 1: batas atas hadir, default 07:35 (§1b, B47) |
   | nominal_games1 | decimal | default 7500 |
   | jam_normal_selesai | time | default 08:05 (setelah ini = telat/denda) |
   | nominal_denda_telat | decimal | default 7500 |
   | jam_toleransi_lembur_mulai | time | ambang "kerja sampai malam" hari sebelumnya, default 20:00 (§1b) |
   | jam_toleransi_batas_denda | time | batas denda mundur jadi ini kalau toleransi berlaku, default 10:00 (§1b) |
   | jam_games2_batas | time | default 08:30 |
   | nominal_games2 | decimal | default 7500 |
   | nominal_games3 | decimal | default 3000 (per orang) |
   | jam_games4_batas | time | default 17:45 |
   | minimal_titik_berdua | integer | default 9 |
   | minimal_titik_sendiri | integer | default 5 |
   | nominal_games4_berdua | decimal | default 15000 |
   | nominal_games4_sendiri | decimal | default 25000 |
   | omset_games5_minimal | decimal | default 850000 |
   | nominal_games5_berdua | decimal | default 40000 |
   | nominal_games5_sendiri | decimal | default 100000 |
   | unit_games6_berdua | integer | default 12 |
   | unit_games6_sendiri | integer | default 6 |
   | nominal_games6 | decimal | default 50000 |
   | timestamps | | |

### Prasyarat di tabel yang SUDAH ADA (B50)
- `orders`: tambah `is_klaim` (boolean, default false) — migrasi kecil
  terpisah di tabel existing, **wajib** sebelum Games 2/4/5/6 bisa dihitung
  otomatis dengan benar (exclude klaim; dikonfirmasi selalu 1 order = 1
  status, tidak pernah campur).

### Komponen baru
- `App\Models\AttendanceCode`, `App\Models\DailyAttendance`,
  `App\Models\AttendanceSetting`, `App\Models\TechnicianIncentive`,
  `App\Models\MotorCleaning`.
- `App\Services\AttendanceCodeService` — `buatBaru()` (nonaktifkan kode lama
  otomatis), `aktifkan()/nonaktifkan()`, `kodeAktifValid()` (cek status +
  `berlaku_sampai`).
- `App\Services\AttendanceService` — `catatDatang(User, kode)` (validasi
  kode aktif, guard kuota foto, hitung `status_datang` — termasuk cek
  toleransi lembur: kalau `jam_pulang` `daily_attendances` teknisi itu
  **kemarin** ≥ `jam_toleransi_lembur_mulai`, pakai
  `jam_toleransi_batas_denda` sebagai batas denda hari ini, bukan
  `jam_normal_selesai` — tulis entri `games1_hadir` dan/atau `denda_telat`
  ke ledger via `TechnicianIncentiveService`, tolak kalau hari ini sudah
  ada jam_datang),
  `catatPulang(User)` (guard kuota foto, hitung jumlah titik hari itu →
  tulis entri `games4_kepulangan` bila lolos ambang, tolak kalau belum ada
  jam_datang atau sudah ada jam_pulang).
- `App\Services\TechnicianIncentiveService` — `catat(kategori, user, tanggal,
  nominal, ...)` (idempoten via unique constraint, set `status_verifikasi =
  menunggu` kalau ada foto / `disetujui` kalau tidak — B55),
  `setujui()/tolak()` (verifikasi, B55), `hitungTitikHarian(User,
  tanggal)` (query `order_technicians` join `orders` status selesai, exclude
  klaim B50), `tentukanModeJalan(User, tanggal)` (berdua/sendiri, B51),
  `catatTitikPertama()` (Games 2, dipanggil dari slider check-in pertama —
  B48), `catatCuciMotor()` (Games 3, dari `MotorCleaning`). Games 5/6: input
  manual Admin/HR di fase awal (B53), lewat form di Rekap — bukan lewat
  service otomatis dulu.
- Filament — grup menu **"Absensi & Insentif"** (Admin/HR; Finance read):
  - Resource **Kode Absensi**: tabel riwayat kode, tombol "Buat Kode Baru"
    (tampilkan QR hasil generate untuk dicetak — pakai lib QR PHP ringan
    atau endpoint gambar QR eksternal-free/self-hosted, cek lisensi),
    toggle aktif/nonaktif, kolom kedaluwarsa.
  - Resource **Rekap Absensi**: read (+ override B45), filter
    tanggal/teknisi, tampil foto datang/pulang.
  - Resource **Rekap Insentif** (`technician_incentives`): read + tambah
    entri manual (Games 5/6 di fase awal, B53) + aksi **Setujui/Tolak**
    per baris berfoto (B55, hanya Admin/HR — Finance read saja), filter
    tanggal/teknisi/kategori/status verifikasi, kolom total per teknisi per
    periode (dasar hitung gaji Finance, otomatis exclude yang ditolak).
  - Resource **Cuci Motor** (`motor_cleanings`): read + tambah manual.
  - Page **Pengaturan Absensi**: form edit `attendance_settings`.
- Teknisi (Livewire) —
  - `App\Livewire\Teknisi\AbsensiScan`, route `GET /teknisi/absensi/{kode}`
    (middleware `auth, role:teknisi, user.aktif`, sama seperti route
    teknisi lain): validasi kode → tampilkan status hari ini (belum datang /
    sudah datang tunggu pulang / sudah lengkap) → form upload foto
    (`capture="environment"`) + tombol sesuai state ("Catat Datang" /
    "Catat Pulang"). Tambah menu di `teknisi-bottom-nav.blade.php`.
  - `OrderDetail` (existing) — perluas slider **"Check-in Sekarang"**
    (`action="checkIn"`, `order-detail.blade.php:176-180`): kalau ini
    check-in pertama teknisi hari itu, slider minta 1 foto tambahan
    sebelum submit (Games 2, B48) — bukan tombol/halaman baru.
  - Halaman/aksi ringan baru "Catat Cuci Motor" (Games 3, B49) — pilih diri
    sendiri + maks 1 rekan, upload foto.

### Storage & kuota
Folder `absensi/` di disk `public`, guard `StorageQuotaService::pastikanCukup()`
sebelum `store()` — pola sama seperti `OrderDetail::submitLaporan()` (lihat
`06-file-manager-foto-penyimpanan.md`).

## 5. Alur Pengguna

**A. Admin — kelola kode:**
1. Admin/HR buka menu Absensi & Insentif → Kode Absensi → "Buat Kode Baru".
2. Sistem nonaktifkan kode lama (kalau ada yang masih aktif), generate kode
   baru, tampilkan QR untuk dicetak/ditempel di kantor.
3. Admin bisa nonaktifkan kode kapan saja (mis. kertas rusak/dicurigai
   bocor) tanpa harus buat baru dulu.

**B. Teknisi — absen datang (Games 1 + cek denda, B47):**
1. Sampai kantor, scan QR pakai kamera HP → link terbuka di browser.
2. Kalau belum login → login dulu → diarahkan balik ke halaman absen.
3. Sistem cek kode aktif & belum expired; kalau tidak valid → pesan jelas
   ("Kode tidak berlaku, hubungi admin").
4. Kalau valid & belum absen datang hari ini → form upload foto → submit →
   sistem catat `jam_datang` = waktu submit, hitung status dari
   `attendance_settings` (termasuk cek toleransi lembur kemarin), tulis
   ledger `technician_incentives`, tampilkan hasil ("Games 1 — Bonus
   Rp 7.500" / "Normal, tidak dapat Games 1" / "Telat — Denda Rp 7.500,
   hubungi HR bila keberatan" / "Telat tapi ditoleransi (lembur kemarin) —
   tidak didenda").

**C. Teknisi — titik pertama (Games 2, B48):**
1. Sampai di rumah customer pertama hari itu, geser slider **"Check-in
   Sekarang"** seperti biasa. Karena ini check-in pertama hari itu, slider
   minta 1 foto tambahan ("buka cover AC indoor") sebelum submit.
2. Sistem cocokkan waktu submit ke `jam_games2_batas` → kalau lolos, tulis
   ledger `games2_titik_pertama`. Check-in ke-2 dst hari itu → slider jalan
   normal tanpa foto tambahan.

**D. Teknisi — cuci motor (Games 3, B49):**
1. Menu "Catat Cuci Motor" → pilih diri sendiri (otomatis) + opsional 1
   rekan (maks 2 total) → upload foto → submit.
2. Sistem tulis ledger `games3_cuci_motor` untuk tiap orang yang tercatat.

**E. Teknisi — absen pulang (Games 4, B52):**
1. Sore hari, scan QR yang sama (atau buka menu Absensi di app teknisi).
2. Kalau sudah absen datang & belum absen pulang → form upload foto →
   submit → catat `jam_pulang`.
3. Sistem hitung jumlah titik hari itu (exclude klaim, B50) + tentukan mode
   jalan (berdua/sendiri, B51) → kalau lolos ambang & jam → tulis ledger
   `games4_kepulangan` dengan nominal sesuai mode jalan.

**F. Admin/HR — Games 5 & 6 (fase awal, manual — B53):**
1. Buka Rekap Insentif → "Tambah Entri Manual" → pilih teknisi, tanggal,
   kategori (`games5_omset_tim`/`games6_unit`), nominal, catatan
   perhitungan.
2. (Fase berikutnya, setelah B50 klaim & definisi omset jelas) dihitung
   otomatis seperti Games 1/2/4.

**G. Finance — hitung gaji (di luar sistem untuk saat ini):**
1. Buka Rekap Insentif, filter per teknisi per periode gaji, lihat total
   bonus/denda per kategori untuk dasar hitung gaji manual. (Integrasi
   otomatis ke modul payroll: **tidak ada modul payroll saat ini** di
   sistem — lihat §8, jadi di luar cakupan versi ini.)

## 6. Definisi "Selesai" — dipecah per fase (lihat §9 untuk urutannya)

**Fase A+B (Games 1, denda telat, Games 4 — siap dieksekusi sekarang, semua konfirmasi sudah masuk):**
1. Admin/HR bisa generate kode baru (otomatis nonaktifkan yang lama),
   aktifkan/nonaktifkan manual, lihat kedaluwarsa.
2. Teknisi scan kode aktif & valid → bisa upload foto datang (kalau belum
   absen hari itu) lalu foto pulang (kalau sudah absen datang & belum
   pulang); kode nonaktif/expired/salah → ditolak dengan pesan jelas.
3. Ledger `technician_incentives` terisi otomatis untuk `games1_hadir` /
   `denda_telat` saat absen datang, dan `games4_kepulangan` saat absen
   pulang (hitung titik + mode jalan) — seluruhnya berdasarkan
   `attendance_settings` (bukan hardcode); ubah nominal/jam/ambang di
   halaman Pengaturan langsung berlaku tanpa deploy.
4. Admin/HR bisa override `dikecualikan_denda` per baris + catatan.
5. Rekap Absensi & Rekap Insentif tampil untuk Owner/Admin/HR/Finance, foto
   bisa dilihat.
6. Foto (absensi, titik pertama, cuci motor) ikut guard kuota penyimpanan
   (B25), gagal upload saat penuh → pesan jelas, tidak membuat baris korup.
7. Entri berfoto masuk dengan `status_verifikasi = menunggu`; Admin/HR bisa
   Setujui/Tolak di Rekap Insentif; yang ditolak otomatis tidak masuk total
   gaji (B55).
8. Test Pest hijau (service kode, service catat datang/pulang + hitung
   ledger, hitung titik/mode-jalan, verifikasi, guard kuota, Filament
   resource dasar).

**Fase B lanjut (Games 2 & 3):**
8. Slider "Check-in Sekarang" minta foto tambahan tepat di check-in
   **pertama** teknisi hari itu (B48), tulis ledger `games2_titik_pertama`
   sesuai ambang jam.
9. "Catat Cuci Motor" bisa dipakai teknisi, maks 2 orang per baris, tulis
   ledger `games3_cuci_motor` per orang.

**Fase C (Games 5 & 6 — perlu definisi omset dari §7):**
10. `orders.is_klaim` ada & bisa diisi Admin/Teknisi.
11. Entri manual Games 5/6 bisa ditambah Admin/HR di Rekap Insentif
    (langkah awal, B53); dihitung otomatis menyusul di fase berikutnya
    tanpa ubah skema.

**Di luar cakupan seluruh fase ini:** Games 7 (§7/B54), modul payroll
otomatis (§8).

## 7. Pertanyaan Terbuka — sisa 1, non-blocking

**✅ Terjawab (17 September 2026, langsung dari Ust Ranto):**
- ~~B47 — jendela Games 1 & status denda~~ → jendela Games 1 = ≤ 07.35
  (1 batas atas), denda ≥08.06 tetap terpisah & berlaku.
- ~~Toleransi telat tanpa potong gaji~~ → batas denda mundur ke 10.00 kalau
  hari sebelumnya lembur sampai 20.00.
- ~~Sumber data "lembur sampai 20.00"~~ → **pakai `jam_pulang` absen kantor**
  hari sebelumnya (rekomendasi saya, dikonfirmasi). Berlaku 1 hari saja.
- ~~Tanggal efektif~~ → berlaku sejak tanggal itu juga — dibaca sebagai:
  skema sudah jalan manual, sistem digital ini **mulai hitung otomatis
  sejak fitur dirilis** (tidak ada backdate data historis; lihat catatan
  di bawah).
- ~~"Bracci2"~~ → cuma istilah/sebutan mereka untuk "bonus", bukan konsep
  berbeda — di sistem dicatat sebagai kategori `games5_omset_tim`, label
  tampilan boleh tetap pakai kata "Bracci2" sesuai istilah mereka.
- ~~B50 (klaim campur dalam 1 order)~~ → **tidak pernah campur**, `is_klaim`
  cukup di level `orders` (lihat B50, direvisi).
- ~~Games 4 "ALL TITIK"~~ → titik yang **ditugaskan** ke teknisi/tim hari
  itu.
- ~~Nominal Games 1–6 beda per orang?~~ → **sama rata** untuk semua teknisi.
- ~~Lokasi barcode~~ → 1 lokasi kantor untuk sekarang, tapi desain
  disiapkan untuk tambah lokasi lain nanti (lihat B41, kolom `lokasi`
  ditambah dari awal).
- ~~B48 (checkpoint Games 2 ganggu alur kerja?)~~ → tidak pakai tombol
  baru — dipindah ke slider "Check-in Sekarang" yang sudah dipakai teknisi
  sehari-hari (lihat B48, direvisi). Sekalian dijawab: foto yang diupload
  (Games 1–4) perlu **verifikasi** Admin/HR sebelum final dihitung ke gaji
  (lihat B55, baru).

**Catatan pribadi soal tanggal (belum benar-benar "terjawab", cuma saya
ambil jalan paling aman):** jawaban "mulai tanggal itu juga" masih belum
menghilangkan kejanggalan tanggal **22 April 2026** (sebelum tanggal chat
ini). Karena tidak ada cara aman untuk "backdate" perhitungan bonus/denda
otomatis ke tanggal yang sudah lewat tanpa data historis yang lengkap
(fitur ini belum ada saat itu), saya asumsikan: **sistem mulai hitung
Games sejak tanggal fitur ini dirilis ke teknisi**, bukan mundur ke 22
April. Kalau ternyata ada kewajiban bayar retroaktif ke periode April–Sep
2026 dari catatan manual mereka, itu **dihitung manual oleh HR/Finance di
luar sistem** (entri manual di Rekap Insentif kalau perlu), bukan
otomatis. Tolong dikoreksi kalau pemahaman ini salah.

**Sisa 1 pertanyaan (tidak menghalangi eksekusi Fase A–C, cuma memengaruhi
akurasi Games 5 di Fase C):**
1. **Games 5 (definisi omset)** — "omset jasa kerja tim" itu dari total
   tagihan (`payments.total_tagihan`) apa adanya, atau exclude biaya
   sparepart/material (cuma jasa servis murni)? (Rentang jam "PKL" untuk
   Games 5 sudah dikonfirmasi **sama** dengan Games 4, 17.45.)

**Kesimpulan:** semua blocker utama sudah terjawab. Fase A, B, dan
B-lanjut (§9) **siap dieksekusi sekarang** tanpa menunggu apa pun lagi.
Fase C (Games 5 & 6 otomatis) tinggal menunggu 1 poin di atas — entri
manual Games 5/6 sendiri tetap bisa jalan duluan (B53) sambil menunggu.

## 8. Batas & Non-Tujuan (versi ini)

- **Bukan modul payroll/gaji.** Sistem ini menghasilkan **ledger bonus/denda
  per hari** (`technician_incentives`) untuk dipakai HR/Finance secara
  manual saat hitung gaji bulanan — tidak ada tabel `payroll`/slip gaji
  otomatis (tidak ada modul ini sama sekali di sistem sekarang; kalau
  dibutuhkan, jadi usulan terpisah, lihat Fase D §9).
- Bukan sistem lokasi GPS/geofencing — validasi "teknisi benar-benar di
  kantor" mengandalkan kode fisik yang ditempel di sana (B41), bukan
  koordinat GPS. Bisa jadi usulan lanjutan bila dirasa kurang.
- **Games 7 (penilaian customer) tidak didesain di dokumen ini** — pengirim
  sendiri bilang "sedang dipikirkan" (B54). Usulan terpisah begitu
  kriterianya ada.
- Foto absensi/insentif **tidak** ikut pembersihan otomatis 60 hari (B44a)
  — kalau ke depan dianggap perlu retensi, dibahas terpisah.
- Barcode/QR generate: pakai library ringan untuk render gambar QR dari
  string kode (perlu dicek pilihan yang cocok untuk PHP/Laravel saat
  eksekusi — belum dipilih di dokumen ini).
- Games 5 & 6 **otomatis penuh** (bukan cuma entri manual) ditunda sampai
  B50 (klaim) & definisi omset (§7 poin 5) clear — lihat B53.

## 9. Urutan Eksekusi Usulan

1. **✅ Fase A (SELESAI 17 Sep 2026)** — migrasi tabel `attendance_codes`,
   `daily_attendances`, `attendance_settings`, `technician_incentives` +
   model + `AttendanceCodeService` + `AttendanceSettingService` + Filament
   "Kode Absensi" (generate/aktif/nonaktif, QR via `endroid/qr-code`) +
   halaman "Pengaturan Absensi" (§4). 22 test Pest hijau
   (`tests/Feature/AttendanceFaseATest.php`), suite penuh 504 passed.
2. **✅ Fase B (SELESAI 17 Sep 2026)** — `AttendanceService` +
   `TechnicianIncentiveService` (catat datang/pulang, hitung Games 1/4 +
   denda telat + toleransi lembur malam dengan asumsi sumber data §7 poin
   1, hitung titik & mode jalan via `order_technicians` per B51) + halaman
   scan teknisi (`App\Livewire\Teknisi\AbsensiScan`, route
   `/teknisi/absensi/{kode?}` — kode opsional: dari QR utk absen datang,
   tanpa kode dari menu bottom-nav utk absen pulang/lihat status) + guard
   kuota + Rekap Absensi & Rekap Insentif (Filament, read + override B45 +
   tambah manual B53 + verifikasi Setujui/Tolak B55). 31 test Pest hijau
   (`tests/Feature/AttendanceFaseBTest.php`), suite penuh 535 passed.
3. **✅ Fase B-lanjut (SELESAI 17 Sep 2026)** — slider "Check-in Sekarang"
   (`OrderDetail`) diperluas: check-in job-site PERTAMA teknisi hari itu
   minta foto tambahan, ditulis ke ledger `games2_titik_pertama` bila masih
   dalam ambang jam (B48). Fitur baru "Catat Cuci Motor" di menu Absensi
   (`AbsensiScan`) + tabel `motor_cleanings`/`motor_cleaning_technicians` +
   `MotorCleaningService` — maks 2 orang/motor, ledger `games3_cuci_motor`
   per orang, **akumulasi** (bukan timpa) kalau dicatat 2x sehari (pagi &
   sore) (B49). Menu Filament "Cuci Motor" (read + tambah manual). 16 test
   Pest hijau (`tests/Feature/AttendanceFaseBLanjutTest.php`) + 1 test lama
   diperbarui (`TeknisiMobileUiTest.php`, checkIn kini butuh
   `fotoTitikPertama` pada check-in pertama hari itu). Suite penuh 551
   passed.
4. **✅ Fase C (SELESAI 18 Sep 2026)** — migrasi `orders.is_klaim` (B50,
   default false) + toggle "Ini pekerjaan klaim/garansi" di form admin
   (`OrderResource`/`CreateOrder` → `OrderService::createOrder`) **dan** di
   form laporan teknisi (`OrderDetail::submitLaporan` →
   `TeknisiService::submitLaporan`) — keduanya bisa menandai, sesuai B50.
   `TechnicianIncentiveService::hitungTitikHarian()` &
   `tentukanModeJalan()` sekarang **exclude order klaim** (Games 4 sudah
   otomatis pakai ini sejak Fase B; Games 5/6 ikut terpakai begitu
   otomatisasinya jalan). Entri manual Games 5/6 di Rekap Insentif
   (Fase B, B53) tetap jadi jalan sementara. 7 test Pest hijau
   (`tests/Feature/AttendanceFaseCTest.php`), suite penuh 558 passed.
5. **Fase C-lanjut** — otomatisasi penuh Games 5 & 6 (setelah datanya
   tervalidasi manual beberapa minggu).
   — **Syarat mulai:** definisi omset persis (§7 poin 4, exclude sparepart
   atau tidak) dikonfirmasi — prasyarat klaim (B50) sudah selesai.
6. **Fase D (opsional, menyusul, di luar cakupan sekarang)** — Games 7
   (B54) & integrasi ke modul payroll bila dibangun nanti.
