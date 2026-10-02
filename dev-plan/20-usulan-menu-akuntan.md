# 20 — Usulan Menu "Akuntan" (Admin) — Display Pendapatan & Pengeluaran

Status: **USULAN / belum dikerjakan** — menunggu persetujuan poin di §8.

## 1. Tujuan

Menu baru di panel admin (Filament) yang **fokus menampilkan data keuangan** (read-only,
bukan input). Semua data difilter per **bulan**.

## 2. Layout halaman

```
Akuntan                                   [ Filter bulan: 2026-10 ▾ ]
┌──────────────┬──────────────┬──────────────┬──────────────┐
│ Pendapatan   │ Pendapatan   │ Pengeluaran  │ Pengeluaran  │
│ Hari Ini     │ Bulan Ini    │ Hari Ini     │ Bulan Ini    │
└──────────────┴──────────────┴──────────────┴──────────────┘
[ Pendapatan Harian ] [ Pengeluaran Harian ] [ Semua Transaksi ]   ← 3 tab
```

Card **Hari Ini** selalu hari ini (tidak ikut filter); card **Bulan Ini** ikut bulan yang
dipilih di filter (label menyesuaikan, mis. "Pendapatan Oktober 2026").
Opsional: card kelima Laba/Rugi bulan = pendapatan − pengeluaran.

### Tab 1 — Pendapatan Harian (rekap per tanggal)
| Tanggal | Jml Order | Total Pendapatan | Aksi |
|---|---|---|---|
| 02 Okt 2026 | 5 | Rp 1.250.000 | **Lihat Detail** |

Urut tanggal desc. **Lihat Detail** → modal/slide-over berisi order pada tanggal itu:
No. order, customer, layanan utama, total; baris order bisa di-expand untuk **rincian turunan**
(layanan utama + tiap `order_items` tambahan / biaya tambah layanan, mis. sparepart hasil
"Ada Perbaikan"), beserta subtotal.

### Tab 2 — Pengeluaran Harian (rekap per tanggal)
Sama polanya: Tanggal | Jml Transaksi | Total Pengeluaran | **Lihat Detail**.
Detail memuat: sumber (Admin / Teknisi), nama teknisi, kategori, keterangan, qty × harga,
nominal, order terkait (link ke order bila ada), status.

### Tab 3 — Semua Transaksi
Satu tabel gabungan dengan sub-toggle **Pendapatan | Pengeluaran** (atau 2 tabel bertumpuk),
urut **tanggal desc**, paginasi, pencarian (customer / keterangan), filter kategori/teknisi.
Dalam bulan terpilih.

## 3. Sumber data (hasil telaah kode)

| Kebutuhan | Sumber | Catatan |
|---|---|---|
| Pendapatan order | `Order` + `orderItems` (`Order::total()`) | `total()` sudah menjumlah layanan utama + item tambahan → "turunan" = baris `order_items` |
| Tanggal pendapatan | `payments.tanggal_bayar` (lunas) → fallback `orders.updated_at` | `Teknisi\Keuangan` kini pakai `updated_at`; lihat §8 poin 1 |
| Pengeluaran admin | `expenses` (`tanggal`, `kategori`, `nominal`, `qty`, `harga`, `order_id`, `dicatat_oleh`) | input via Filament ExpenseResource |
| Pengeluaran teknisi | `teknisi_expenses` (`tanggal_input`, `order_id`, `status`) | input dari `Teknisi\LaporanPengeluaran`, status pending/approved/rejected |

Tidak dipakai (hindari dobel hitung): tabel `incomes` (ditulis sekali saat lunas dengan
`total_tagihan` snapshot, tidak ikut update bila ada tambah layanan sesudahnya),
`teknis_expenses` (tabel lama, model `TeknisExpense`), `expense_items` (laporan harian lama).
Perlu dikonfirmasi apakah dua yang terakhir masih terisi data produksi.

## 4. Arsitektur

Mengikuti pola `app/Filament/Pages/OrderanHarian.php` (Filament Page + Livewire + Blade):

- `app/Filament/Pages/Akuntan.php`
  - nav group `Finance`, ikon `heroicon-o-calculator`, slug `akuntan`
  - `canAccess()`: Owner, Admin, Finance (sama dengan `RingkasanFinanceWidget`)
  - properti `#[Url]`: `bulan` (Y-m, default bulan ini), `tab`, dan `tanggalDetail` (untuk modal)
