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
| filament/filament | ^3.3 | panel backoffice Owner/Admin/Finance/HR di `/admin` (bundel Livewire 3) |
| Pest | ^3.8 (+ pest-plugin-laravel ^3.2) | framework test (aturan QA) |
| DB development/test | SQLite (file `database/database.sqlite`) | keputusan T3; produksi MySQL |
| Node/Vite | Tailwind v4 via `@tailwindcss/vite` | dipakai untuk layout mobile Teknisi (`npm run build` sebelum deploy) |
| Storage | disk `public` + `storage:link` | untuk foto laporan teknisi & bukti pengeluaran |

## UI yang Sudah Ada

- **Panel Filament** (`/admin`, provider `App\Providers\Filament\AdminPanelProvider`): resource
  Customer, ServiceCatalog, Order (aksi custom Assign Teknisi/Catat Pembayaran/Batalkan —
  bukan edit field mentah, lihat konvensi #2 di bawah), StockItem (+ aksi Stok Masuk/Penyesuaian),
  StockMovement (read-only), Payment (read-only), ServiceReminder (+ aksi Tandai Dihubungi),
  Expense, Income (read-only). Widget dashboard `RingkasanFinanceWidget` (laba-rugi, notice
  jatuh tempo, stok menipis). Akses panel diatur `User::canAccessPanel()` (Owner/Admin/Finance/HR
  saja) + Policy per model di `app/Policies` (mirroring role masing-masing Service).
- **Mobile Teknisi** (`/teknisi`, middleware `auth` + `role:teknisi`): Livewire component
  `JadwalHariIni`, `OrderDetail` (slider swipe "Mulai Berangkat" via Alpine + `wire:click`,
  check-in, form laporan dengan repeater material dari stok + upload foto), `RiwayatPengerjaan`,
  `CapaianKerja`. Layout `resources/views/layouts/teknisi.blade.php` (mobile-first, max-width
  480px). Login bersama di `/login` (`App\Livewire\Auth\Login`) — mengarahkan ke `/admin` atau
  `/teknisi` sesuai role setelah autentikasi; backoffice tetap bisa juga login langsung di
  `/admin/login` (bawaan Filament).

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
app/Filament/
├── Resources/        ← 11 resource backoffice: Customer, ServiceCatalog, Order,
│                        StockItem, StockMovement, Payment, ServiceReminder,
│                        Expense, Income + PaymentChannel (QRIS/rekening, B15-16)
│                        & User (kelola akun, B20)
└── Widgets/          ← RingkasanFinanceWidget
app/Policies/         ← 1 policy per model yang tampil di panel Filament,
                        mirroring role di Service masing-masing
app/Livewire/
├── Auth/Login.php    ← login bersama, redirect berdasar role
└── Teknisi/          ← JadwalHariIni, OrderDetail, RiwayatPengerjaan, CapaianKerja
app/Support/EnumOptions.php ← helper enum cases -> Filament Select options
tests/
├── Pest.php          ← bind Feature tests + RefreshDatabase
├── Unit/             ← test murni tanpa DB (EnumValuesTest)
└── Feature/          ← SchemaTest, ModelRelationsTest, RoleAccessTest,
                        AdminOrderFlowTest, StockTest, TeknisiWorkflowTest,
                        FinanceTest, LoginFlowTest, TeknisiMobileUiTest,
                        Filament/ (CustomerResourceTest, OrderResourceTest,
                        StockItemResourceTest, FinanceResourcesTest,
                        DashboardWidgetTest, PanelSmokeTest),
                        FilamentAuthorizationTest
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
8. **UI tidak pernah edit field mentah untuk state yang dijaga Service**:
   `Order.status`/`teknisi_id`, `Payment.*`, `StockItem.stok_saat_ini`,
   `ServiceReminder.status_notice` tidak punya form edit langsung di Filament —
   hanya lewat aksi custom (`Tables\Actions\Action::make(...)`) yang memanggil
   Service terkait, supaya income/reminder/stok movement tetap konsisten.
9. **Halaman Create yang berbasis Service** (Order, Expense) override
   `handleRecordCreation()` di page class untuk memanggil Service, bukan
   default Eloquent create — catch `BusinessRuleException`/`AuthorizationException`
   lalu `Notification::make()->danger()` + `$this->halt()`.
10. **Otorisasi Filament** lewat Policy (`app/Policies`), bukan permission
    granular — satu Policy per model yang tampil di panel, method-nya mirip
    `assertRole` di Service (Owner tetap bypass semua lewat `Gate::before`).

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

- Notifikasi WhatsApp otomatis (Fase 2, PRD §12/§11).
- Modul HRD penuh (kehadiran karyawan non-teknisi, jenjang karir) — tabel
  `employees`/`performance_reviews` & resource Filament terkait belum dibuat.
  Role `hr` sudah ada di panel (akses read-only Customer/Order) tapi belum
  ada resource HR-nya sendiri.
- `development_plans` (rencana pengembangan Finance, Fase 2).
- Export laporan Excel/PDF, grafik tren di dashboard.
- Deployment VPS + MySQL (keputusan T1) — detail menyusul; migrasi ditulis
  portable jadi tidak perlu ubah kode saat pindah dari SQLite.
- Live tracking lokasi teknisi saat status `menuju_lokasi` (Fase 2, lihat
  dev-plan Teknisi §7).
