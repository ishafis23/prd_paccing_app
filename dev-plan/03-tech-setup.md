# Tech Setup & Konvensi Kode — One Gate System Paccing

Dokumen ini mencatat setup aktual (6 September 2026) dan konvensi yang
dipakai tim. Update dokumen ini setiap ada perubahan environment/konvensi.

## Stack Terpasang

| Komponen | Versi | Catatan |
|---|---|---|
| PHP | 8.4.23 (CLI, Laravel Herd) | NTS x64 |
| Composer | 2.8.10 | |
| Laravel Framework | 12.69.1 | scaffold `laravel/laravel:^12.0` di root repo |
| spatie/laravel-permission | ^8.3 | role-based access (keputusan T2/eksekusi B7) |
| Pest | ^3.8 (+ pest-plugin-laravel ^3.2) | framework test (aturan QA) |
| DB development/test | SQLite (file `database/database.sqlite`) | keputusan T3; produksi MySQL |
| Node/Vite | skeleton bawaan | belum dipakai (UI menyusul) |

## Struktur Kode (di luar skeleton Laravel)

```
app/
├── Enums/            ← 16 enum bertipe string (nilai = nilai DB)
├── Exceptions/       ← BusinessRuleException (aturan bisnis)
├── Models/           ← 13 model Fase 1 + relasi
├── Services/         ← logika bisnis: OrderService, PaymentService,
│                       TeknisiService, StockService, FinanceService
│                       + trait RestrictsByRole (otorisasi berbasis role)
└── Providers/        ← AuthServiceProvider (Gate::before owner = super admin)
database/
├── migrations/       ← 14 migrasi Fase 1 (prefix 2026_09_06_*)
├── factories/        ← factory semua model (data test)
└── seeders/          ← RolesAndPermissionsSeeder, ServiceCatalogSeeder,
                        DatabaseSeeder (akun demo)
tests/
├── Pest.php          ← bind Feature tests + RefreshDatabase
├── Unit/             ← test murni tanpa DB (EnumValuesTest)
└── Feature/          ← SchemaTest, ModelRelationsTest, RoleAccessTest,
                        AdminOrderFlowTest, StockTest, TeknisiWorkflowTest,
                        FinanceTest
```

## Konvensi Kode

1. **Enum sebagai sumber nilai**: kolom bertipe `string` + validasi via PHP
   enum (`app/Enums`). Tidak pakai enum DB-level (portabel SQLite/MySQL).
2. **Logika bisnis di Services**, bukan controller/model. Model hanya
   relasi & casts. Service memakai trait `RestrictsByRole` untuk memastikan
   role pemanggil (Owner selalu lolos lewat `Gate::before`).
3. **Aturan bisnis vs otorisasi**: pelanggaran state → `BusinessRuleException`;
   larangan role/kepemilikan → `AuthorizationException`.
4. **Stok**: `stock_items.stok_saat_ini` kolom cache; semua perubahan lewat
   `StockService` (masuk/keluar/penyesuaian) yang menulis `stock_movements`
   dan menghitung delta dari movement tersimpan. Stok boleh minus (keputusan B4).
5. **Income & reminder otomatis**: hanya dipicu `PaymentService::recordPayment`
   saat status lunas; idempotent (guard income/reminder per order).
6. **Migrasi**: satu migrasi per tabel, nama `YYYY_MM_DD_HHMMSS_nama`. Skema
   mengikuti `dev-plan/database-schema.md` (Fase 1 saja; tabel Fase 2 belum dibuat).
7. **Bahasa kode**: nama kolom & pesan domain memakai bahasa Indonesia
   (sesuai skema); kode struktural PHP standar.

## Aturan QA (wajib, arahan Lead QA)

1. Setiap pembuatan/ubahan kode WAJIB disertai Unit/Feature Test (Pest).
2. Sebelum dianggap selesai: `php artisan test` harus HIJAU (0 fail).
3. Test FAIL → perbaiki kode (bukan skip test) → jalankan ulang sampai PASS.
4. Laporan status test + coverage dilaporkan per milestone.

## Perintah Harian

```bash
# Jalankan semua test
php artisan test

# Jalankan test file tertentu
php artisan test --filter=TeknisiWorkflowTest

# Setup DB dev dari nol + seed
php artisan migrate:fresh --seed

# Jalankan dev server (Herd: arahkan site ke folder ini/public)
php artisan serve
```

## Catatan Coverage

Driver xdebug/pcov **tidak tersedia** di environment saat ini, sehingga
`php artisan test --coverage` belum bisa dijalankan. Opsi: aktifkan
Xdebug di Laravel Herd (edit php.ini) atau install pcov. Sampai tersedia,
laporan kualitas memakai jumlah test/assertion + matriks cakupan manual
(lihat laporan milestone).

## Roadmap Implementasi Berikutnya (belum dikerjakan)

- UI backoffice: Filament 3 panel (Owner/Admin/Finance/HR) — auth role,
  resource Customer/Order/Payment/Stok/Finance, dashboard notice.
- UI Teknisi mobile-first: Blade + Alpine (slider "mulai berangkat",
  check-in/out, form laporan + foto before/after).
- HTTP route + FormRequest + controller tipis memanggil Services.
- Deployment VPS + MySQL (keputusan T1) — detail menyusul.
