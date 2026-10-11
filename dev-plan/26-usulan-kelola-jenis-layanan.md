# 26 — Usulan Menu Kelola "Jenis Layanan" (Service Type)

> STATUS: **DIEKSEKUSI (Opsi B hybrid)** 11 Okt 2026.
> Permintaan client: menu untuk **menambah Jenis Layanan** (bukan hardcode di select
> option saja). Jenis yang **sudah ada saat ini tidak bisa diedit/dihapus**; yang **baru
> ditambahkan** ke depan bisa dikelola.
>
> Keputusan: (1) Opsi B; (2) jenis tambahan cukup **nama + komponen** (tanpa slot foto);
> (3) komponen **diisi manual admin**; (4) boleh **dinonaktifkan** tanpa dihapus.
>
> Implementasi: tabel `service_types` + model `ServiceTypeOption` + `ServiceTypeResolver`
> + `ServiceTypeValue` + cast `ServiceTypeCast`; enum `ServiceType` (7 bawaan) tetap
> read-only. Menu **Customer & Order → "Jenis Layanan"**. Opsi select Katalog/Order/Teknisi
> & laporan omset kini mengenali jenis tambahan. Tes `JenisLayananTest`.
>
> Plus (permintaan terpisah, sudah dikerjakan): kolom **Telepon & WA** di menu Order admin.

## 1. Kondisi sekarang

- "Jenis Layanan" = enum `App\Enums\ServiceType` (7 nilai tetap): `cuci_ac`, `service_ac`,
  `pengadaan_ac`, `tambah_freon`, `instalasi`, `relokasi`, `bongkar`.
- Dipakai sebagai **select option** di mana-mana via `EnumOptions::for(ServiceType::class)`.
- **Disimpan** sebagai string lalu di-cast ke enum:
  - `service_catalogs.jenis_layanan` → `ServiceType` (`ServiceCatalog.php:34`)
  - `order_items.kategori` → `ServiceType` (`OrderItem.php:42`)
  - `photo_report_templates.kategori` → `ServiceType` (`PhotoReportTemplate.php:28`)
- **Dipakai di ±62 titik**: laporan omset (`OmsetService`), `IncomeCategory::untukLayanan`,
  `DashboardPimpinanService`, template foto (`PhotoReportTemplateService`, `FotoLaporanSlot`),
  `TeknisiService`, form (`OrderResource`, `ServiceCatalogResource`, `PhotoReportTemplateResource`),
  riwayat/komponen teknisi, factory, seeder/migrasi.

## 2. Masalah

Menambah jenis layanan baru **harus ubah kode** (tambah `case` enum) — tidak bisa dari UI.

## 3. Opsi rancangan

### A. Full dinamis (ganti enum → tabel `service_types`)
- Hapus cast enum; `jenis_layanan`/`kategori` jadi string + relasi ke tabel.
- Update ±62 titik (banyak `instanceof ServiceType`, `->value`, `ServiceType::from()`).
- **Risiko tinggi**, blast radius besar, tidak disarankan sekaligus.

### B. Hybrid (REKOMENDASI) — bawaan tetap enum, tambahan di tabel
- Tetap pakai enum `ServiceType` untuk **7 jenis bawaan (read-only)**.
- Tabel baru **`service_types`** menyimpan **jenis tambahan**: `kode` (unik), `label`,
  `komponen` (jasa/material), `aktif`, `urutan`, `ikon` (nullable), timestamps.
- **Opsi select** = gabungan enum bawaan + baris `service_types` aktif.
- Storage: ubah cast `jenis_layanan`/`kategori` dari enum → **string**, lalu buat **resolver**
  `ServiceTypeResolver` (label, komponen, slot foto, dll) yang mengenali bawaan (enum) &
  tambahan (tabel). Titik yang perlu disesuaikan terbatas pada yang membaca enum (~10–15).
- Kelola via resource baru **"Jenis Layanan"** (grup menu yang sesuai): tambah jenis baru +
  aktif/nonaktif/urut; **bawaan ditandai read-only** (tidak bisa edit/hapus sesuai permintaan).

### C. Minimal (paling cepat, paling terbatas)
- Tambah tabel `service_types` (tambahan) + resource kelola.
- Opsi select digabung, **tetapi** jenis tambahan diperlakukan seperti `Lainnya` di laporan
  omset (komponen jasa/material ditebak dari nama seperti aturan `IncomeCategory`).
- Tidak mengubah cast enum; `jenis_layanan` tetap enum → **tidak bisa** menampung jenis baru
  di `service_catalogs`/`order_items` tanpa menyesuaikan cast. Jadi opsi C tidak menyelesaikan
  penyimpanan → **kurang layak**.

## 4. Usulan teknis (Opsi B) — berkas

- Migrasi: `create_service_types_table` (seed 0; bawaan tetap dari enum, bukan di tabel).
- Model: `App\Models\ServiceTypeOption` (hindari bentrok nama `ServiceType` enum).
- `App\Support\ServiceTypeResolver` (label/komponen/opset dari enum + tabel tambahan).
- Ubah cast: `ServiceCatalog`, `OrderItem`, `PhotoReportTemplate` → string + accessor label.
- Update pembaca enum: `OmsetService`, `IncomeCategory::untukLayanan`, `DashboardPimpinanService`,
  `PhotoReportTemplateService`, `FotoLaporanSlot`, `TeknisiService`, `OrderDetail` (opsi form).
- Form: ganti `EnumOptions::for(ServiceType::class)` → resolver (bawaan + tambahan).
- Resource baru **"Jenis Layanan"** (tambah/aktif/nonaktif; bawaan read-only).
- Tes: jenis tambahan muncul di opsi katalog/order; laporan omset mengenali jenis baru;
  bawaan tidak bisa diedit/dihapus.

## 5. Dampak & risiko

- Sedang–tinggi (menyentuh enum pusat). Mitigasi: kerjakan bertahap + tes regresi
  (Omset, ServiceCatalog, Order, Unit Report) sebelum rilis.
- Data lama aman (nilai enum tetap tersimpan sebagai string yang sama).

## 6. Keputusan yang diminta

1. Setuju **Opsi B (hybrid)**? (atau minta langsung full dinamis Opsi A)
2. Jenis tambahan: **wajib punya banyak slot foto** juga (template foto) atau cukup
   "jasa/material + nama" saja?
3. Komponen omset jenis baru: **diisi manual admin** saat membuat (jasa/material), atau
   tebak dari nama? (usul: **manual admin**)
4. Penomoran/urutan di select: pakai kolom **urutan** manual? (usul: ya)
5. Apakah jenis tambahan boleh **dinonaktifkan** (disembunyikan dari opsi) tanpa dihapus?
   (usul: ya)
