# Rencana Pengerjaan — Revisi 17 Nov 2026

Urutan dari yang paling kecil/mandiri ke yang menyentuh data.

| Tahap | Poin | Isi | Verifikasi |
|---|---|---|---|
| 1 | 33, 27 | Filter metode bayar; filter periode Jadwal OFF | tinker/test query filter |
| 2 | 25, 26 | Jenis pelanggan otomatis; Unit AC bisa dicari | test Livewire form/ render halaman |
| 3 | 28, 29 | Migrasi `payroll_run_adjustments`, service (edit periode, hapus, tambah/hapus penyesuaian), UI, rincian + slip | feature test PayrollService |
| 4 | 30, 31 | Filter tanggal Performa; kolom Jasa/Material + rincian per teknisi | test service |
| 5 | 32 | Migrasi `orders.dibatalkan_oleh`; aksi Batalkan minta pihak; sub-tab 3.4 | test service |
| 6 | 24 | Pengaturan + halaman Bonus Admin | test service |
| 7 | — | Ringkasan progres + commit per tahap | `php artisan test` |
