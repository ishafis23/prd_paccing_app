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

## Belum dikerjakan (butuh kerja sisi Teknisi/portal HP)

| Ref | Item | Ringkas |
|---|---|---|
| §3.1 | Lapor kebutuhan sparepart | Teknisi lapor ke admin saat cuci AC ternyata butuh perbaikan/sparepart. |
| §3.2 | Input pengeluaran per trip | Teknisi (atau admin dari laporan teknisi) catat uang operasional & biaya tak terduga per order. |
| §3.7 | Upload bukti pembayaran | Upload bukti transfer via portal (bukan kirim WA) — wajib utk rumahan, opsional utk instansi (ditentukan admin di SPK). |
| §3.8 | Foto laporan per kategori | Ganti dari 1 pasang foto sebelum/sesudah jadi beberapa slot foto sesuai kategori pekerjaan (cuci ac/tambah freon/service/dst) — **masih ada 2 versi requirement beda, nunggu klarifikasi client (lihat §5 dokumen sumber)**. |
| §3.13 | Tim Teknisi permanen | Teknisi lihat keanggotaan tim tetapnya (bukan cuma anggota ad-hoc per order). |

## Prioritas (mengikuti §4 di dokumen sumber)
Wajib sebelum 1 Okt: ~~§3.9~~ (selesai), §3.7. §3.8 & §3.1/§3.2 nunggu
jawaban client dulu (§5 dokumen sumber) sebelum bisa mulai desain
teknisnya.
