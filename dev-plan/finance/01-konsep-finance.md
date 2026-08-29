# Konsep Modul — Finance

Referensi: [PRD §4.4](../../PRD.md#44-modul-finance) · [Skema DB](../database-schema.md)

## 1. Ringkasan & Tujuan

Modul untuk melihat kesehatan keuangan usaha: pendapatan (otomatis dari transaksi order) vs pengeluaran (input manual). Prioritas: **ketiga**, karena pendapatan bergantung pada pembayaran yang dicatat di modul Admin (lihat [`00-overview.md`](../00-overview.md)). Pengeluaran sendiri tidak bergantung modul lain sehingga bisa mulai diinput sejak awal.

## 2. Fitur

1. **Pendapatan** — otomatis tercatat dari `payments` berstatus lunas, dikategorikan **Jasa** (cuci/service) atau **Material** (pengadaan AC).
2. **Pengeluaran** — input manual, kategori: Material, Perawatan, Operasional.
3. **Rencana Pengembangan** — catatan rencana investasi/anggaran ke depan (Fase 1: cukup catatan sederhana, bukan modul approval kompleks).
4. **Laporan** — ringkasan laba rugi sederhana per periode (pendapatan − pengeluaran), breakdown per kategori.

## 3. Alur Pengguna

**A. Pendapatan (otomatis):**
1. Admin catat pembayaran lunas pada suatu order.
2. Sistem otomatis membuat entri di `incomes` dengan kategori sesuai `service_catalog.jenis_layanan` order tsb.
3. Finance/Owner melihat rekap di dashboard tanpa input manual tambahan.

**B. Pengeluaran (manual):**
1. Finance/Admin input pengeluaran: pilih kategori, nominal, tanggal, keterangan, opsional bukti (foto struk).
2. Tersimpan di `expenses`, langsung masuk perhitungan laba rugi periode berjalan.

**C. Laporan:**
1. Pilih rentang periode (bulanan/custom).
2. Sistem tampilkan: total pendapatan (breakdown jasa/material), total pengeluaran (breakdown 3 kategori), laba/rugi bersih.

## 4. Halaman/Menu

- Dashboard Finance (ringkasan bulan berjalan: pendapatan, pengeluaran, laba/rugi)
- Pendapatan (list, read-only — sumber dari order, dengan link ke order terkait)
- Pengeluaran (list + form tambah/edit)
- Rencana Pengembangan (list catatan rencana + status)
- Laporan (filter periode, export — Fase 2 bisa export Excel/PDF)

## 5. Tabel Terkait

| Tabel | Peran Finance |
|---|---|
| `incomes` | read only (write otomatis dari sistem saat payment lunas) |
| `expenses` | full CRUD |
| `development_plans` | full CRUD (Fase 2) |
| `payments` | read (referensi sumber income) |

## 6. Checklist Pengembangan

**Fase 1 (MVP):**
- [ ] Auto-create `incomes` saat `payments.status` berubah jadi `lunas` (trigger dari modul Admin)
- [ ] CRUD `expenses` dengan 3 kategori
- [ ] Dashboard ringkasan pendapatan vs pengeluaran (bulan berjalan)
- [ ] Laporan laba rugi sederhana per rentang tanggal

**Fase 2:**
- [ ] Modul `development_plans` (rencana pengembangan) dengan target anggaran & progress
- [ ] Export laporan ke Excel/PDF
- [ ] Grafik tren pendapatan/pengeluaran per bulan
- [ ] Breakdown profitabilitas per jenis layanan atau per teknisi

## 7. Pertanyaan Terbuka

- Apakah pembayaran **DP** (belum lunas) tetap dicatat sebagian sebagai pendapatan (accrual), atau pendapatan hanya diakui saat status `lunas` penuh?
- Siapa yang berwenang input/approve pengeluaran besar (mis. ada batas nominal yang perlu approval Owner)?
- Format laporan seperti apa yang paling dibutuhkan Owner — cukup ringkasan angka, atau perlu grafik visual sejak Fase 1?
