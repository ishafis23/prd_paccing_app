# Backlog Modul Teknisi — Hasil Diskusi WA 13 Sept 2026

> STATUS: **DRAFT** — irisan sisi Teknisi dari
> [`../12-analisis-chat-13sep-dan-roadmap.md`](../12-analisis-chat-13sep-dan-roadmap.md)
> (sumber lengkap & detail teknis ada di sana). Rujuk nomor `§3.x` di sana
> untuk detail kerja & status EXISTS/PARTIAL/MISSING.

## Sudah selesai
- Peta lokasi customer muncul di detail order teknisi + tombol "Buka Rute".
- Fix tombol geser (Mulai Berangkat/Check-in/Selesaikan Order) yg tidak
  responsif di sebagian HP.
- Tombol "Terkendala/Gagal" (§3.9) — teknisi lapor kendala lapangan
  (alasan wajib diisi), order masuk status Terkendala menunggu admin
  jadwalkan ulang, tetap muncul di Jadwal Saya selama menunggu.
- Upload bukti pembayaran (§3.7) — foto bisa diunggah/diganti sebelum
  order ditutup, wajib utk rumahan (slider "Selesaikan Order" ditolak
  kalau belum ada), opsional utk instansi.
- Lapor kebutuhan sparepart (§3.1) + foto laporan per kategori (§3.8) —
  spesifikasi final di
  [`../13-ada-perbaikan-kategori-dan-pengeluaran-trip.md`](../13-ada-perbaikan-kategori-dan-pengeluaran-trip.md):
  tombol "Ada Perbaikan" di detail order (catatan + estimasi harga
  opsional), order tetap `dikerjakan` sambil menunggu admin konfirmasi
  ke customer. Form submitLaporan sekarang merender slot foto per baris
  layanan sesuai kategorinya (mis. Cuci AC: outdoor proses → indoor
  proses → indoor sebelum → indoor sesudah+suhu), bukan cuma 1 pasang
  foto sebelum/sesudah generik lagi.

## Belum dikerjakan (butuh kerja sisi Teknisi/portal HP)

| Ref | Item | Ringkas |
|---|---|---|
| §3.13 | Tim Teknisi permanen | Teknisi lihat keanggotaan tim tetapnya (bukan cuma anggota ad-hoc per order). |

Catatan §3.2 (input pengeluaran per trip): keputusan final tetap admin
yg input (bukan teknisi), konsisten dgn kebijakan sekarang — lihat
dev-plan/13 §4.

## Prioritas (mengikuti §4 di dokumen sumber)
Wajib sebelum 1 Okt: ~~§3.9~~ (selesai), ~~§3.7~~ (selesai). ~~§3.8~~ &
~~§3.1/§3.2~~ juga sudah selesai (dev-plan/13). Sisa: §3.13 (Stage 2).
