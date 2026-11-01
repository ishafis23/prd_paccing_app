# 12 — Proses: Keuangan (Pengeluaran, Pendapatan, Setoran, Kasbon)

Padanan web: `Keuangan` (tab Pengeluaran & Pendapatan), `LaporanPengeluaran`, `SetoranTunai`. Service: `CashHandoverService`, `OmsetService`, `TechnicianIncentiveService`; **`TeknisiExpenseService` belum ada** (refactor prasyarat, dokumen 02 §6).

## 1. Pengeluaran harian

Aturan (revisi client 1 Nov, poin 3/4/6/7/8; `dev-plan/30`):

- Tiap **hari teknisi absen** wajib ada catatan pengeluaran, kecuali **hari OFF** dan hari ini (hari-hari lampau dalam tenggang 7 hari, `config/administrasi.php`). Tidak ada → penghalang "Administrasi belum tuntas".
- Satu catatan bisa memuat **Partner Tim/Pendamping**: dihitung untuk **dua** teknisi, cukup satu yang menginput. Partner melihatnya ("Dicatat oleh X") tetapi tidak bisa mengubah/menghapus.
- Pencatat bisa **Ubah** kapan saja (partner, nominal, kategori, tanggal, keterangan, order). Ubah tanggal/kategori/qty/harga/nominal pada yang sudah Disetujui/Ditolak → kembali **Menunggu**; ubah partner/keterangan/order saja → status tetap.
- Filter riwayat **Perbulan** atau **Perhari/Tanggal**; ringkasan "Hari Ini" & "Bulan Ini" tetap.
- Nominal = qty × harga, bisa dikoreksi; batas 1.000–5.000.000; tanggal ≤ hari ini.

### Endpoint

| Method & path | Catatan |
|---|---|
| `GET /pengeluaran?mode=bulanan\|harian&bulan=&tanggal=&kategori=&status=&page=` | termasuk entri tempat user menjadi partner (flag `sebagai_partner`, `dicatat_oleh`); sertakan `ringkasan` (hari ini/bulan ini) dan `breakdown` kategori |
| `GET /pengeluaran/opsi` | kategori (`TeknisiExpense::kategoriOpsi()`), daftar order terkait (≤100), daftar calon partner (teknisi lain) |
| `POST /pengeluaran` | body: `tanggal_input`, `kategori`, `qty`, `harga`, `nominal`, `keterangan?`, `order_id?`, `partner_id?` |
| `PUT /pengeluaran/{id}` | hanya pencatat (selain itu 404); respons memuat `status` baru + `perlu_acc_ulang` |
| `DELETE /pengeluaran/{id}` | hanya pencatat & status `pending` |

Route lama `teknisi.expense.*` (JSON, `TeknisExpenseController`) di `routes/web.php` diganti oleh ini; tes `ExpenseTrackingTest` yang sudah gagal sebelum perubahan hari ini perlu diperbaiki/dipensiunkan saat refactor.

## 2. Pendapatan jasa (portal "Keuangan > Pendapatan")

- Hanya **omset JASA** (material tidak tampil), mengikuti split yang dicatat admin di Catat Pembayaran bila ada; fallback rincian item.
- Tanggal = **tanggal order dijadwalkan** (bukan tanggal laporan/bayar).
- Daftar **hanya order yang dia PIC**; order tim tempat dia anggota tidak masuk.
- Mode Bulanan/Harian; kartu Hari Ini, Bulan Ini, Total.
- Mode Harian menampilkan kartu **Game 5 (omset tim)** & **Game 6 (unit)**: tercapai/belum, target, bonus (`ringkasanGames56`).

| Method & path | Sumber |
|---|---|
| `GET /pendapatan/ringkasan` | kartu hari ini/bulan ini/total |
| `GET /pendapatan?mode=&tanggal=&bulan=` | daftar order + total + (mode harian) blok `games` |

## 3. Setoran tunai

Padanan `SetoranTunai`, `CashHandoverService`: kewajiban setor (order bayar tunai), status telat (menahan berangkat — dokumen 13), catat setoran + foto bukti, verifikasi oleh admin.

| Method & path | Service |
|---|---|
| `GET /setoran` | `kewajiban`, `telat`, `ringkasanTeknisi`, riwayat `CashHandover` |
| `POST /setoran` | `CashHandoverService::catat($order, $user, [...])` + foto (multipart) |

## 4. Kasbon

Saat ini **tidak ada halaman teknisi** untuk kasbon (dikelola admin, `TechnicianAdvance`, dev-plan/28). Fase 2 opsional, baca saja: `GET /kasbon` (sisa, jadwal potong). Tanyakan ke client apakah teknisi perlu melihatnya.

## 5. Perilaku khusus app

- Uang dikirim/diterima sebagai **integer rupiah**; format "Rp 1.075.000" di app.
- Form pengeluaran dipakai ulang untuk **catat** dan **ubah** (state `editId`), seperti web.
- Antrean offline untuk pengeluaran: **boleh** (tidak bergantung jam server), dengan `client_uuid` agar kirim ulang tidak membuat duplikat.
- Pilihan partner dimuat dari `/pengeluaran/opsi`; mengosongkan partner = hapus tautan.

## 6. Pengujian

`tests/Feature/Api/PengeluaranTest.php`: catat/ubah/hapus, partner terbebas dari penghalang setelah ditautkan, ubah nominal pada yang disetujui → `pending`, partner/non-pencatat tidak bisa ubah/hapus, filter harian vs bulanan, validasi nominal & partner (bukan diri sendiri/non-teknisi). `PendapatanTest.php`: tanggal jadwal (kasus 31 Okt vs 1 Nov), hanya PIC, split admin dipakai, blok Game 5/6 (kasus 26 Okt: omset 1.075.000 ✓, 7 unit ✗ target 12). Tes web terkait (`TeknisiKeuanganCapaianTest`, `PengeluaranHarianPartnerTest`) tetap lulus.
