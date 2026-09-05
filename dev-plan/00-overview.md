# Overview — Dev Plan One Gate System Paccing

Dokumen ini adalah peta jalan teknis, turunan dari konsep di [`../PRD.md`](../PRD.md). Detail per modul dipecah ke folder masing-masing agar mudah dibahas terpisah dengan client per bagian (Admin, Teknisi, HRD, Finance), sementara skema database tetap satu sumber kebenaran karena tabel-tabelnya saling terhubung.

## Struktur Dev Plan

```
dev-plan/
├── 00-overview.md          ← dokumen ini
├── database-schema.md      ← skema tabel & relasi (lintas modul)
├── admin/01-konsep-admin.md
├── teknisi/01-konsep-teknisi.md
├── hrd/01-konsep-hrd.md
└── finance/01-konsep-finance.md
```

`03-tech-setup.md` (langkah setup project Laravel) menyusul setelah keputusan hosting/server difinalkan bersama client (lihat PRD §10).

## Kenapa Skema Database Tidak Dipisah per Modul

Satu order melewati banyak modul sekaligus: dibuat oleh **Admin**, dikerjakan **Teknisi**, hasil pembayarannya masuk **Finance**, dan (untuk teknisi sebagai karyawan) absensinya tercatat di **HRD**. Kalau skema dipecah per folder modul, tabel seperti `orders` atau `attendances` akan terduplikasi/tidak konsisten. Jadi: **konsep & fitur dipecah per modul, skema tetap satu**, dan tiap dokumen modul menunjuk ke tabel mana yang relevan.

## Urutan Pengerjaan yang Disarankan

Urutan ini beda dari urutan modul di PRD, karena disusun berdasarkan **dependency alur data**, bukan besar-kecilnya modul:

1. **Admin (core)** — data customer & order harus ada duluan, karena semua modul lain menggantung ke sini.
2. **Teknisi (core)** — butuh order dari Admin untuk punya jadwal; laporan pengerjaan dari sini yang nanti memicu status "selesai" di Admin dan pemicu reminder servis berikutnya.
3. **Finance dasar** — pendapatan otomatis ditarik dari pembayaran order (Admin) yang sudah lunas; pengeluaran bisa diinput manual sejak awal, tidak bergantung modul lain.
4. **HRD** — secara data paling independen (data karyawan, absensi umum), tapi prioritas paling belakang karena tidak menyentuh alur transaksi harian secara langsung. Kehadiran teknisi di lapangan sudah tercakup di modul Teknisi.

Ini konsisten dengan pembagian Fase di PRD §9, hanya lebih rinci soal urutan *di dalam* Fase 1.

## Fase Pengembangan (ringkas, detail per modul ada di masing-masing folder)

| Fase | Modul | Fokus |
|---|---|---|
| **1 — MVP** | Admin, Teknisi, Finance (dasar) | Alur transaksi harian: order → pengerjaan → pembayaran → pendapatan tercatat → reminder servis berikutnya |
| **2 — Lanjutan** | HRD (penuh), Finance (lanjutan), Admin/Teknisi (peningkatan) | Evaluasi performa & jenjang karir, rencana anggaran, notifikasi WA otomatis, inventory sederhana |

## Definisi "Selesai" untuk Fase 1

Fase 1 dianggap selesai ketika alur berikut bisa dijalankan end-to-end di sistem tanpa keluar ke WA/Excel manual:

1. Admin input customer baru & buat order.
2. Order ter-assign ke teknisi dan muncul di jadwal teknisi.
3. Teknisi absen, kerjakan, input laporan, ubah status selesai.
4. Admin catat pembayaran → status order jadi lunas.
5. Pendapatan otomatis tercatat di Finance.
6. Sistem menampilkan notice tanggal servis berikutnya untuk customer tsb.

## Pertanyaan Lintas Modul yang Masih Terbuka

Lihat juga pertanyaan spesifik di tiap dokumen modul. Yang sifatnya lintas modul:

- Apakah satu order bisa berisi lebih dari satu jenis layanan/unit sekaligus (mis. cuci 3 AC beda PK dalam satu kunjungan)? Ini menentukan apakah `orders` perlu tabel detail (`order_items`) sejak Fase 1 atau bisa fase 2 (lihat catatan di `database-schema.md`).
- Siapa saja yang akan jadi user aktif di Fase 1 — apakah role Finance & HRD dirangkap Owner/Admin dulu (sesuai asumsi PRD §3)?

## Status Implementasi (6 September 2026)

Keputusan atas semua pertanyaan terbuka (bisnis + teknis) sudah difinalkan —
lihat [`02-keputusan-eksekusi.md`](02-keputusan-eksekusi.md). Setup teknis &
konvensi kode tercatat di [`03-tech-setup.md`](03-tech-setup.md).

**Selesai (engine Fase 1, teruji):**
- Laravel 12 + Pest + spatie/permission + SQLite (dev/test) + seeder
  (role, katalog layanan, akun demo owner/admin/teknisi1-2).
- Migrasi & model 13 tabel Fase 1 sesuai `database-schema.md` (+ soft deletes,
  index, enum string).
- Service domain: `OrderService` (create/assign/cancel), `TeknisiService`
  (berangkat/check-in/submit laporan → stok keluar), `PaymentService`
  (DP/lunas → income + reminder otomatis), `StockService` (masuk/keluar/
  penyesuaian), `FinanceService` (expense + laba rugi).
- 56 test hijau (164 assertion) — cakupan: skema, relasi, role access,
  alur order→pengerjaan→pembayaran→income→reminder end-to-end, stok minus,
  larangan akses lintas role.

**Belum dikerjakan (roadmap berikutnya):** UI backoffice Filament, UI
teknisi mobile-first, HTTP layer (controller/route/FormRequest), deployment
VPS+MySQL, tabel Fase 2 (employees, performance_reviews, development_plans).
