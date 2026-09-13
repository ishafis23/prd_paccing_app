# Backlog Modul Admin — Hasil Diskusi WA 13 Sept 2026

> STATUS: **DRAFT** — irisan sisi Admin dari
> [`../12-analisis-chat-13sep-dan-roadmap.md`](../12-analisis-chat-13sep-dan-roadmap.md)
> (sumber lengkap & detail teknis ada di sana; dokumen ini cuma daftar
> ringkas per-modul supaya gampang dibahas terpisah dgn Mutia/Miftah).
> Rujuk nomor `§3.x` di sana untuk detail kerja & status EXISTS/PARTIAL/MISSING.

## Sudah selesai
- Data Customer: kolom `jenis` (company/perorangan), `email`, lokasi peta
  (link Google Maps + geser pin), Import Excel customer massal (~4000 baris).
- Data Unit AC per customer company: tab "Unit AC" di Data Customer +
  Import Excel (§3.10, master data saja).
- Tombol Terkendala/Gagal + aksi "Jadwalkan Ulang" (§3.9) — admin bisa
  jadwalkan ulang order yg dilaporkan kendala oleh teknisi, tetap bisa
  dibatalkan kalau tidak jadi dilanjutkan.
- Bukti pembayaran per laporan + instansi/rumahan (§3.7) — field
  "Jenis Pelanggan" di form Order (default dari data customer, bisa
  diubah admin), guard wajib-bukti utk rumahan sebelum teknisi bisa
  menutup order.

## Belum dikerjakan (butuh kerja Admin-side)

| Ref | Item | Ringkas |
|---|---|---|
| §3.1 | Status order "Ada Perbaikan" | Admin ubah status order saat teknisi lapor ada sparepart yg perlu diganti, + estimasi selesai baru. |
| §3.2 | Pengeluaran operasional per trip | Catat uang operasional (makan+bensin) & biaya tak terduga per order/tim, bukan buku besar umum spt sekarang. |
| §3.3 | Halaman Orderan Harian | Dashboard admin: semua order yg berjalan hari ini (belum ada, cuma jadwal per-teknisi). |
| §3.4 | Import Excel dispatch massal | Assign order/teknisi massal utk klien banyak unit — **bergantung §3.10 lanjutan** (riwayat per unit), ditunda sampai fitur input orderan disentuh. |
| §3.5 | Re-assign PIC hari-H | Ganti teknisi penanggung jawab di hari-H kalau berhalangan — perlu dicek kelengkapan di OrderResource. |
| §3.11 | Verifikasi laporan teknisi | Admin klik acc/verifikasi tiap laporan masuk sebelum order lanjut ke tahap berikut. |
| §3.12 | Surat Jalan (korporat) | Cetak/kirim daftar unit yg akan dikerjakan ke nomor order — pola spt fitur resi yg sudah ada. |
| §3.13 | Tim Teknisi permanen (SPK) | Menu buat tim tetap (1 tim = 2 teknisi), assign order ke tim bukan pilih orang satu-satu. |

## Prioritas (mengikuti §4 di dokumen sumber)
Wajib sebelum 1 Okt: ~~§3.9~~ (selesai), ~~§3.7~~ (selesai), §3.11, §3.3.
Sisanya (§3.1, §3.2, §3.4, §3.12, §3.13) realistis Stage 2 kecuali
diputuskan lain oleh client.

## Pertanyaan terbuka yang perlu client
Lihat §5 di dokumen sumber (foto laporan, kategori pekerjaan, nominal
pengeluaran) — beberapa item di atas (§3.1, §3.2) tidak bisa mulai
dikerjakan sebelum itu terjawab.
