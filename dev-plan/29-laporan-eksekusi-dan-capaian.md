# 29 — Laporan Eksekusi & Capaian (Revisi Teknisi, 25 Okt 2026)

> Berkas ini ditulis **supervisor** (sesi Hermes) setelah semua fase dieksekusi.
> Acuan: `dev-plan/29-revisi-kasbon-periode-kategori-dan-syarat-administrasi.md`.
> Isinya: apa yang dikerjakan, apa yang **saya buktikan sendiri dengan menjalankan**, apa yang
> menyimpang dari teks plan, dan apa yang **belum** selesai.

---

## 1. Cara kerja (siapa mengerjakan apa)

- **Fase 1** (kategori, cut-off, kasbon): agen CLI, brief `_brief/10-…md`, laporan `_brief/laporan-10.md`.
  Commit `93578ec`.
- **Fase 2 sisi teknisi** (gate + halaman Setoran Tunai): agen CLI kedua, brief `_brief/11b-…md`,
  laporan `_brief/laporan-11b.md`. Commit `864062d` (pondasi) + `b36008e`.
- **Fase 2 sisi admin** (verifikasi setoran + kirim laporan ke customer): brief `_brief/11c-…md`,
  laporan `_brief/laporan-11c.md`. Commit `f451589`.
- **Supervisor** (sesi ini): menulis brief, menjalankan sendiri **semua** test dan perintah
  verifikasi, membaca diff, memperbaiki 4 regresi yang ditemukan saat suite penuh, menambah test
  yang kurang, lalu commit + push. Agen **tidak** bisa menjalankan PHP di mesin ini (php tidak ada
  di PATH sesi agen), jadi **angka di laporan agen tidak dipakai sebagai bukti** — semua angka di
  bawah ini keluar dari mesin saya.
- Aturan yang dipaksakan ke agen: dilarang menjalankan perintah git, dilarang `migrate:fresh` /
  `rollback` / `db:wipe`, dilarang mengubah `.env`, dilarang `composer update`, dilarang menghapus
  berkas di `storage/`.

---

## 2. Status per permintaan client

| # | Permintaan client | Hasil | Bukti |
|---|---|---|---|
| A | Kasbon mengikuti periode gaji (kasus Bayu: kasbon 19 Sep sudah dibayar di gaji sebelumnya, tapi terpotong lagi di periode 24 Sep–23 Okt) | `technician_payroll_profiles.cutoff_hari`; periode gaji teknisi = `24 bulan lalu s.d. 23 bulan berjalan` bila cut-off 23, dan **perilaku lama** bila kosong. Kasbon hanya dipotong di periode yang memuat tanggalnya; kasbon periode lampau yang belum pernah dipotong **tidak** dipotong lagi dan diberi aksi manual "Tandai sudah dibayar di periode lalu" (`payroll_run_id` kini boleh kosong + `catatan` + `dicatat_oleh`). | `tests/Feature/PayrollCutoffTest.php`, `KasbonPeriodeTest.php`, `Devplan29PanelKasbonTest.php`; commit `93578ec` |
| B | Kategori Pengeluaran Teknisi: `transport` → ⚙️ Perawatan, tambah 💵 Gaji/Honor dan 🎁 Bonus | Satu sumber daftar kategori di `TeknisiExpense::KATEGORI` + `kategoriLabel()`; dipakai form teknisi, tambah manual admin, dan controller legacy. Migrasi memindahkan baris `transport` → `perawatan` (hanya pada tabel `teknisi_expenses`, tabel legacy `teknis_expenses` tidak disentuh). | `tests/Feature/KategoriPengeluaranTeknisiTest.php`; migrasi `2026_10_25_000002` |
| C | Tiga syarat administrasi sebelum teknisi bertugas (laporan pengerjaan tuntas **dan** terkirim, pengeluaran tuntas, setoran tunai dilaporkan) + portal terkunci bila setoran belum dilaporkan | `SyaratAdministrasiService::penghalang()` menjadi **satu-satunya** sumber aturan, dipakai `berangkat()` dan `checkIn()`. Jenis penghalang: `setoran`, `laporan`, `foto`, `pengeluaran`. Tunggakan setoran **menyembunyikan daftar order** di beranda dan mengalihkan detail order ke halaman Setoran Tunai. | `tests/Feature/SyaratAdministrasiTest.php`, `CashHandoverTest.php`, `LaporanTerkirimGateTest.php`; commit `b36008e`, `f451589` |
| C2 | Setoran tunai harus ada foto/dokumentasi setor ke siapa + nominal | Tabel `cash_handovers` (nominal, penerima, foto wajib, tanggal, status menunggu/diterima/ditolak, catatan verifikasi). Halaman teknisi **Setoran Tunai** (form + riwayat), tab baru di bottom-nav, kartu setoran di detail order. Admin/Finance memverifikasi lewat menu **Finance → Setoran Tunai**. | idem |

