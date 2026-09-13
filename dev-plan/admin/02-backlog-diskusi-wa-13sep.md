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

## Belum dikerjakan (realistis Stage 2)

| Ref | Item | Ringkas |
|---|---|---|
| §3.4 | Import Excel dispatch massal | Assign order/teknisi massal utk klien banyak unit — **bergantung §3.10 lanjutan** (riwayat per unit), ditunda sampai fitur input orderan disentuh. |
| §3.5 | Re-assign PIC hari-H | Ganti teknisi penanggung jawab di hari-H kalau berhalangan — perlu dicek kelengkapan di OrderResource. |
| §3.12 | Surat Jalan (korporat) | Cetak/kirim daftar unit yg akan dikerjakan ke nomor order — pola spt fitur resi yg sudah ada. |
| §3.13 | Tim Teknisi permanen (SPK) | Menu buat tim tetap (1 tim = 2 teknisi), assign order ke tim bukan pilih orang satu-satu. |

Kecuali diputuskan lain oleh client, item di atas boleh menyusul setelah
1 Okt.

## Pertanyaan terbuka yang perlu client
§3.1, §3.2, §3.8 sudah terjawab & selesai diimplementasikan (lihat
dev-plan/13). Sisa pertanyaan terbuka §5 di dokumen sumber (kalau ada)
sudah tidak menghalangi item wajib manapun.
