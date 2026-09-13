# Konsep Modul — Portal Customer (Corporate)

> STATUS: **DRAFT — butuh keputusan sebelum implementasi.** Lihat
> "Keputusan Terbuka" di §4. Sumber: diskusi WA 13 Sept 2026 (lihat
> [`../12-analisis-chat-13sep-dan-roadmap.md`](../12-analisis-chat-13sep-dan-roadmap.md)
> §3.6) — client sendiri menyebut ini Stage 2 KECUALI utk korporat besar
> (mis. Dafi, Kalla), yang disetujui masuk tahap 1.

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

## 4. Keputusan Terbuka (perlu dikonfirmasi sebelum mulai coding)

1. **Cara akses/login customer**:
   - **Opsi A — Token link permanen** (pola sama seperti fitur "resi"
     yang sudah ada: `customers.portal_token` acak, akses tanpa
     login lewat URL, mis. `/portal/{customer}/{token}`). Simpel, cepat
     dibangun, tapi siapa saja yang pegang link bisa akses (tidak ada
     password), dan tidak ada "sesi" per user PIC customer.
   - **Opsi B — Akun login sungguhan** (email+password, khusus utk PIC
     customer corporate, terpisah dari akun Admin/Teknisi). Lebih aman
     & bisa multi-user per customer (mis. beberapa staf sekolah), tapi
     perlu guard/tabel baru + alur lupa password, dsb — kerja lebih besar.
   - Rekomendasi: **Opsi A dulu** (konsisten dgn pola resi yg sudah
     terbukti dipakai), upgrade ke Opsi B kalau ternyata dibutuhkan
     multi-user per corporate.
2. **Ruang lingkup "progress"**: cuma tanggal jadwal berikutnya (pasif,
   dari `ServiceReminder`), atau juga live status order yang SEDANG
   dikerjakan (mis. "Teknisi menuju lokasi", real-time spt Peta Teknisi)?
3. **Timing pembangunan**: karena bergantung ke §3.4 (order multi-unit)
   yang sendiri belum jelas skedulnya (lihat §4 dokumen sumber), apakah
   portal ini digeser ke Stage 2 penuh, atau tetap dipaksa masuk sebelum
   1 Okt dgn scope terbatas (mis. cuma tampilkan daftar unit + jadwal
   customer secara umum, tanpa riwayat per-unit dulu)?
