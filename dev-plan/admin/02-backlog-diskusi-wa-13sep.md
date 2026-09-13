# Backlog Modul Admin — Hasil Diskusi WA 13 Sept 2026

> STATUS: **DRAFT** — irisan sisi Admin dari
> [`../12-analisis-chat-13sep-dan-roadmap.md`](../12-analisis-chat-13sep-dan-roadmap.md)
> (sumber lengkap & detail teknis ada di sana; dokumen ini cuma daftar
> ringkas per-modul supaya gampang dibahas terpisah dgn Mutia/Miftah).
> Rujuk nomor `§3.x` di sana untuk detail kerja & status EXISTS/PARTIAL/MISSING.

## Sudah selesai
- Data Customer: kolom `jenis` (company/perorangan), `email`, lokasi peta
  (link Google Maps + geser pin), Import Excel customer massal (~4000 baris).
- Data Unit AC per customer (§3.10) — tab "Unit AC" di Data Customer,
  kini tampil utk **semua jenis customer termasuk rumahan** (sebelumnya
  cuma Company), + Import Excel. **Ditautkan ke order/laporan**: field
  "Unit AC" (opsional) di form Buat Order, "Tambah Layanan", & "Setujui
  Perbaikan" — supaya laporan teknisi & Surat Jalan menunjuk ke unit AC
  spesifik kalau customer punya lebih dari satu. 12 test baru
  (`tests/Feature/OrderAcUnitLinkTest.php`).
- Tombol Terkendala/Gagal + aksi "Jadwalkan Ulang" (§3.9) — admin bisa
  jadwalkan ulang order yg dilaporkan kendala oleh teknisi, tetap bisa
  dibatalkan kalau tidak jadi dilanjutkan.
- Bukti pembayaran per laporan + instansi/rumahan (§3.7) — field
  "Jenis Pelanggan" di form Order (default dari data customer, bisa
  diubah admin), guard wajib-bukti utk rumahan sebelum teknisi bisa
  menutup order.
- Verifikasi laporan teknisi (§3.11) — aksi "Verifikasi Laporan" per
  order, gate di pelunasan pembayaran (bukan slider teknisi) supaya
  tidak mengganggu alur lapangan.
- Halaman Orderan Harian (§3.3) — menu baru, daftar semua order lintas
  teknisi per tanggal + ringkasan status.

**Semua 5 item wajib-sebelum-1-Okt sudah selesai.**

- Order jadi multi-item, tombol "Ada Perbaikan" + pengeluaran per trip
  (§3.1, §3.2 — spesifikasi final di
  [`../13-ada-perbaikan-kategori-dan-pengeluaran-trip.md`](../13-ada-perbaikan-kategori-dan-pengeluaran-trip.md),
  menggantikan deskripsi ringkas §3.1/§3.2 di atas): order bisa punya
  beberapa baris layanan (`order_items`), admin tambah baris lewat aksi
  "Tambah Layanan"; admin lihat notice "Menunggu Konfirmasi Perbaikan"
  di Orderan Harian & detail order lalu Setujui (tambah baris layanan)
  atau Tolak; field "Order Terkait" opsional di form Expense buat catat
  pengeluaran per trip/order (nominal manual/bisa diedit).
- Re-assign PIC hari-H (§3.5) — aksi baru "Ganti PIC" (`OrderService::
  gantiPic()`), terpisah dari "Assign Teknisi" (sekarang cuma muncul kalau
  order belum punya PIC sama sekali). Ganti PIC TIDAK mereset status order
  (beda dari assignTechnician yg selalu balik ke terjadwal) — dipakai saat
  order sudah `menuju_lokasi`/`dikerjakan` dan PIC-nya berhalangan
  mendadak. Efek otomatis: attendance terbuka PIC lama ditutup, PIC lama
  lepas dari `order_technicians`, riwayat dicatat ke `catatan_admin`.
  Anggota tim lain (bukan PIC) tidak terganggu. 15 test baru
  (`tests/Feature/OrderGantiPicTest.php`).
- Surat Jalan korporat (§3.12) — pola sama dgn resi (B14a): token publik
  (`orders.surat_jalan_token`) + halaman cetak/PDF (`surat-jalan.blade.php`)
  berisi customer, jadwal, tim teknisi, daftar pekerjaan (dari
  `order_items`), kolom tanda tangan. Aksi "Surat Jalan" di tabel admin
  cuma muncul utk customer instansi (`jenis_pelanggan = company`) & order
  belum batal. 5 test baru (`tests/Feature/SuratJalanTest.php`).
- Tim Teknisi permanen (§3.13) — menu baru "Tim Teknisi" (`TeamResource`):
  buat tim tetap (nama + anggota, PIC = anggota pertama dipilih). Aksi
  "Assign Tim" di tabel Order (berdampingan dgn "Assign Teknisi") —
  pilih tim, seluruh anggotanya otomatis ditugaskan. `order_technicians`
  ad-hoc yg sudah ada tetap jadi sumber kebenaran siapa yg bertugas;
  `orders.team_id` cuma jejak tim mana yg dipakai. 8 test baru
  (`tests/Feature/TeamTest.php`).
- Import Excel dispatch massal (§3.4) — `OrderDispatchImportService`,
  header action "Buat Order Massal" di tab "Unit AC" (berdampingan dgn
  "Import Excel" unit). Bikin SATU order dgn banyak `order_items`
  sekaligus (1 baris file = 1 unit AC yg sudah terdaftar) — cocok utk 1
  kunjungan/trip yg mencakup banyak ruangan. Form pilih Jenis Layanan
  (katalog), harga default, jadwal, Assign Tim (opsional); file cuma
  perlu kolom `kode_unit` + `harga`/`catatan` opsional per baris. 8 test
  baru (`tests/Feature/OrderDispatchImportTest.php`).

## Belum dikerjakan (realistis Stage 2)

Semua item wajib & backlog admin dari diskusi 13 Sept sudah selesai.
Sisa: Portal Klien/Corporate (§3.6) — bagian non-login (histori per
unit, auto-reminder per kategori) belum dikerjakan, dan bagian
login/akun customer **blocked** menunggu keputusan client (1 akun per
corporate vs multi-user staf) — lihat dev-plan/12 §3.6.

## Pertanyaan terbuka yang perlu client
§3.1, §3.2, §3.8 sudah terjawab & selesai diimplementasikan (lihat
dev-plan/13). Satu pertanyaan baru masih terbuka: **skema akun login
Portal Customer** (§3.6) — 1 akun per customer company, atau boleh
banyak akun staf per customer? Ini menentukan skema tabel sebelum mulai
dikerjakan.
