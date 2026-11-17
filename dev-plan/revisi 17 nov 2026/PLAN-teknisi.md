# PLAN Sisi Teknisi (Portal Web + API + App) — Revisi 17 Nov 2026

Semua poin 24–33 inisiatifnya dari sisi admin. Dampak ke teknisi hanya pada hal yang **terlihat oleh teknisi**:

| Dari poin | Dampak ke teknisi | Pekerjaan | Status |
|---|---|---|---|
| 28 Edit/hapus periode gaji | Slip hanya terlihat setelah di-ACC; gaji draft yang salah dihapus admin tidak pernah tampil ke teknisi. Periode di slip mengikuti periode hasil edit. | Tidak ada perubahan kode di teknisi; cukup verifikasi API `GET /keuangan/gaji` membaca periode dari run. | Verifikasi |
| 29 Bonus / potongan lain | Item muncul di slip (PDF & web) dan rincian API. | Slip memakai `payroll_run_items` — adjustment ditulis sebagai item (`bonus_lain` / `potongan_lain`, label + catatan) sehingga slip ikut tanpa perubahan struktur. Cek API slip. | Ikut otomatis, diverifikasi |
| 32 Pembatalan customer vs kendala | Alur teknisi "Terkendala" tidak berubah (pihak customer/teknisi sudah wajib sejak poin 17 revisi 14 Nov). Data ini dipakai untuk persentase di dashboard. | Tidak ada. | Tidak ada pekerjaan |
| 31 Jasa vs material per teknisi | Teknisi tidak melihat dashboard pimpinan. Capaian teknisi (`/capaian`) tidak diubah. | Tidak ada. | Tidak ada pekerjaan |
| 25, 26 Form order | Hanya form admin. | Tidak ada. | Tidak ada pekerjaan |

## Untuk tim app mobile (repo terpisah)
Tidak ada kontrak API baru. Jika slip di app menampilkan rincian per item, pastikan baris dengan `kategori` `bonus_lain` / `potongan_lain` dirender (struktur sama dengan item bonus/potongan yang sudah ada).
