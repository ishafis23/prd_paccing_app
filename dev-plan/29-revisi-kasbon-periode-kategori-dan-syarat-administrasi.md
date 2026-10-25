# 29 — Revisi Teknisi: Kasbon Ikut Periode Gaji, Kategori Pengeluaran Baru, dan 3 Syarat Administrasi Sebelum Bertugas

> STATUS: **USULAN — DISETUJUI PEMILIK UNTUK DIEKSEKUSI** (25 Okt 2026).
> Sumber: pesan client (WhatsApp, via pemilik proyek), 25 Okt 2026.
> Eksekusi: agen CLI Claude Code, brief di `_brief/10-…md` dan `_brief/11-…md`; review terpisah oleh agen kedua (`_brief/12-…md`).

## 1. Kutipan permintaan client

> "untuk kasbon, bisa mengikut periode teknisi/gaji, seperti potongan denda, bonus dll.
> Soalnya ini (19 sept) bayu, sudah terbayar di gaji sebelumnya.
> Periode/batas bayu tgl 23, hitungan untuk gaji bulan ini mulai tgl 24 sept - 23 okt,
> untuk menu 'Pengeluaran Teknisi' kategori transport diubah jadi ⚙️Perawatan
> Kemudian tambah kategori 💵Gaji/Honor 🎁Bonus.
> Kepastian 3 Hal administrasi sebelum teknisi bertugas:
> 1. Laporan pengerjaan tuntas dikerja oleh teknisi dan dikirim ke customer oleh Admin/Sistem
> 2. Laporan Pengeluaran harus tuntas
> 3. Laporan penyerahan dana tunai jika ada transaksi tunai
> Jadi, SPK atau orderan bisa dikerjakan oleh teknisi jika mereka telah selesaikan 3 hal ini, ini untuk poin 3 ust,
> jadi jika Teknisi klik pembayaran tunai/cash. Maka harus ada laporan foto/dokumentasi setor ke siapa dan nominalnya brp.
> Sama seperti jika orderan tdk selesai, maka besoknya teknisi tdk bisa akses kalau belum setor tunai."

Dipecah menjadi tiga pekerjaan:

| # | Permintaan | Inti masalah |
|---|---|---|
| A | Kasbon mengikuti periode gaji | Kasbon tanggal 19 Sep (Bayu) **sudah** dipotong di gaji periode sebelumnya tetapi masih ikut terpotong lagi di periode 24 Sep–23 Okt |
| B | Kategori Pengeluaran Teknisi | `transport` → `⚙️ Perawatan`; tambah `💵 Gaji/Honor` dan `🎁 Bonus` |
| C | 3 syarat administrasi | Teknisi tidak boleh mulai order baru sebelum: laporan pengerjaan tuntas **dan** terkirim ke customer, laporan pengeluaran tuntas, serta setoran tunai dilaporkan (foto + penerima + nominal) |

## 2. Kondisi kode saat ini (terverifikasi 25 Okt 2026)

| Fakta | Bukti |
|---|---|
| Periode gaji dihitung dari tanggal yang dipilih Admin: `[mulai, mulai + 1 bulan − 1 hari]` — **tidak ada** tanggal cut-off per teknisi | `app/Services/PayrollService.php:57` (`rentangPeriode()`); form "Hitung Gaji" `app/Filament/Resources/PayrollRunResource.php:115` |
| Profil gaji teknisi hanya punya `tanggal_masuk`, gaji pokok, BPJS, potongan OFF, denda telat | `database/migrations/2026_10_17_000001_create_technician_payroll_profiles_table.php` |
| Potongan kasbon mengambil **semua** kasbon aktif dengan `tanggal <= akhir periode`, tanpa melihat periode asal kasbon → kasbon lama ikut terpotong lagi | `app/Services/PayrollService.php:135-160` (`alokasiKasbon()`) |
| Pelunasan kasbon baru dicatat saat gaji di-ACC; `payroll_run_id` **wajib** terisi sehingga tidak ada jalur melunasi kasbon di luar gaji | `app/Services/PayrollService.php:429`; migrasi `2026_10_19_000005` |
| Kategori pengeluaran teknisi: `bensin, makan, material, transport, lainnya`, daftar ditulis ulang di 3 tempat + 1 blade | `app/Models/TeknisiExpense.php:15`; `app/Livewire/Teknisi/LaporanPengeluaran.php:20`; `app/Livewire/Admin/ManajemenPengeluaranTeknisi.php:51,316`; `app/Http/Controllers/TeknisExpenseController.php:19,356`; `resources/views/livewire/teknisi/laporan-pengeluaran.blade.php:77,209,270` |
| Rekap Bulanan Dana **sudah** punya keranjang `perawatan` tetapi selalu kosong karena tidak ada kategori bernama itu | `app/Services/LaporanRekapService.php:51` |
| Gate keberangkatan hari ini hanya memeriksa **foto wajib** order yang belum ditutup | `app/Services/TeknisiService.php:39-96` |
| Belum ada konsep setoran tunai per order. `setoran` yang ada hanya angka harian di laporan harian teknisi | `app/Livewire/Teknisi/DailyReport.php:53`; migrasi `2026_09_29_143816` |
| Metode pembayaran tunai sudah tercatat: `orders.metode_dipilih` (`cash/transfer/qris/ewallet`), tanpa cap tanggal dipilih | `app/Enums/PaymentMethod.php`; `TeknisiService::catatMetodeDipilih()` (`:687`) |
| Belum ada penanda "laporan pengerjaan sudah dikirim ke customer"; yang ada baru kirim resi/invoice & notifikasi berangkat | `app/Services/NotifikasiWaService.php` (hanya `kirimBerangkat`, `kirimPembayaranLunas`) |
| Test suite: 98 file fitur. Baseline merah **16** (13 warisan + 3 karena `WA_TEST_TARGET` terisi di `.env`) | `_log/baseline-*.log`, `php artisan test` |

