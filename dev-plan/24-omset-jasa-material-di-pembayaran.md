# 24 — Omset Jasa & Omset Material di "Catat Pembayaran" (acuan final)

> STATUS: **DIEKSEKUSI** (Opsi B). Ringkas: fungsi split jasa/material SUDAH ada di
> `order_items.komponen`; plan ini menambahkan input split di pembayaran sebagai
> **acuan final** untuk laporan omset, dengan fallback ke `order_items` untuk data lama.

## 1. Latar

Laporan **Omset Jasa & Material** (widget `OmsetJasaMaterialWidget`, sumber
`OmsetService`) sudah ada, tetapi angkanya diturunkan dari `order_items.komponen`
(satu baris = satu komponen jasa/material). Dua celah:

1. **Satu baris campur jasa+material** (mis. "Isi Freon" 550rb = jasa 150rb +
   material 400rb) tidak bisa diwakili satu baris `order_items`.
2. **Total Tagihan di-override** saat "Catat Pembayaran" (ongkir/material tambahan
   atau diskon, B77) membuat split dari `order_items` ≠ uang yang benar-benar masuk.

## 2. Keputusan (Opsi B)

- Tambah 2 kolom di `payments`: `omset_jasa`, `omset_material` (nullable, decimal 14,2).
- Form **Catat Pembayaran** menambah input **Omset Jasa** & **Omset Material**, ter-prefill
  otomatis dari `Order::totalPerKomponen()` (atau nilai pembayaran sebelumnya), admin boleh sesuaikan.
- Aturan:
  1. Split mengikuti **Total Tagihan** (`total_tagihan`).
  2. Bila split terisi, ia **menang** atas `order_items` di semua laporan.
  3. Order selesai tanpa baris pembayaran → fallback ke `order_items` (perilaku lama).
  4. `omset_jasa + omset_material` harus **sama dengan** `total_tagihan` (toleransi 0,009).
- Jika Total Tagihan berubah sedangkan split lama tidak lagi cocok → wajib isi ulang split.

## 3. Sumber angka setelah perubahan

`OmsetService::baris()` (dipakai `ringkasan`/`kartu`/`total`), `AkuntanService::pendapatan`
(→ `FinanceService` laba-rugi, `DashboardPimpinanService` keuangan) memakai **split pembayaran**
bila ada, jika tidak fallback ke `order_items`. Games 5 (Omset Jasa) nanti memakai angka yang sama
(otomatisasi Games 5 terpisah — menunggu definisi atribusi order→tim/B50).

## 4. Berkas

Baru:
- `database/migrations/2026_10_15_000002_add_omset_jasa_material_ke_payments_table.php`

Diubah:
- `app/Models/Payment.php` (fillable, casts, `punyaSplitOmset()`, `splitOmset()`).
- `app/Services/PaymentService.php` (`recordPayment` terima & validasi split).
- `app/Services/OmsetService.php` (pakai split; eager-load `payments`).
- `app/Services/AkuntanService.php` (pakai split; eager-load `payments`).
- `app/Filament/Resources/OrderResource.php` (input split di form Catat Pembayaran).
