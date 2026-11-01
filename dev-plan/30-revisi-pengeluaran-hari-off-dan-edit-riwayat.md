# 30 — Revisi pengeluaran harian: hari OFF dikecualikan & edit Riwayat Pengeluaran

Sumber: `dev-plan/req dev 1 nov 2026/` poin 6, 7, 8 (lanjutan poin 3 / commit `510d665`).

## Masalah (dari client)

| Poin | Kasus | Akar masalah |
|---|---|---|
| 6 | Bayu, 28 Okt berstatus **OFF**, tapi tetap absen untuk menyelesaikan laporan kemarin. Sistem menuntut catatan pengeluaran tgl 28 → "Administrasi belum tuntas". | `SyaratAdministrasiService::penghalangPengeluaranHarian` hanya melihat absen, tidak melihat hari OFF (`technician_off_days`). |
| 6 | Bayu, 30 Okt: pengeluaran diisi **teknisi lain** sebelum fitur Partner ada, jadi tidak tertaut ke Bayu. | Pengeluaran lama tidak punya `partner_id`, dan tidak ada cara menambahkannya. |
| 7 | Daftar Riwayat Pengeluaran tidak bisa diedit — client minta bisa menambah teknisi/partner agar terbaca di sistem. | Hanya ada Hapus (khusus pending); tidak ada Ubah. |
| 8 | Bayu mencatat 26 Okt, tapi akun Riski (partner 26 Okt) tidak tercatat → administrasi belum tuntas. Perlu opsi edit: input teknisi/partner, tambah pengeluaran hari itu, koreksi nominal salah. | Sama dengan poin 7. |

## Keputusan desain

1. **Hari OFF dikecualikan** dari kewajiban catatan pengeluaran, walau teknisi absen di hari itu (kasus 28 Okt). Dasarnya `technician_off_days` (tanggal + user), bukan status absen.
2. **Tombol "Ubah" di tiap baris Riwayat Pengeluaran**, hanya untuk **pencatat** (`teknisi_id`). Form-nya memakai form "Catat Pengeluaran" yang sudah ada (mode edit): tanggal, order, **partner**, kategori, qty, harga, nominal, keterangan.
3. **Partner boleh ditambah/diganti/dikosongkan lewat edit** — inilah jawaban kasus 30 Okt dan 26 Okt (pengeluaran lama ditautkan ke rekan, rekan langsung terbebas dari penghalang).
4. **"Tambah pengeluaran hari itu"** sudah bisa lewat form baru (tanggal bebas ≤ hari ini); tidak perlu fitur baru. Partner juga bisa mengisi pengeluarannya sendiri.
5. **Aturan status saat edit** (karena uang): 
   - Ubah **hanya** partner / keterangan / order → status **tidak berubah** (yang sudah Disetujui tetap Disetujui).
   - Ubah **tanggal, kategori, qty, harga, atau nominal** pada yang sudah Disetujui/Ditolak → kembali **Menunggu** (ACC ulang admin), `approved_by` dan catatan approval dikosongkan.
   - Yang masih Menunggu: bebas diubah.
6. Partner hanya bisa **melihat** (label "Dicatat oleh X"), tidak bisa Ubah/Hapus.
7. Validasi sama dengan form baru (tanggal ≤ hari ini, nominal 1.000–5.000.000, partner harus teknisi lain).

## Rencana eksekusi

1. `SyaratAdministrasiService::penghalangPengeluaranHarian` — buang tanggal yang ada di `technician_off_days` milik teknisi itu.
2. `LaporanPengeluaran` (Livewire) — properti `editId`; `mulaiEdit(id)` mengisi form dari record (cek pemilik); `submitPengeluaran` bercabang simpan baru / update; `batal` mereset `editId`; aturan status (poin 5).
3. `laporan-pengeluaran.blade.php` — tombol **Ubah** di baris milik sendiri; judul form berganti "Ubah Pengeluaran".
4. Tes (`PengeluaranHarianPartnerTest`): hari OFF tidak memblokir; edit menambah partner → partner terbebas; ubah nominal pada yang disetujui → kembali menunggu; ubah partner saja → tetap disetujui; partner/orang lain tidak bisa mengubah.
5. Jalankan tes terkait, commit, push.

## Di luar cakupan

- Perapian data lama: tidak ada skrip massal; cukup diedit satu per satu lewat tombol Ubah.
- Edit oleh admin dari panel admin (`ManajemenPengeluaranTeknisi`) — tidak diminta.