## 3. Keputusan yang dipakai (bukan menu pilihan)

### A1. Tanggal cut-off periode gaji per teknisi
- Kolom baru `technician_payroll_profiles.cutoff_hari` (1–31, nullable).
- **Bila terisi** (Bayu = 23): periode = **tanggal 24 bulan lalu s.d. tanggal 23 bulan berjalan** yang memuat tanggal acuan. Contoh: acuan 1 Okt 2026 → 24 Sep 2026 – 23 Okt 2026.
- **Bila kosong**: perilaku lama dipertahankan apa adanya (`acuan` s.d. `acuan + 1 bulan − 1 hari`) supaya gaji teknisi lain & seluruh test lama tidak berubah.
- Form "Hitung Gaji" berubah label menjadi **"Tanggal acuan periode"** + helper text yang menampilkan periode hasil hitungan (mis. "Periode: 24 Sep 2026 – 23 Okt 2026").
- Alasannya: satu-satunya cara agar "batas 23" berlaku tanpa mengubah perilaku teknisi yang belum diatur, dan tanpa menulis ulang 12 test penggajian yang ada.

### A2. Kasbon hanya boleh dipotong pada periode asalnya
- Periode asal kasbon = periode gaji (aturan A1) yang **memuat** `technician_advances.tanggal`. Dihitung saat dibutuhkan (tidak disimpan) lewat `PayrollService::periodeKasbon()`.
- Aturan `alokasiKasbon(user, periode_mulai, periode_selesai, batas)`:
  1. Kasbon dengan periode asal **sesudah** periode gaji yang dihitung → **tidak** dipotong.
  2. Kasbon dengan periode asal **sama** → dipotong `min(sisa, cicilan_per_periode atau sisa, sisa plafon)`.
  3. Kasbon dengan periode asal **sebelum** periode gaji ini:
     - **sudah pernah dipotong** (ada pelunasan) → lanjut dicicil sisanya (perilaku lama untuk kasbon multi-cicilan).
     - **belum pernah dipotong sama sekali** → **tidak** dipotong lagi (periodenya sudah tutup), ditandai **"Belum terpotong (periode lampau)"** di menu Kasbon; Admin memutuskan lewat aksi baru.
- Aksi Admin baru di menu Kasbon: **"Tandai sudah dibayar/dipotong di periode lalu"** → mencatat pelunasan manual (kolom `payroll_run_id` dibuat *nullable*, tambah `catatan` & `dicatat_oleh`) dengan nominal bebas ≤ sisa. Ini yang dipakai untuk kasus Bayu 19 Sep.
- Ini memperbaiki keluhan langsung: kasbon 19 Sep untuk Bayu (cut-off 23) masuk periode 24 Agu–23 Sep, jadi **tidak** muncul lagi di periode 24 Sep–23 Okt. Kalau periode 24 Agu–23 Sep sudah di-ACC tanpa memotongnya, kasbon tampil sebagai "Belum terpotong" dan Admin menandainya lunas/manual.

### A3. Kategori Pengeluaran Teknisi
- Kategori final: `bensin`, `makan`, `material`, `perawatan`, `gaji_honor`, `bonus`, `lainnya`.
- Label: `⛽ Bensin`, `🍜 Makan`, `🔧 Material`, `⚙️ Perawatan`, `💵 Gaji/Honor`, `🎁 Bonus`, `📦 Lainnya`.
- Migrasi data: `UPDATE teknisi_expenses SET kategori='perawatan' WHERE kategori='transport'`. Tabel legacy `teknis_expenses` (model `TeknisExpense`, enum MySQL, controller sudah rusak & test-nya merah) **tidak disentuh**.
- Daftar kategori di 4 tempat diganti menjadi satu sumber: `TeknisiExpense::KATEGORI` + `kategoriLabel()`, dan aturan validasi disusun dari konstanta itu (bukan string `in:...` yang ditulis ulang).
- Rekap Bulanan Dana: `gaji_honor` dan `bonus` **tidak** dijumlahkan ke Operasional Rutin (sumber resmi gaji/bonus tetap `payroll_runs` → kalau ikut dijumlah akan dobel). Keduanya hanya tampil sebagai baris informasi.