---

## 3. Perubahan yang client/user akan lihat

**Portal teknisi**
- Tab **Setoran** (ikon uang) di navigasi bawah: daftar tunggakan, form laporan (nominal, diserahkan ke, foto, tanggal, catatan), dan 10 riwayat terakhir dengan badge status.
- Beranda: panel **merah "Akses ditahan — setoran tunai belum dilaporkan"** (daftar order disembunyikan) bila masih ada setoran hari sebelumnya yang belum dilaporkan; panel **kuning "Administrasi belum tuntas"** untuk penghalang lain (daftar order tetap tampil).
- Detail order: kartu **Laporan Setoran Tunai** pada bagian pembayaran untuk order tunai; bila portal sedang ditahan, membuka detail order langsung dialihkan ke halaman setoran dengan pesan yang menyebut order + nominal.
- Tombol **Berangkat** dan **Check-in** menolak dengan pesan yang menyebutkan semua yang belum tuntas (foto/keterangan yang kurang, laporan belum terkirim, pengeluaran pending, setoran tertunggak).

**Admin / Finance**
- Menu **Finance → Setoran Tunai**: tabel (tanggal, teknisi, order + customer, nominal, penerima, status), filter status/teknisi/rentang tanggal, aksi **Terima** / **Tolak** (catatan wajib saat menolak), foto bukti terlihat di halaman detail.
- Menu **Order**: aksi **"Kirim Laporan ke Customer"** (kirim PDF laporan pengerjaan lewat WA, lalu menandai terkirim **hanya bila kiriman sukses**) dan aksi **"Tandai Laporan Terkirim"** (untuk laporan yang dikirim manual/di luar aplikasi). Kolom "Laporan dikirim" dan entri "Laporan terkirim pada" di detail order.
- **Template Pesan WA** dapat entri baru "Notifikasi Laporan Pengerjaan" (placeholder `{nomor_order}` baru).

---

## 4. Perubahan skema (5 migrasi, semua menambah; tidak ada yang menghapus data)

| Migrasi | Isi |
|---|---|
| `2026_10_25_000001_add_cutoff_hari_ke_technician_payroll_profiles_table` | kolom `cutoff_hari` (nullable; kosong = perilaku lama) |
| `2026_10_25_000002_rename_kategori_transport_ke_perawatan` | `UPDATE teknisi_expenses SET kategori='perawatan' WHERE kategori='transport'` (hanya nilai kolom `kategori`) |
| `2026_10_25_000003_buat_pelunasan_kasbon_manual` | `technician_advance_repayments.payroll_run_id` → nullable + `catatan` + `dicatat_oleh` (agar kasbon bisa dilunasi di luar run gaji) |
| `2026_10_25_000010_add_laporan_terkirim_dan_metode_dipilih_pada_ke_orders` | `orders.laporan_terkirim_pada`, `orders.laporan_terkirim_oleh`, `orders.metode_dipilih_pada` |
| `2026_10_25_000011_create_cash_handovers_table` | tabel `cash_handovers` + 2 index |

Ketiganya (000010, 000011, dan 000001–000003) **sudah dijalankan di DB dev** dan statusnya `Ran`.
Uji pada MySQL/MariaDB lokal **belum** dijalankan pada sesi ini karena MariaDB XAMPP tidak hidup
(`ERROR 2002 ... 10061`); lihat §7.

---

## 5. Verifikasi (angka nyata dari mesin saya)

| Uji | Hasil |
|---|---|
| `php artisan test` (suite penuh, run terakhir) | **1054 lulus, 16 gagal, 3414 assertion, 171 detik** |
| Susunan 16 gagal | `ExpenseTrackingTest` 7, `PhotoUploadTest` 5, `NotifBerangkatTest` 2, `NotifPembayaranTest` 1, `OrderAcUnitLinkTest` 1 — **persis baseline sebelum pekerjaan ini**, tidak ada tambahan |
| Test baru Fase 2 | `SyaratAdministrasiTest` 4, `CashHandoverTest` 5, `LaporanTerkirimGateTest` 4 — hijau |
| Test baru Fase 1 | `PayrollCutoffTest`, `KasbonPeriodeTest`, `KategoriPengeluaranTeknisiTest`, `Devplan29PanelKasbonTest` — hijau (dibuktikan ulang di run penuh di atas) |
| `php artisan administrasi:periksa` | jalan; di DB dev semua teknisi 0 penghalang (DB dev memang hampir kosong) |
| `php artisan route:list --path=teknisi` | route `teknisi.setoran` terdaftar |
| `php -l` semua berkas baru/berubah | tanpa error sintaks |

