# Konsep Modul — Teknisi

Referensi: [PRD §4.2](../../PRD.md#42-modul-teknisi) · [Skema DB](../database-schema.md)

## 1. Ringkasan & Tujuan

Modul untuk petugas lapangan: melihat jadwal, absen di lokasi, dan melaporkan hasil kerja. Ini yang membuat status order bergerak dari `terjadwal` → `dikerjakan` → `selesai` (bersama verifikasi Admin). Prioritas: **kedua setelah Admin**, karena butuh order dari Admin untuk punya jadwal (lihat [`00-overview.md`](../00-overview.md)).

## 2. Fitur

1. **Jadwal** — daftar order yang di-assign ke teknisi ybs, per hari/minggu.
2. **Absensi** — check-in saat tiba di lokasi customer, check-out saat selesai.
3. **Laporan Pengerjaan** — catatan kerja, material terpakai, foto before/after.
4. **Status Pengerjaan** — teknisi update status order (`dikerjakan` → `selesai`/`butuh_followup`).
5. **Riwayat Pengerjaan** — histori order yang pernah dikerjakan teknisi ybs.
6. **Capaian Kerja** — ringkasan jumlah unit dikerjakan per periode (dilihat teknisi & jadi bahan evaluasi HRD).

## 3. Alur Pengguna

1. Login → lihat **Jadwal Hari Ini** (daftar order status `terjadwal` milik sendiri).
2. Tiba di lokasi → **check-in** (tercatat di `attendances`, terhubung ke `order_id`).
3. Order otomatis/manual berubah status `dikerjakan`.
4. Selesai kerja → isi **form laporan**: catatan, material terpakai, upload foto before/after.
5. Submit laporan → order berubah status `selesai` (atau `butuh_followup` jika ada kendala, mis. sparepart kurang) → **check-out**.
6. Admin menerima notifikasi laporan masuk untuk verifikasi & proses pembayaran.

## 4. Halaman/Menu

- Jadwal Saya (list order per hari, tombol check-in)
- Form Laporan Pengerjaan (dari halaman detail order aktif)
- Riwayat Pengerjaan Saya
- Capaian Kerja Saya (ringkasan bulanan: jumlah order selesai)

## 5. Tabel Terkait

| Tabel | Peran Teknisi |
|---|---|
| `orders` | read (miliknya sendiri) + update `status` |
| `attendances` | create (check-in/out) |
| `work_reports` | create |

## 6. Checklist Pengembangan

**Fase 1 (MVP):**
- [ ] Halaman jadwal per teknisi (query order `WHERE teknisi_id = auth()->id()`)
- [ ] Fitur check-in/check-out sederhana (timestamp, tanpa validasi lokasi ketat dulu)
- [ ] Form laporan pengerjaan + upload foto (2 file: before/after)
- [ ] Update status order dari sisi teknisi
- [ ] Riwayat pengerjaan per teknisi
- [ ] UI mobile-friendly (diakses dari HP browser, bukan aplikasi native)

**Fase 2:**
- [ ] Validasi lokasi check-in (GPS/geofence terhadap alamat customer)
- [ ] Rating/feedback dari customer masuk ke capaian kerja
- [ ] Notifikasi push/WA saat ada order baru di-assign
- [ ] Integrasi material terpakai dengan stok (bukan free-text)

## 7. Pertanyaan Terbuka

- Apakah check-in wajib pakai validasi lokasi (GPS) di Fase 1, atau cukup manual dulu?
- Kalau order butuh follow-up (sparepart kurang, dsb), bagaimana alurnya — jadi order baru atau tetap order yang sama dengan status ditunda?
- Bagaimana kalau satu order dikerjakan lebih dari satu teknisi (tim)? Apakah perlu tabel relasi many-to-many `order_technicians`, atau cukup satu PIC teknisi per order di Fase 1?