### C1. Tiga syarat administrasi (gate)
Service baru `app/Services/SyaratAdministrasiService.php` → `penghalang(User $teknisi): array` berisi daftar penghalang terstruktur (`jenis`, `order_id`, `pesan`). Jenis:

1. **`foto`** — order teknisi berstatus `selesai`/`butuh_followup` & `ditutup_pada` null yang foto wajibnya kurang (logika lama `fotoWajibKurang`, dipindah apa adanya).
2. **`laporan`** — order yang sama tetapi `orders.laporan_terkirim_pada` masih null. Dianggap tuntas bila laporan pengerjaan sudah dikirim ke customer (otomatis oleh sistem saat kirim WA, atau ditandai Admin). Kolom baru `laporan_terkirim_pada` + `laporan_terkirim_oleh`.
3. **`pengeluaran`** — masih ada `teknisi_expenses` berstatus `pending` milik teknisi itu yang terkait order tersebut **atau** bertanggal ≤ tanggal order itu selesai.
4. **`setoran`** — order di mana teknisi menandai pembayaran **tunai** (`orders.metode_dipilih='cash'`) tetapi belum ada `cash_handovers` berstatus `menunggu`/`diterima`; menjadi penghalang mulai **hari berikutnya** setelah `orders.metode_dipilih_pada` (kolom baru) — jadi tidak memblokir di hari yang sama saat uang baru diterima.

Penerapan gate:
- `TeknisiService::berangkat()` dan `TeknisiService::checkIn()` → tolak (`BusinessRuleException`) dengan pesan yang menyebut order, jenis, dan nilainya.
- Halaman `JadwalHariIni` (beranda portal teknisi) → tampilkan panel "Administrasi belum tuntas" berisi daftar penghalang; bila ada penghalang jenis `setoran` dari hari sebelumnya → **daftar order tidak dirender** (layar tertahan) dan tombol menuju "Setoran Tunai".
- `OrderDetail::mount()` → alihkan ke halaman setoran bila teknisi punya tunggakan setoran hari sebelumnya.
- Halaman **Lengkapi Laporan** dan **Setoran Tunai** selalu bisa dibuka (jalan keluar teknisi).

### C2. Setoran tunai (poin 3)
- Tabel baru `cash_handovers`: `order_id`, `teknisi_id`, `tanggal_setor`, `nominal`, `diserahkan_ke`, `foto_path`, `catatan`, `status` (`menunggu`/`diterima`/`ditolak`), `diterima_oleh`, `diterima_pada`, `catatan_verifikasi`, timestamps.
- Enum `App\Enums\CashHandoverStatus`, model `App\Models\CashHandover`, service `App\Services\CashHandoverService`.
- Portal teknisi: halaman baru **Setoran Tunai** (`/teknisi/setoran`, tambah di bottom-nav) + form di detail order saat metode tunai: nominal, diserahkan ke siapa, foto bukti (kamera/galeri seperti upload lain), catatan.
- Admin: halaman Filament **Setoran Tunai** (grup Keuangan) — filter status/teknisi/tanggal, aksi Terima/Tolak + catatan. Status `ditolak` mengembalikan kewajiban ke teknisi.
- Admin: aksi di Order — **"Kirim Laporan Pengerjaan ke Customer"** (WA dokumen PDF laporan, memakai `LaporanPdfService` + `WhatsappService::kirimDokumen`) yang mengisi `laporan_terkirim_pada`, dan **"Tandai Laporan Terkirim (manual)"** bila dikirim di luar aplikasi.

## 4. Yang TIDAK dikerjakan di gelombang ini

- Pengajuan kasbon dari portal teknisi (tetap Admin yang input, sesuai dev-plan/28).
- Verifikasi setoran tunai tidak memblokir teknisi (cukup laporannya ada); keputusan penerimaan tetap wewenang Admin.
- Tabel legacy `teknis_expenses` / endpoint `TeknisExpenseController` (sudah rusak sebelum perubahan ini, test-nya termasuk 13 merah warisan).
- Perubahan tampilan slip gaji di luar tambahan baris keterangan periode & kasbon yang belum terpotong.

## 5. Rencana eksekusi (2 fase + review)