**Regresi yang saya temukan sendiri dan perbaiki (bukan dilaporkan agen):**

1. Setelah gate dipasang, 4 test lama gagal karena aturan baru memang menambah syarat:
   `PhotoReportTemplateTest` ×2 dan `UnitReportTest` ×2 (semuanya menguji "berangkat boleh setelah
   foto lengkap"). Perbaikannya: test tetap menguji hal yang sama, tetapi order lama sekarang
   ditandai `laporan_terkirim_pada` lebih dulu — sesuai aturan baru bahwa laporan harus terkirim.
   Assertion aslinya **tidak** dihapus/dilemahkan.
2. Pesan penolakan foto sempat berubah sehingga test lama yang mencari teks "Lengkapi dulu foto"
   gagal. Saya **kembalikan** kalimat lama (dan tetap menyebut daftar foto yang kurang) — bukan
   mengubah test-nya.
3. Risiko hari deploy: gate akan memblokir teknisi untuk order lama yang selesai sebelum fitur ini
   dipasang. Saya batasi sendiri: `CashHandoverService::kewajiban()` hanya memuat order yang
   **sudah** ditandai tunai setelah fitur terpasang (`metode_dipilih_pada` terisi), sehingga order
   tunai lama tidak mengunci portal pada hari pertama. Untuk penghalang `laporan` (yang tidak punya
   penanda waktu otomatis), saya sediakan `php artisan administrasi:periksa` (read-only) dan
   `--tandai-terkirim=YYYY-MM-DD` bila pemilik memutuskan order lama dianggap selesai administrasi.
   Test `order tunai lama tanpa cap metode tidak mengunci portal` mengunci perilaku ini.

---

## 6. Penyimpangan / keputusan di luar teks plan

| Hal | Alasan |
|---|---|
| Rekap Bulanan Dana: honor/bonus dari Pengeluaran Teknisi **digabung** ke kolom **Gaji/Honor**, bukan "hanya informasi" | Rencana awal membuat total Rekap ≠ total halaman Akuntan (janji "satu sumber angka" jadi bohong) dan uang yang diinput Admin seolah hilang. Diubah saat verifikasi; test mengunci angka `gaji` dan `pengeluaran`. |
| `berangkat()` dan `checkIn()` memakai pesan gabungan (`pesanRingkas`) | Satu pesan menyebut semua penghalang; teknisi tidak perlu menebak. Kalimat lama soal foto dipertahankan agar tidak mengejutkan teknisi. |
| Kartu setoran di detail order **mengalihkan** ke halaman Setoran (bukan form inline) | Satu jalur input saja, jadi validasi kuota penyimpanan + kepemilikan order hanya ada di satu tempat. |
| Order tunai lama (tanpa `metode_dipilih_pada`) dikecualikan dari kewajiban setoran | Lihat §5 butir 3. |
| Laporan dianggap "terkirim" hanya bila `WaLog` sukses; ada aksi manual terpisah | Supaya kolom `laporan_terkirim_pada` tidak berbohong saat WA gagal. |
| Command `administrasi:periksa` (tidak diminta client) | Alat bantu pemilik memeriksa dampak gate di data produksi sebelum/sesudah deploy, dan menandai order lama secara sadar. |

---

## 7. Yang belum selesai / perlu keputusan Anda

1. **Uji migrasi di MySQL/MariaDB belum dijalankan** pada sesi ini — MariaDB XAMPP tidak aktif
   (port 3306 menolak koneksi). Migrasi hanya memakai operasi Blueprint standar + satu `UPDATE`
   kolom, tetapi ini belum dibuktikan di MySQL seperti yang saya lakukan pada plan 21. Bila XAMPP
   dinyalakan, saya bisa ulangi uji itu (±5 menit).
2. **Review independen agen kedua (`_brief/laporan-12.md`) belum ada.** Agen CLI kehabisan kuota di
   tengah review (`You've hit your usage limit ... Nov 24, 2026`). Review masih berjalan lewat agen
   lain; hasilnya akan ditambahkan ke berkas ini setelah saya verifikasi sendiri.
3. **Tampilan baru sudah saya pastikan ter-render dengan data nyata**, bukan lewat browser (tool browser di mesin ini tidak merespons): saya login sebagai teknisi (id 3) dan Owner (id 1) di dalam aplikasi, lalu meminta `/teknisi`, `/teknisi/setoran`, `/admin/cash-handovers`, `/admin/orders`, `/admin/orders/2` — kelimanya **HTTP 200** dan memuat penanda yang benar: panel "Akses ditahan" muncul di beranda **dan** daftar order ("Hari Ini & Akan Datang") hilang; halaman Setoran Tunai memuat form + riwayat; menu Finance → Setoran Tunai ada; aksi "Kirim Laporan ke Customer" dan "Tandai Laporan Terkirim" ada di daftar order. Yang belum: tata letak di layar HP sungguhan (9 tab bottom-nav) dan rasa tombolnya saat diklik — perlu sekali Anda coba.
   - **Data demo di DB lokal:** saya membuat 1 order contoh **id 2** (teknisi Teknisi Andi, customer Budi Santoso, Rp100.000, tunai ditandai kemarin, laporan sudah ditandai terkirim) supaya panel "Akses ditahan" bisa Anda lihat langsung. Hapus dengan `php artisan tinker --execute="\App\Models\Order::find(2)->delete();"` bila sudah tidak perlu.
4. **Cut-off per teknisi masih kosong** untuk semua teknisi. Admin perlu mengisi (mis. Bayu = 23)
   lewat menu profil gaji teknisi; selama kosong, periode gaji tetap perilaku lama.
5. **Kasbon Bayu 19 Sep** (kasus yang dilaporkan) harus diselesaikan sekali oleh Admin lewat aksi
   "Tandai sudah dibayar di periode lalu" — sistem tidak bisa menebak kasbon mana yang sudah dibayar
   di luar aplikasi.
6. **Order lama yang belum ditutup** dapat memblokir keberangkatan karena syarat "laporan terkirim".
   Jalankan `php artisan administrasi:periksa` di produksi setelah deploy; bila angkanya besar,
   putuskan memakai `--tandai-terkirim=YYYY-MM-DD`.
7. **Pengeluaran teknisi lama berstatus `pending`** juga ikut memblokir. Tidak ada penanda waktu
   pemasangan untuk ini; Admin perlu menyetujui/menolak yang menumpuk.
8. **Belum ada aksi massal** untuk menandai laporan terkirim / memverifikasi setoran banyak sekaligus.
9. Antrean WA (`KirimNotifWaJob`) tetap butuh worker di hosting; tombol kirim laporan berjalan
   **sinkron** dari admin sehingga tidak bergantung worker.

---

## 8. Langkah pasang di server (live)

```
mysqldump -u USER -p DB > backup-sebelum-29.sql
git pull
composer install --no-dev --optimize-autoloader     # vendor/ tidak ada di git
php artisan migrate:status | grep -i pending        # lihat 5 migrasi baru
php artisan migrate --force
php artisan optimize:clear
php artisan administrasi:periksa                    # lihat dampak gate SEBELUM teknisi memakai
```

- **Tidak perlu** `npm run build` — tidak ada perubahan `resources/js` atau `resources/css`.
- Setelah deploy: isi **cut-off** teknisi (menu profil gaji), buka **Finance → Setoran Tunai** untuk
  memastikan menunya muncul untuk Owner/Admin/Finance, dan cek **Template Pesan WA** memuat
  "Notifikasi Laporan Pengerjaan".
- Opsional di `.env`: `WA_NOTIF_LAPORAN=true|false` (default `true`).
- Migrasi `2026_10_25_000002` **mengubah nilai** kolom `kategori` pada `teknisi_expenses` milik
  teknisi (transport → perawatan). Tidak ada nominal/tanggal yang berubah. Snapshot cepat bila perlu:
  `SELECT kategori, COUNT(*) FROM teknisi_expenses GROUP BY kategori;`

---

## 9. Berkas bukti

- Brief & laporan agen: `_brief/10-…md` + `laporan-10.md` (fase 1, sisi periode gaji/kategori),
  `_brief/11-…md` (spesifikasi asli fase 2), `_brief/11b-…md` + `laporan-11b.md` (sisi teknisi),
  `_brief/11c-…md` + `laporan-11c.md` (sisi admin).
- Log agen: `_log/codex-11b-*.log`, `_log/codex-11c-*.log`, `_log/codex-review29-*.log`.
- Log suite: `_log/fullsuite-devplan29-*.log` (20 gagal, sebelum 4 regresi diperbaiki),
  `_log/fullsuite-devplan29-r2-*.log` (16 gagal = baseline, 1054 lulus).
- Commit: `93578ec` (fase 1), `864062d` + `b36008e` (fase 2 teknisi), `f451589` (fase 2 admin).
