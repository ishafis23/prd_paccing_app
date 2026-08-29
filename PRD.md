# PRD — One Gate System Paccing
### Sistem CRM Internal untuk Paccing Official (Cuci AC & Service AC Makassar, Gowa, Maros)

**Versi:** 0.1 (Draft)
**Tanggal:** 29 Agustus 2026
**Sumber konsep:** Mind map client "One Gate System Paccing" + profil bisnis Instagram @paccingofficial

---

## Tentang Dokumen Ini

**PRD (Product Requirement Document)** atau **Dokumen Persyaratan Produk** adalah dokumen panduan yang menjelaskan secara detail fungsi, fitur, perilaku, dan kriteria keberhasilan dari sistem yang akan dibangun.

Dokumen ini menjembatani komunikasi antara **pemilik usaha (client)**, **tim desain**, dan **tim pengembang (developer)**, supaya semua pihak bekerja dari pemahaman yang sama tentang apa yang dibangun, untuk siapa, dan kenapa.

**Fungsi dokumen ini:**

- **Acuan pengembangan** — sumber kebenaran tunggal (*single source of truth*) tentang apa yang harus dibuat developer.
- **Menyelaraskan visi** — client, desainer, dan developer punya pemahaman yang sama soal tujuan sistem.
- **Mengurangi miskomunikasi** — meminimalkan asumsi liar saat proses coding/desain (lihat juga daftar asumsi terbuka di §12).
- **Dasar pengujian** — jadi acuan skenario testing sebelum sistem dipakai sehari-hari.

**Peta isi dokumen** (mengikuti komponen standar PRD, disesuaikan kebutuhan Paccing):

| Komponen Standar PRD | Ada di Bagian |
|---|---|
| Tujuan & Latar Belakang | §1 Latar Belakang, §2 Tujuan Produk |
| Target Pengguna (User Persona) | §3 Target Pengguna (Role) |
| Fitur & Persyaratan Fungsional | §4 Ruang Lingkup Modul |
| Alur Kerja Pengguna (User Flow) | §5 Alur Bisnis Utama |
| Persyaratan Non-Fungsional | §6 Kebutuhan Non-Fungsional |
| Desain & Wireframe | §7 Desain & Wireframe |
| Matrik Keberhasilan (Success Metrics) | §8 Matrik Keberhasilan |

Bagian tambahan di luar komponen standar, khusus untuk kebutuhan proyek ini: §9 Keputusan Teknologi, §10 Entitas Data, §11 Roadmap, §12 Asumsi Terbuka.

---

## 1. Latar Belakang

Paccing Official adalah usaha cleaning service dengan spesialisasi HVAC di area Makassar, Gowa, dan Maros, dengan lini layanan:

- **Cuci AC** (rutin/berkala)
- **Service AC** (perbaikan, ganti freon, dsb)
- **Pengadaan AC** (penjualan unit — AC Split, AC Standing, dll)

Saat ini operasional (data customer, jadwal teknisi, laporan pengerjaan, keuangan) diasumsikan masih manual/tersebar (chat WA, catatan, Excel). Client ingin satu sistem terpusat — **"One Gate System"** — yang menyatukan seluruh proses bisnis ke dalam satu CRM, agar admin, teknisi, dan owner bekerja dari satu sumber data yang sama.

## 2. Tujuan Produk

1. Menyatukan pengelolaan **customer, order/pengerjaan, teknisi, karyawan, dan keuangan** dalam satu sistem.
2. Memberi **visibilitas status pekerjaan** secara real-time ke admin dan owner (siapa mengerjakan apa, sudah sampai mana, kapan servis berikutnya jatuh tempo).
3. Merapikan **pencatatan keuangan** (pendapatan jasa & material vs pengeluaran material, perawatan, operasional) agar owner bisa melihat profitabilitas dengan jelas.
4. Membangun dasar untuk **retensi customer** lewat notifikasi jadwal servis berkala (cuci AC rutin tiap 3–6 bulan).
5. Menyediakan data **performa teknisi & karyawan** sebagai dasar evaluasi dan jenjang karir.

## 3. Target Pengguna (Role)

