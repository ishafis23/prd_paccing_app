# Konsep Modul — Portal Customer (Corporate)

> STATUS: **DITUNDA KE STAGE 2 (keputusan 13 Sept 2026)** — digeser
> penuh sampai alur order multi-unit (§3.4) selesai duluan, supaya
> riwayat per-unit yang ditampilkan akurat sejak awal. Keputusan akses
> & scope sudah final (lihat §4) — dokumen ini jadi acuan siap-pakai
> saat waktunya tiba, TIDAK PERLU dibahas ulang dari nol. Sumber: diskusi
> WA 13 Sept 2026, lihat
> [`../12-analisis-chat-13sep-dan-roadmap.md`](../12-analisis-chat-13sep-dan-roadmap.md)
> §3.6.

## 1. Ringkasan & Tujuan

Halaman khusus untuk **customer company** (bukan perorangan) yang punya
banyak unit AC (sekolah, kantor, dll) untuk memantau:
1. **Riwayat pencucian per unit AC** — kapan terakhir dicuci, siapa
   teknisinya, catatan kondisi.
2. **Progress/jadwal berikutnya** — kapan unit tsb dijadwalkan dicuci
   lagi, dan status order yang sedang berjalan (kalau ada).

Nilai plus ke klien corporate: transparansi & bukti kerja terdokumentasi
per unit, bukan cuma per invoice/order.

Fondasi data sudah ada sebagian: `CustomerAcUnit` (master data unit AC,
lihat §3.10) dan `ServiceReminder` (jadwal servis berikutnya, per
customer — belum per unit).

## 2. Fitur (usulan awal)

1. **Daftar Unit AC customer** — dari `CustomerAcUnit`: kode unit,
   ruangan, jenis, PK.
2. **Riwayat pencucian per unit** — **BELUM ada datanya**: `WorkReport`
   sekarang tersambung ke `Order`, bukan ke unit AC spesifik. Perlu
   kolom penghubung (`work_reports.customer_ac_unit_id` atau tabel
   pivot) supaya laporan teknisi bisa ditandai "ini untuk unit AC yang
   mana" saat order melibatkan banyak unit sekaligus.
3. **Jadwal berikutnya per unit** — `ServiceReminder` sekarang per
   **customer** (satu tanggal utk semua), bukan per unit. Perlu
   diperluas jadi per-unit (`customer_ac_unit_id` nullable di
   `service_reminders`, atau tabel baru) supaya tiap ruangan bisa beda
   jadwal (mis. lantai 1 baru dicuci bulan lalu, lantai 2 belum).
4. **Status order berjalan** — kalau ada order aktif yg mencakup unit
   tsb, tampilkan status ringkas (terjadwal/menuju lokasi/dikerjakan).

## 3. Ketergantungan ke fitur lain

- §3.4 (Import Excel dispatch massal) & alur input order corporate multi-unit
  — perlu ada dulu supaya satu Order bisa mencakup banyak `CustomerAcUnit`
  sekaligus (baru dari situ riwayat per-unit bisa tercatat otomatis).
- §3.8 (foto laporan per kategori) — kalau foto laporan juga mau
  ditandai per-unit (bukan cuma per-order), portal ini butuh itu juga.

**Konsekuensi**: portal ini secara teknis **tidak bisa dibangun penuh**
sebelum alur order multi-unit (§3.4) ada — kalau dipaksa sebelum itu,
"riwayat per unit" isinya akan kosong/tidak akurat karena laporan
teknisi belum tertaut ke unit mana pun.

## 4. Keputusan (final, 13 Sept 2026)

1. **Cara akses/login customer**: **Opsi B — akun login sungguhan**
   (email+password), khusus PIC customer corporate, terpisah dari akun
   Admin/Teknisi. Bukan token-link spt resi.
   - Konsekuensi teknis: perlu guard Laravel baru (mis. `customer`),
     tabel akun terpisah dari `users` (atau kolom login di `customers`
     kalau 1 akun = 1 customer; kalau butuh multi-user per corporate,
     perlu tabel `customer_users` tersendiri — **perlu diperjelas lagi
     saat mulai dikerjakan**: apakah 1 customer boleh punya beberapa
     akun staf, atau cukup 1 akun per customer?), alur lupa password,
     halaman login terpisah dari `/admin` dan `/teknisi` (mis. `/portal/login`).
2. **Ruang lingkup "progress"**: belum difinalkan detail teknisnya —
   dibahas lagi saat mulai dikerjakan (tunggu §3.4 selesai, lihat poin 3).
3. **Timing pembangunan**: **ditunda penuh ke Stage 2** — baru mulai
   setelah alur order multi-unit (§3.4) selesai, supaya riwayat per-unit
   akurat sejak awal. Tidak dipaksakan sebelum 1 Okt.
