# Konsep Modul — Admin

Referensi: [PRD §4.1](../../PRD.md#41-modul-admin) · [Skema DB](../database-schema.md)

## 1. Ringkasan & Tujuan

Modul Admin adalah **pusat kendali harian**: satu-satunya titik masuk data customer dan order baru. Semua modul lain (Teknisi, Finance) menggantung ke data yang dibuat di sini. Prioritas pengembangan: **paling awal**, lihat [`00-overview.md`](../00-overview.md).

## 2. Fitur

1. **Data Customer** — CRUD, pencarian, filter per area (Makassar/Gowa/Maros) dan status (lead/aktif/nonaktif).
2. **Status Customer** — perubahan status manual oleh admin (mis. lead → aktif setelah order pertama selesai).
3. **Order/Pengerjaan** — buat order baru, pilih jenis layanan dari `service_catalog`, assign teknisi, jadwalkan tanggal/jam.
4. **Riwayat Pengerjaan** — daftar semua order per customer, termasuk laporan teknisi yang sudah masuk.
5. **Notice Servis Berikutnya** — daftar customer yang jatuh tempo servis (dari `service_reminders`), dengan aksi "tandai sudah dihubungi".
6. **Pembayaran** — catat metode, status (belum bayar/DP/lunas), jumlah dibayar per order.

## 3. Alur Pengguna

**A. Order baru:**
1. Admin cari customer existing atau buat baru.
2. Pilih jenis layanan & unit dari `service_catalog` → sistem isi harga otomatis.
3. Tentukan tanggal/jam & assign teknisi (lihat ketersediaan jadwal teknisi lain agar tidak bentrok).
4. Order tersimpan status `terjadwal` → otomatis muncul di jadwal teknisi terkait.

**B. Setelah teknisi selesai (order status `dikerjakan` → laporan masuk):**
1. Admin cek laporan pengerjaan dari teknisi.
2. Admin catat pembayaran → jika `lunas`, sistem otomatis: catat ke `incomes`, ubah status order jadi `selesai`, dan buat entri `service_reminders`.

**C. Notice servis berikutnya:**
1. Dashboard admin menampilkan daftar customer dengan `tanggal_servis_berikutnya` mendekati (mis. H-7).
2. Admin hubungi customer manual (Fase 1) → tandai status notice `sudah_dihubungi`.
3. (Fase 2) sistem kirim WA otomatis, admin tinggal follow-up jika ada respon.

## 4. Halaman/Menu

- Dashboard (ringkasan: order hari ini, notice jatuh tempo, order belum dibayar)
- Data Customer (list, detail, form tambah/edit)
- Order (list dengan filter status, form buat/edit, detail order + riwayat pembayaran & laporan)
- Notice Servis Berikutnya (list + aksi tandai kontak)
- Pembayaran (list transaksi, form catat pembayaran dari halaman detail order)

## 5. Tabel Terkait

| Tabel | Peran Admin |
|---|---|
| `customers` | full CRUD |
| `orders` | full CRUD, kecuali update laporan (milik Teknisi) |
| `service_catalog` | read (dan CRUD jika admin juga kelola harga) |
| `payments` | create/update |
| `service_reminders` | read + update status_notice |
| `work_reports` | read only (input dari Teknisi) |

## 6. Checklist Pengembangan

**Fase 1 (MVP):**
- [ ] CRUD Data Customer + filter area/status
- [ ] CRUD Order + pilih dari `service_catalog` + assign teknisi
- [ ] Halaman detail order (info order, laporan teknisi, riwayat pembayaran)
- [ ] Form catat pembayaran + auto-update status order & trigger income
- [ ] Auto-generate `service_reminders` saat order selesai+lunas
- [ ] Dashboard notice servis jatuh tempo

**Fase 2:**
- [ ] Kirim notifikasi WA otomatis ke customer (reminder & konfirmasi jadwal)
- [ ] Multi-item order (`order_items`) jika satu kunjungan bisa banyak jenis layanan
- [ ] Manajemen master `service_catalog` dari UI (bukan seed manual)

## 7. Pertanyaan Terbuka

- Apakah harga bisa berbeda per customer (nego) atau selalu ikut `service_catalog`?
- Siapa yang menentukan interval reminder per jenis layanan — default global atau bisa per order?
- Apakah admin perlu melihat lokasi/jarak teknisi vs customer saat assign (untuk efisiensi rute), atau assign manual berdasarkan pengalaman saja di Fase 1?