| Role | Deskripsi | Kebutuhan Utama |
|---|---|---|
| **Owner** | Pemilik usaha | Dashboard ringkasan finance, performa teknisi, jumlah customer aktif |
| **Admin** | CS/operasional harian | Input data customer, buat order, assign teknisi, catat pembayaran |
| **Finance** | Bisa dirangkap Admin/Owner di tahap awal | Input pengeluaran, rekap pendapatan, laporan keuangan |
| **HR** | Bisa dirangkap Owner/Admin di tahap awal | Kelola data karyawan, absensi, performa |
| **Teknisi** | Petugas lapangan | Lihat jadwal, absen di lokasi, input laporan pengerjaan |

> Catatan: untuk tim kecil, satu orang (Owner/Admin) bisa memegang multi-role sekaligus. Sistem tetap dirancang dengan role terpisah agar siap saat tim membesar.

## 4. Ruang Lingkup Modul (mengikuti mind map client)

### 4.1 Modul Admin
- CRUD **Data Customer** (nama, alamat, no. HP/WA, area: Makassar/Gowa/Maros, sumber lead)
- **Status Customer** (baru/lead, aktif berlangganan rutin, non-aktif)
- Buat **order/pengerjaan** baru (jenis layanan: cuci / service / pengadaan; jenis & jumlah unit AC; jadwal)
- **Riwayat pengerjaan** per customer (histori semua order + laporan teknisi)
- **Notice waktu pengerjaan berikutnya** — sistem otomatis menandai kapan customer jatuh tempo cuci/service berikutnya, dan memunculkan reminder ke admin (opsional: auto-notifikasi WA ke customer)
- **Pembayaran Customer**
  - Metode: cash, transfer, QRIS, e-wallet
  - Status: belum dibayar / DP / lunas
  - Riwayat pembayaran per order

### 4.2 Modul Teknisi
- **Jadwal** kerja (harian/mingguan), termasuk order yang di-assign ke teknisi tsb
- **Absensi** (check-in/check-out, idealnya dengan timestamp + opsional lokasi saat tiba di lokasi customer)
- **Laporan Pengerjaan** — catatan pekerjaan, material/spare part terpakai, foto before/after
- **Status Pengerjaan** — menunggu, dikerjakan, selesai, butuh follow-up
- **Riwayat Pengerjaan** — histori semua order yang pernah dikerjakan teknisi tsb
- **Capaian Kerja** — jumlah unit dikerjakan per periode, rating/feedback customer (jika ada)

### 4.3 Modul Human Resource (HR)
- **Data Karyawan** (biodata, tanggal masuk, jabatan/role)
- **Kehadiran** — rekap absensi seluruh karyawan (bukan hanya teknisi lapangan)
- **Performa Kerja** — ringkasan dari capaian kerja teknisi + evaluasi kualitatif
- **Kualifikasi/Jenjang Karir** — level/skill karyawan (mis. Teknisi Junior/Senior), dasar untuk kenaikan jenjang atau insentif

### 4.4 Modul Finance
- **Pendapatan**
  - Dari **Jasa** (biaya cuci/service, otomatis tertarik dari order yang sudah dibayar lunas)
  - Dari **Material** (penjualan unit AC / spare part, dari order pengadaan)
- **Pengeluaran**
  - Material (pembelian spare part/unit AC untuk stok atau pengadaan customer)
  - Perawatan (mis. perawatan kendaraan operasional, alat kerja)
  - Operasional (BBM, konsumsi, gaji, dll — kategori bebas)
- **Rencana Pengembangan** — catatan/anggaran rencana investasi ke depan (mis. beli alat baru, tambah teknisi) — di MVP cukup berupa catatan/target, bukan modul kompleks
- Laporan ringkas: laba rugi sederhana per periode (pendapatan − pengeluaran)

## 5. Alur Bisnis Utama (Order → Pembayaran)

