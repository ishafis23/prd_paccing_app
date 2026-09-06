# Multi-Teknisi per Order (Tim Pengerjaan) — B21

> STATUS: **DISETUJUI (7 September 2026)** — revisi atas keputusan B9
> ("1 PIC per order, `order_technicians` ditunda Fase 2"). Arahan owner:
> order bisa di-assign ke beberapa teknisi; tiap teknisi melihatnya di portal
> masing-masing; salah satu anggota yang memulai (slider/check-in/laporan);
> hasil tetap tercatat untuk tiap teknisi seperti pada assigned pertama.
> Tercatat di `02-keputusan-eksekusi.md` & `database-schema.md`.

## 1. Model Data

- Tabel baru `order_technicians` (order_id FK cascade, teknisi_id FK cascade,
  unique(order_id, teknisi_id)) = **keanggotaan tim penuh, termasuk PIC**.
- `orders.teknisi_id` dipertahankan = **PIC** (penanggung jawab / assigned
  pertama) — kompatibel dengan relasi/tampilan lama (`Order.teknisi`).
- Konsekuensi: saat assign PIC pertama, sekaligus dibuat baris
  `order_technicians`; legacy order (data lama yg cuma punya `teknisi_id`)
  tetap dikenali lewat fallback ke kolom tersebut.

## 2. Aturan Alur (default, bisa direvisi)

1. **Admin**: "Assign Teknisi" (set PIC) → juga jadi anggota; "Tambah Anggota
   Tim" (satu per satu; duplikat ditolak). Tidak bisa ditambah saat order
   `selesai`/`batal`. Lepas anggota: menyusul (versi ini belum ada).
2. **Portal teknisi**: Jadwal Hari Ini, Riwayat, dan Capaian menampilkan order
   bila user adalah PIC **atau** anggota `order_technicians`.
3. **Aksi lapangan** (slider berangkat, check-in, submit laporan, catat metode
   bayar) boleh dilakukan **anggota mana pun** — guard service berubah dari
   "harus PIC" menjadi "harus anggota tim". Non-anggota → AuthorizationException.
4. **Attendance**: tercatat per user yang benar-benar check-in
   (`attendances.user_id` = aktor). Saat laporan disubmit, SEMUA attendance
   terbuka milik anggota tim di-check-out (semua yang hadir tercatat keluar).
5. **Laporan**: `work_reports.teknisi_id` = anggota yang submit; riwayat order
   tetap muncul di tiap anggota; capaian dihitung per anggota tim.

## 3. Definisi Selesai

- Assign beberapa teknisi ke satu order dari panel admin.
- Kedua teknisi melihat order di Jadwal & Riwayat & Capaian masing-masing.
- Salah satu (bukan PIC) bisa berangkat → check-in (attendance atas namanya)
  → submit laporan; attendance seluruh anggota yang hadir tertutup; order
  selesai + token resi; riwayat/capaian tercatat utk kedua teknisi.
- Non-anggota tetap ditolak; duplikat anggota ditolak; tambah anggota saat
  order selesai/batal ditolak.
- `php artisan test` hijau (test baru: `tests/Feature/OrderTimTeknisiTest.php`).

## 4. Batas Versi Ini

- Tidak ada UI "lepas anggota tim" (menyusul).
- Ganti PIC via Assign tidak otomatis melepas anggota lama (PIC = penanggung
  jawab; keanggotaan lama tetap tercatat).
- Satu alur status per order (bukan per anggota) — konsisten dengan perilaku
  lama: order yang sama tidak punya status terpisah per teknisi.
