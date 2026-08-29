# Konsep Modul — Human Resource (HRD)

Referensi: [PRD §4.3](../../PRD.md#43-modul-human-resource-hr) · [Skema DB](../database-schema.md)

## 1. Ringkasan & Tujuan

Modul untuk kelola data karyawan, kehadiran umum, evaluasi performa, dan jenjang karir. Prioritas: **paling belakang** dari 4 modul, bukan karena tidak penting, tapi karena tidak menyentuh alur transaksi harian secara langsung — kehadiran teknisi di lapangan sudah tercakup lewat check-in di modul Teknisi (lihat [`00-overview.md`](../00-overview.md)). HRD di Fase 1 cukup diwakili tabel dasar; fitur evaluasi lengkap masuk Fase 2.

## 2. Fitur

1. **Data Karyawan** — biodata, jabatan, tanggal masuk, status.
2. **Kehadiran** — rekap absensi seluruh karyawan (gabungan dari `attendances`, termasuk yang tercatat via check-in Teknisi).
3. **Performa Kerja** — evaluasi berkala, mengacu ke Capaian Kerja dari modul Teknisi + catatan kualitatif.
4. **Kualifikasi/Jenjang Karir** — level karyawan (junior/senior/lead), dasar kenaikan jenjang/insentif.

## 3. Alur Pengguna

**A. Onboarding karyawan baru:**
1. Owner/HR buat akun `users` (role sesuai posisi) → lengkapi data di `employees` (jabatan, tanggal masuk, jenjang awal).

**B. Rekap kehadiran:**
1. HR lihat rekap `attendances` semua karyawan per periode (gabungan absen kantor + absen lapangan teknisi).
2. Tandai anomali (mis. alpha berulang) untuk ditindaklanjuti.

**C. Evaluasi performa (Fase 2):**
1. Periode tertentu (bulanan/kuartalan), HR/Owner isi `performance_reviews` per karyawan — mengacu data objektif (jumlah order selesai dari modul Teknisi) + catatan kualitatif.
2. Hasil evaluasi jadi dasar usulan naik jenjang karir di `employees.jenjang_karir`.

## 4. Halaman/Menu

- Data Karyawan (list, detail, form tambah/edit)
- Rekap Kehadiran (filter per karyawan/periode)
- Evaluasi Performa (Fase 2 — form + riwayat evaluasi per karyawan)
- Jenjang Karir (Fase 2 — riwayat perubahan level per karyawan)

## 5. Tabel Terkait

| Tabel | Peran HRD |
|---|---|
| `employees` | full CRUD |
| `attendances` | read (write terjadi dari modul Teknisi/absen umum) |
| `performance_reviews` | full CRUD (Fase 2) |
| `users` | read (dan create saat onboarding karyawan baru) |

## 6. Checklist Pengembangan

**Fase 1 (MVP — minimal):**
- [ ] CRUD `employees` dasar (biodata, jabatan, tanggal masuk)
- [ ] Halaman rekap kehadiran (read-only, gabungan data `attendances`)

**Fase 2:**
- [ ] Form evaluasi performa (`performance_reviews`) mengacu data capaian kerja Teknisi
- [ ] Riwayat & pengelolaan jenjang karir
- [ ] Absensi mandiri untuk karyawan non-lapangan (mis. admin kantor) — sejauh ini `attendances` didesain generik untuk semua role
- [ ] Notifikasi otomatis untuk anomali kehadiran (mis. alpha berturut-turut)

## 7. Pertanyaan Terbuka

- Apakah karyawan non-teknisi (Admin, Finance) perlu absen harian juga di sistem, atau HRD di Fase 1 fokus untuk data teknisi saja?
- Kriteria kenaikan jenjang karir seperti apa — murni dari jumlah order selesai, atau ada penilaian subjektif (soft skill, kedisiplinan)?
- Siapa yang berwenang mengubah `jenjang_karir` — HR sendiri atau perlu approval Owner?