1. Customer menghubungi via WA/IG/telepon → **Admin** input data customer (baru) atau cari data existing.
2. Admin buat **order baru**: pilih jenis layanan, jenis/jumlah unit AC, alamat, jadwal.
3. Admin **assign teknisi** sesuai area & ketersediaan jadwal.
4. **Teknisi** melihat jadwal di sistem → saat di lokasi, **absen check-in**.
5. Teknisi menyelesaikan pekerjaan → input **laporan pengerjaan** (catatan, material terpakai, foto) → ubah **status jadi selesai**.
6. Admin memverifikasi laporan → mencatat **pembayaran** (metode & status).
7. Sistem otomatis:
   - Mencatat **pendapatan** ke modul Finance (jasa/material sesuai jenis order).
   - Menghitung **tanggal servis berikutnya** (mis. +3 bulan untuk cuci AC rutin) → masuk ke **notice** admin.
8. Riwayat tersimpan di profil customer & profil teknisi untuk pelaporan performa.

## 6. Kebutuhan Non-Fungsional

- **Mobile-friendly**: teknisi mengakses jadwal & input laporan dari HP di lapangan (browser, tidak wajib aplikasi native di MVP).
- **Role-based access control**: setiap role hanya melihat menu & data sesuai kewenangannya.
- **Notifikasi**: minimal notifikasi in-app untuk "notice servis berikutnya"; WhatsApp API (mis. Fonnte/Wablas) sebagai peningkatan di fase lanjutan.
- **Keamanan**: password ter-hash, validasi input di server, proteksi CSRF/SQL Injection/XSS.
- **Backup data**: backup database berkala (harian).
- **Skalabilitas ringan**: cukup untuk skala UKM (ratusan–ribuan customer), tidak perlu arsitektur high-traffic di awal.

## 7. Desain & Wireframe

Belum tersedia — dokumen ini masih tahap konsep/fungsional. Wireframe/mockup UI (mis. di Figma) sebaiknya dibuat **setelah** ruang lingkup fitur di §4 disepakati bersama client, supaya desain tidak bolak-balik berubah akibat perubahan requirement.

Sebagai acuan awal untuk desainer, rencana halaman per modul sudah tercantum di masing-masing dokumen dev-plan:

