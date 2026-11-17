# PLAN Sisi Superadmin / Admin Web — Revisi 17 Nov 2026 (poin 24–33)

Lingkup: panel admin (Filament) untuk Owner/Admin/HRD/Finance. Bukti rujukan: `24`–`33` di folder ini (`25.mp4`, `26.mp4`, `27/28/29/29-b/30/33.jpeg`).

| # | Revisi | Area | Pendekatan | Berkas utama |
|---|---|---|---|---|
| 24 | Bonus Admin/HRD/Finance = 1% omset jasa, syarat minimal omset jasa Rp 50 jt | Pengaturan + Akuntan | Pengaturan baru (persen, minimal omset, aktif, peran penerima). Halaman **Bonus Admin** per periode (tgl mulai–sampai): omset jasa dari `OmsetService`, status memenuhi syarat, bonus per penerima. | `bonus_admin_settings` (migrasi+model), `BonusAdminService`, `Filament/Pages/BonusAdmin.php` |
| 25 | Jenis pelanggan otomatis dari data customer | Order > Create | Untuk "Pelanggan Terdaftar" field Jenis Pelanggan **disembunyikan**, diganti keterangan "Instansi/Umum (dari data customer)". Hanya "Pelanggan Baru" yang memilih jenis. `OrderService` sudah fallback ke `customer->jenis`. | `OrderResource/Pages/CreateOrder.php` |
| 26 | Unit AC tersimpan bisa dicari | Order > Create (wizard) | `->searchable()` + `getSearchResultsUsing` + `getOptionLabelUsing`. Path state dibaca lewat `$component->getStatePath()` dan `$livewire->data`, bukan `../` (penyebab pencarian dulu dimatikan). Pencarian: kode unit, ruangan, merek, PK. | `CreateOrder.php` |
| 27 | Filter periode (mulai tgl … sampai tgl …) di Jadwal OFF | SDM/HRD | Filter `Periode` (dari/sampai) di tabel Jadwal OFF. | `TechnicianOffDayResource.php` |
| 28 | Edit periode gaji + hapus data salah input | Penggajian | Aksi **Edit Periode** (mulai/selesai, hanya draft) dan **Hapus** (hanya draft, dengan konfirmasi). Periode manual disimpan apa adanya; Hitung Ulang memakai periode tersimpan (tidak dinormalkan ulang oleh cut-off). Tolak tumpang tindih periode teknisi yang sama. | `PayrollRunResource.php`, `PayrollService.php` |
| 29 | Input bonus / potongan lain berulang + catatan | Penggajian | Tabel baru `payroll_run_adjustments` (jenis bonus\|potongan, keterangan, tanggal, nominal, catatan). Aksi **+ Bonus** dan **+ Potongan Lain** di detail gaji, bisa berkali-kali, bisa dihapus selama draft. Bertahan saat Hitung Ulang. Masuk rincian gaji & slip. Contoh: "Potongan (ganti pipa) 240rb", "Bonus (Game 5) 4 Nov". | migrasi, `PayrollRunAdjustment`, `PayrollService`, `ViewPayrollRun`, `payroll/_rincian` |
| 30 | Aspek Performa: filter tanggal mulai–sampai (lintas bulan) | Dashboard Pimpinan | Pada tab Performa muncul input **Dari/Sampai**; kosong = bulan terpilih. Berlaku untuk 3.1–3.4. | `DashboardPimpinan.php`, blade |
| 31 | Pendapatan Jasa vs Material per teknisi, rincian jasa per layanan | Dashboard Pimpinan 3.1 | Kolom **Jasa** dan **Material** terpisah per teknisi + rincian jasa per klasifikasi (Cuci / Service / Pemasangan / Pengecekan / Lainnya) yang bisa dibuka. Dasar: `OmsetService::baris()` dikaitkan ke teknisi order. | `DashboardPimpinanService`, blade |
| 32 | Tingkat pekerjaan terkendala & batal oleh customer (angka + %) | Dashboard Pimpinan | Sub-tab baru **3.4 Kendala & Batal**. Penyebut = order yang dijadwalkan di periode. Terkendala = ada kendala tercatat (sisi customer/teknisi dipisah). Batal customer = pembatalan dengan pihak "customer" (kolom baru `orders.dibatalkan_oleh`, aksi Batalkan meminta pihak). | migrasi, `OrderService::cancel`, `OrderResource`, `DashboardPimpinanService` |
| 33 | Filter metode bayar (cash/transfer) di Payments | Finance | `SelectFilter` metode (semua `PaymentMethod`). | `PaymentResource.php` |

## Asumsi yang dipakai (bisa diubah bila salah)
1. **#24**: 1% dihitung dari omset jasa seluruh perusahaan pada periode, dan **tiap penerima** (peran Admin/HRD/Finance) mendapat 1% itu. Opsi "dibagi rata" tersedia di pengaturan. Bonus hanya diberikan bila omset jasa ≥ Rp 50.000.000. Tampil sebagai daftar/perhitungan, belum otomatis masuk gaji (admin belum punya modul gaji).
2. **#29**: bonus "Game 4/5/6" yang diinput manual di sini masuk sebagai bonus tambahan yang menambah gaji. Entri otomatis Games di Rekap Insentif tidak berubah.
3. **#31**: "Pengecekan" ditambahkan sebagai jenis layanan sendiri (jasa) lewat migrasi `2026_11_17_000004`; muncul terpisah di rincian per teknisi dan Klasifikasi Pengerjaan. Pekerjaan di luar kategori yang ada masuk "Lainnya".
4. **#32**: order lama yang sudah Batal tanpa catatan pihak masuk "Tidak dicatat".

## Catatan deploy
`php artisan migrate` (migrasi 2026_11_17_*). Tidak ada env baru.