- `app/Services/AkuntanService.php` — semua query agregasi (agar testable, view tetap tipis):
  - `ringkasanKartu(CarbonInterface $bulan): array` — 4 angka card
  - `pendapatanPerHari($bulan)`, `pendapatanDetailHari($tanggal)` (dengan rincian item)
  - `pengeluaranPerHari($bulan)`, `pengeluaranDetailHari($tanggal)`
  - `daftarPendapatan($bulan)`, `daftarPengeluaran($bulan)` (query paginasi, desc)
  - pengeluaran = union `Expense` + `TeknisiExpense` dinormalisasi ke bentuk
    `{tanggal, sumber, pelaku, kategori, keterangan, qty, harga, nominal, order_id, status}`
- `resources/views/filament/pages/akuntan.blade.php` — card, tab (Alpine/Livewire), tabel,
  modal detail (pakai komponen modal Filament / `x-filament::modal`)
- Rupiah format sama dengan helper yang sudah ada (`number_format(..., 0, ',', '.')`)

Performa: agregasi per hari pakai `GROUP BY DATE(...)` di SQL, bukan memuat semua baris;
`Order::total()` butuh `orderItems` → untuk rekap pakai `SUM(harga*jumlah)` dari `order_items`
join `orders`, bukan loop model.

## 5. Langkah kerja

1. `AkuntanService` + unit test (rekap harian, detail, kartu; kasus: order dengan item tambahan,
   pengeluaran teknisi ditolak tidak dihitung, order batal tidak dihitung, batas bulan).
2. Page `Akuntan` + view: filter bulan + 4 card.
3. Tab 1 & 2 (rekap harian + modal Lihat Detail).
4. Tab 3 (daftar gabungan desc + paginasi + filter).
5. Hak akses + feature test (teknisi/HR → 403; admin/finance/owner → 200).
6. Rapikan: empty state, loading state, tampilan mobile/responsif.

Estimasi: ±1–1,5 hari kerja.

## 6. Di luar cakupan (sengaja)

Input/edit/hapus transaksi, approval pengeluaran (sudah ada di `ManajemenPengeluaranTeknisi`),
export Excel/PDF (bisa tahap berikutnya), grafik tren.

## 7. Risiko

- Dobel hitung antar sumber pengeluaran bila `expenses` dan `teknisi_expenses` saling menyalin
  data — perlu dicek apakah ada proses approve yang menyalin ke `expenses`
  (`TeknisExpenseController::approve`).
- Selisih angka dengan `Teknisi\Keuangan` bila definisi tanggal pendapatan berbeda.

## 8. Keputusan yang perlu dikonfirmasi

1. **Dasar pendapatan & tanggalnya** — usulan: order berstatus **selesai**, total dari
   `order_items` (termasuk biaya tambahan), tanggal = tanggal bayar lunas, fallback tanggal
   selesai. Alternatif: murni tabel `incomes` (kas masuk, tapi tidak ikut update bila ada
   tambahan layanan setelah lunas).
2. **Pengeluaran teknisi** — usulan: hanya status **approved** masuk total; **pending**
   ditampilkan di tabel dengan badge dan dijumlah terpisah (bukan di card); **rejected** tidak
   dihitung. Setuju?
3. **Akses** — Owner + Admin + Finance (HR tidak)?
4. **Letak menu** — grup "Finance" di sidebar?
5. Card kelima **Laba/Rugi** bulan ini perlu?

## 9. Rollout (disepakati)

1. **Tahap 1 (SELESAI diimplementasi):** menu Akuntan berjalan paralel; menu lama tidak disentuh.
   Keputusan §8 memakai usulan default (order selesai + `order_items`, tanggal bayar lunas;
   teknisi approved saja yang dihitung; akses Owner/Admin/Finance; grup Finance; tanpa card Laba/Rugi).
2. **Tahap 2:** pemilik mencocokkan angka beberapa bulan; selisih diperbaiki di rumus
   (`AkuntanService`), bukan di data.
3. **Tahap 3:** setelah cocok, menu display-only lama disembunyikan dari navigasi
   (widget ringkasan, daftar Income/Expense). `ManajemenPengeluaranTeknisi` (approve) tetap hidup.
   Tidak ada penghapusan kode/tabel.

File: `app/Services/AkuntanService.php`, `app/Filament/Pages/Akuntan.php`,
`resources/views/filament/pages/akuntan.blade.php`, `tests/Feature/AkuntanPageTest.php`.