- [Halaman Modul Admin](dev-plan/admin/01-konsep-admin.md#4-halamanmenu)
- [Halaman Modul Teknisi](dev-plan/teknisi/01-konsep-teknisi.md#4-halamanmenu)
- [Halaman Modul Finance](dev-plan/finance/01-konsep-finance.md#4-halamanmenu)
- [Halaman Modul HRD](dev-plan/hrd/01-konsep-hrd.md#4-halamanmenu)

## 8. Matrik Keberhasilan (Success Metrics)

Sistem ini untuk operasional internal (bukan aplikasi customer-facing), jadi indikator suksesnya diukur dari **efisiensi operasional & kualitas data**, bukan metrik akuisisi seperti conversion rate.

| Indikator | Target Setelah Fase 1 Berjalan (~1–2 bulan) |
|---|---|
| Order tercatat di sistem, bukan lagi hanya di chat WA/catatan manual | 100% order baru masuk lewat sistem |
| Waktu admin membuat 1 order baru | < 2 menit, dari cari/isi data customer sampai order tersimpan |
| Kecepatan pencatatan pembayaran | Tercatat di sistem di hari yang sama dengan transaksi |
| Customer aktif mendapat notice servis berikutnya | 100% otomatis via `service_reminders` setelah order selesai & lunas |
| Laporan laba-rugi tersedia tanpa rekap manual Excel | Owner bisa cek pendapatan vs pengeluaran bulan berjalan kapan saja dari dashboard |
| Adopsi teknisi terhadap sistem (laporan pengerjaan tidak lagi lewat WA ke admin) | 100% laporan pengerjaan masuk lewat sistem dalam 2 minggu setelah rilis |

> Target di atas adalah usulan awal untuk didiskusikan — perlu disesuaikan dengan Owner setelah tahu kondisi/baseline operasional saat ini (lihat §12).

## 9. Keputusan Teknologi — PHP Native vs Laravel

**Rekomendasi: Laravel** (versi LTS terbaru saat development, mis. Laravel 11/12), dengan MySQL, Blade + Tailwind, dan panel admin via **Filament** atau **Livewire** untuk mempercepat pembangunan UI CRUD.

| Aspek | PHP Native | Laravel |
|---|---|---|
| Kecepatan development | Lambat — auth, ORM, validasi, routing ditulis manual | Cepat — auth (Breeze), Eloquent ORM, validasi, routing sudah tersedia |
| Manajemen multi-role (Admin/Finance/HR/Teknisi/Owner) | Harus dibangun sendiri dari nol | Tinggal pakai `spatie/laravel-permission`, sudah teruji |
| Fitur "notice servis berikutnya" (terjadwal) | Perlu cron manual + logic manual | Task Scheduler bawaan Laravel, tinggal daftarkan job |
| Keamanan (SQLi, CSRF, XSS) | Rawan jika developer lupa sanitasi manual | Proteksi bawaan by default |
| Ekosistem laporan/export (Excel, PDF invoice) | Cari & integrasi library manual | Tinggal pakai `laravel-excel`, `dompdf`, dll |
| Maintainability jangka panjang | Sulit di-scale/di-handover ke developer lain | Struktur MVC baku, mudah onboarding developer baru |
| Kebutuhan hosting | Bisa jalan di hosting paling minim (tanpa Composer) | Butuh Composer & PHP ≥ 8.1 — mayoritas hosting Indonesia modern (termasuk shared hosting) sudah mendukung |

**Kapan native PHP masih masuk akal:** hanya jika hosting sangat terbatas (tidak bisa Composer/CLI sama sekali) atau sistem yang dibangun benar-benar sesederhana form + tabel tanpa role kompleks. Untuk kebutuhan One Gate System ini — 5 role berbeda, alur order-pembayaran-pengingat otomatis, laporan finance — kompleksitasnya sudah melewati titik di mana native PHP jadi lebih lambat dan lebih rawan bug/celah keamanan dibanding Laravel. **→ Laravel lebih tepat.**

## 10. Entitas Data Utama (gambaran awal)

- `users` (role: owner, admin, finance, hr, teknisi)
- `customers` (nama, kontak, alamat, area, status)
- `orders` (customer_id, jenis_layanan, jenis_unit_ac, jadwal, teknisi_id, status)
- `work_reports` (order_id, catatan, material_terpakai, foto, waktu_selesai)
- `attendances` (user_id, order_id/null, check_in, check_out)
- `payments` (order_id, metode, status, jumlah, tanggal)
- `service_reminders` (customer_id, order_id, tanggal_servis_berikutnya, status_notice)
- `employees` (user_id, jabatan, tanggal_masuk, jenjang_karir)
- `incomes` (sumber: jasa/material, nominal, tanggal, order_id)
- `expenses` (kategori: material/perawatan/operasional, nominal, tanggal, keterangan)

## 11. Roadmap / Fase Pengembangan

**Fase 1 — MVP (fokus operasional harian):**
- Modul Admin: data customer, order, pembayaran, notice servis berikutnya
- Modul Teknisi: jadwal, absensi, laporan & status pengerjaan
- Modul Finance dasar: pendapatan (otomatis dari order) + input pengeluaran manual + laporan laba-rugi sederhana
- Auth & role dasar (Owner, Admin, Teknisi)

**Fase 2 — Pengembangan lanjutan:**
- Modul HR penuh (performa, jenjang karir, kehadiran non-teknisi)
- Modul Finance: rencana pengembangan/anggaran, laporan lebih detail per kategori
- Notifikasi WhatsApp otomatis (reminder servis ke customer, notifikasi jadwal ke teknisi)
- Inventory/stok sederhana untuk unit AC & spare part (mendukung lini "Pengadaan AC")
- Rating/feedback customer terhadap teknisi (mendukung capaian kerja)

## 12. Asumsi & Hal yang Perlu Dikonfirmasi ke Client

- Jumlah teknisi & admin aktif saat ini (untuk estimasi skala & desain role).
- Apakah "Pengadaan AC" perlu manajemen stok/inventory sejak Fase 1, atau cukup dicatat sebagai transaksi di Finance dulu.
- Interval standar reminder cuci AC rutin (3 bulan? 6 bulan? tergantung tipe unit?).
- Apakah notifikasi WhatsApp ke customer wajib di Fase 1, atau notice cukup tampil di dashboard admin dulu.
- Preferensi hosting/server yang akan dipakai (VPS vs shared hosting) — memengaruhi detail deployment Laravel.
