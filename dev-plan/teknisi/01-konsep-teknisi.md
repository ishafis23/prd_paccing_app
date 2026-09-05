# Konsep Modul — Teknisi

Referensi: [PRD §4.2](../../PRD.md#42-modul-teknisi) · [Skema DB](../database-schema.md)

## 1. Ringkasan & Tujuan

Modul untuk petugas lapangan: melihat jadwal, absen di lokasi, dan melaporkan hasil kerja. Ini yang membuat status order bergerak dari `terjadwal` → `menuju_lokasi` → `dikerjakan` → `selesai` (bersama verifikasi Admin). Prioritas: **kedua setelah Admin**, karena butuh order dari Admin untuk punya jadwal (lihat [`00-overview.md`](../00-overview.md)).

## 2. Fitur

1. **Jadwal** — daftar order yang di-assign ke teknisi ybs, per hari/minggu.
2. **Mulai Pengerjaan (slider "meluncur ke lokasi")** — dari order status `terjadwal`, teknisi geser slider konfirmasi (gaya Gojek "menuju lokasi") untuk menandai dia berangkat ke TKP → order berubah status `menuju_lokasi`.
3. **Absensi** — check-in saat tiba di lokasi customer, check-out saat selesai.
4. **Laporan Pengerjaan** — catatan kerja, material terpakai (dipilih dari stok, bukan free-text), foto before/after, disubmit lewat tombol **"Lapor Selesai"**.
5. **Status Pengerjaan** — teknisi menggerakkan status order sepanjang alur `terjadwal` → `menuju_lokasi` → `dikerjakan` → `selesai`/`butuh_followup`.
6. **Riwayat Pengerjaan** — histori order yang pernah dikerjakan teknisi ybs.
7. **Capaian Kerja** — ringkasan jumlah unit dikerjakan per periode (dilihat teknisi & jadi bahan evaluasi HRD).

## 3. Alur Pengguna

1. Login → lihat **Jadwal Hari Ini** (daftar order status `terjadwal` milik sendiri).
2. Siap berangkat → geser **slider "Mulai Berangkat ke Lokasi"** di halaman detail order → status order jadi `menuju_lokasi` (mirip status "menuju lokasi" di aplikasi ojek online — tanda bagi Admin/Owner bahwa teknisi sudah on the way, bukan sekadar terjadwal).
3. Tiba di lokasi → **check-in** (tercatat di `attendances`, terhubung ke `order_id`) → status order jadi `dikerjakan`.
4. Selesai kerja → isi **form laporan**: catatan, pilih material yang dipakai dari daftar stok (`stock_items`) beserta jumlahnya, upload foto before/after.
5. Klik **"Lapor Selesai"** → submit `work_report` + baris `work_report_materials` → sistem otomatis kurangi stok terkait (`stock_movements` jenis `keluar`) → order berubah status `selesai` (atau `butuh_followup` jika ada kendala, mis. sparepart kurang) → **check-out**.
6. Admin menerima notifikasi laporan masuk untuk verifikasi & proses pembayaran.

## 4. Halaman/Menu

- Jadwal Saya (list order per hari)
- Detail Order Aktif — slider "Mulai Berangkat ke Lokasi" (muncul saat status `terjadwal`), tombol check-in (saat `menuju_lokasi`)
- Form Laporan Pengerjaan — catatan, pilih material dari stok + jumlah (bisa lebih dari satu item), upload foto, tombol "Lapor Selesai"
- Riwayat Pengerjaan Saya
- Capaian Kerja Saya (ringkasan bulanan: jumlah order selesai)

## 5. Tabel Terkait

| Tabel | Peran Teknisi |
|---|---|
| `orders` | read (miliknya sendiri) + update `status` (`menuju_lokasi` via slider, `dikerjakan`/`selesai`/`butuh_followup` via check-in/laporan) |
| `attendances` | create (check-in/out) |
| `work_reports` | create |
| `work_report_materials` | create (bagian dari submit laporan) |
| `stock_items` | read (dipilih saat isi laporan) |

## 6. Checklist Pengembangan

**Fase 1 (MVP):**
- [ ] Halaman jadwal per teknisi (query order `WHERE teknisi_id = auth()->id()`)
- [ ] Komponen slider swipe-to-confirm "Mulai Berangkat ke Lokasi" (update status order jadi `menuju_lokasi`)
- [ ] Fitur check-in/check-out sederhana (timestamp, tanpa validasi lokasi ketat dulu)
- [ ] Form laporan pengerjaan: pilih material dari `stock_items` (bukan free-text) + jumlah, upload foto (2 file: before/after), tombol "Lapor Selesai"
- [ ] Submit laporan otomatis membuat `work_report_materials` + `stock_movements` jenis `keluar` per item dipakai
- [ ] Update status order dari sisi teknisi (`menuju_lokasi` → `dikerjakan` → `selesai`/`butuh_followup`)
- [ ] Riwayat pengerjaan per teknisi
- [ ] UI mobile-friendly (diakses dari HP browser, bukan aplikasi native)

**Fase 2:**
- [ ] Validasi lokasi check-in (GPS/geofence terhadap alamat customer)
- [ ] Rating/feedback dari customer masuk ke capaian kerja
- [ ] Notifikasi push/WA saat ada order baru di-assign
- [ ] Live tracking lokasi teknisi saat status `menuju_lokasi` (peta, bukan cuma status teks)

## 7. Pertanyaan Terbuka

- Apakah check-in wajib pakai validasi lokasi (GPS) di Fase 1, atau cukup manual dulu?
- Kalau order butuh follow-up (sparepart kurang, dsb), bagaimana alurnya — jadi order baru atau tetap order yang sama dengan status ditunda?
- Bagaimana kalau satu order dikerjakan lebih dari satu teknisi (tim)? Apakah perlu tabel relasi many-to-many `order_technicians`, atau cukup satu PIC teknisi per order di Fase 1?
- Slider "Mulai Berangkat" — apakah wajib digeser dulu sebelum bisa check-in, atau teknisi boleh langsung check-in tanpa lewat status `menuju_lokasi` (mis. kalau lupa geser slider di jalan)?
- Kalau stok yang dipilih di form laporan ternyata kurang dari jumlah yang dibutuhkan (`stok_saat_ini` tidak cukup), apakah laporan tetap boleh disubmit (stok jadi minus) atau harus diblokir sampai admin restock?