### Fase 1 — Kategori + Cut-off + Kasbon  (`_brief/10-…md`)
Migrasi: `add_cutoff_hari_ke_technician_payroll_profiles`, `rename_kategori_transport_ke_perawatan_dan_tambah_kategori`, `buat_payroll_run_id_nullable_di_advance_repayments`.
Berkas utama: `PayrollService`, `TeknisiExpense`, `TechnicianAdvance`, `TechnicianAdvanceRepayment`, resource Filament `TechnicianPayrollProfileResource` / `PayrollRunResource` / `TechnicianAdvanceResource`, 2 komponen Livewire pengeluaran + 1 controller + blade, `LaporanRekapService`.
Test baru: `PayrollCutoffTest`, `KasbonPeriodeTest`, `KategoriPengeluaranTeknisiTest`.

### Fase 2 — Syarat administrasi + setoran tunai  (`_brief/11-…md`)
Migrasi: `create_cash_handovers_table`, `add_laporan_terkirim_dan_metode_dipilih_pada_ke_orders`.
Berkas utama: `SyaratAdministrasiService`, `CashHandoverService`, enum + model + halaman Filament, `TeknisiService` (berangkat/checkIn), `Order`, `OrderDetail` + blade, `JadwalHariIni` + blade, halaman baru `SetoranTunai`, routes, bottom-nav, `NotifikasiWaService::kirimLaporanPengerjaan`, aksi di `OrderResource`.
Test baru: `SyaratAdministrasiTest`, `CashHandoverTest`, `LaporanTerkirimGateTest`.

### Fase 3 — Review independen + dokumen
- Agen kedua (`_brief/12-…md`) mereview diff: aturan bisnis, keamanan/kepemilikan data (teknisi hanya order sendiri), migrasi aman untuk MySQL produksi, tidak ada `migrate:fresh`, tidak ada `.env`, tidak ada penulisan di luar folder proyek.
- Supervisor (Hermes) menjalankan sendiri: `php artisan migrate` di sqlite lokal, seluruh test, dan pemeriksaan tangan (bukan klaim agen). Setelah hijau: commit + push, lalu laporan capaian `dev-plan/29-laporan-eksekusi.md`.

## 6. Verifikasi yang harus lolos

| Uji | Cara | Harapan |
|---|---|---|
| Periode cut-off 23 | `php artisan test --filter=PayrollCutoffTest` | acuan 2026-10-01 → 24 Sep–23 Okt; acuan 2026-09-23 → 24 Agu–23 Sep |
| Kasbon tidak dobel | `--filter=KasbonPeriodeTest` | kasbon 19 Sep tidak dipotong di run 24 Sep–23 Okt; tetap dipotong di run 24 Agu–23 Sep |
| Kasbon lewat periode | `--filter=KasbonPeriodeTest` | kasbon belum terpotong & periode lampau → tidak dipotong, tampil "Belum terpotong", aksi manual melunasi |
| Kategori | `--filter=KategoriPengeluaranTeknisiTest` | `perawatan`, `gaji_honor`, `bonus` diterima; baris lama `transport` sudah pindah ke `perawatan`; rekap tidak dobel |
| Gate | `--filter=SyaratAdministrasiTest` | berangkat/check-in ditolak untuk tiap jenis penghalang, lolos setelah tuntas |
| Setoran | `--filter=CashHandoverTest` | lapor setoran wajib foto+nominal+penerima; blokir hari berikutnya; verifikasi admin |
| Tidak ada regresi | `php artisan test` (full) | tetap **16 merah** dengan susunan yang sama, bukan lebih |

## 7. Keputusan yang masih perlu diputuskan client (dengan usulan default)

1. **Cut-off teknisi lain.** Usulan: kolom cut-off per teknisi, default kosong = perilaku sekarang; Bayu diisi 23 oleh Admin. (Butuh daftar cut-off teknisi lain dari client.)
2. **Kasbon yang sudah dibayar di gaji lalu tetapi belum tercatat di sistem** (kasus Bayu 19 Sep). Usulan: Admin memakai aksi "Tandai sudah dibayar/dipotong di periode lalu" — bukan menghapus kasbon, supaya jejaknya tetap ada.
3. **Gaji/Honor & Bonus di Pengeluaran Teknisi.** Usulan: dicatat sebagai pengeluaran (bisa dipakai bukti bayar honor/bonus di luar penggajian) tetapi **tidak** dijumlahkan ke Rekap Bulanan Dana, karena gaji & bonus resmi sudah diambil dari menu Penggajian.
4. **Kapan gate benar-benar mengunci layar.** Usulan: order yang belum tuntas mengunci **keberangkatan & check-in**; tunggakan **setoran tunai** mengunci seluruh portal teknisi sampai dilaporkan (sesuai kalimat client "besoknya teknisi tidak bisa akses").
