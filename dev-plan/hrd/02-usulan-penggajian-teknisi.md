# HRD 02 — Usulan Modul Penggajian Teknisi (Payroll)

> STATUS: **DIEKSEKUSI (menu admin HRD)** 11 Okt 2026. Keputusan final di §9.
> Menu slip di sisi teknisi **belum** dikerjakan (menyusul).
>
> Implementasi: migrasi `technician_payroll_profiles`, `technician_off_days`,
> `payroll_runs` + `payroll_run_items`; model + `PayrollService` (hitung/ACC/bayar,
> bonus umum); grup menu **SDM/HRD** → "Data Gaji Teknisi", "Jadwal OFF",
> "Penggajian"; slip HTML+PDF (`/slip-gaji/{run}`); tes `PenggajianTeknisiTest`.

## 1. Tujuan

Menu SDM/HRD untuk:
1. Menyimpan **info penggajian dasar tiap teknisi** (gaji pokok, potongan BPJS TK,
   potongan OFF/hari, kuota cuti, tanggal gajian/batas periode).
2. **Menghitung gaji** tiap teknisi per periode = Gaji Pokok + Bonus − Potongan.
3. **Rekap semua teknisi** dalam tabel dengan kolom rincian (item) seperti contoh.
4. **Slip gaji** yang baru muncul setelah **di-ACC** HRD/Admin.
5. (Nanti) teknisi bisa melihat slip sendiri.

## 2. Data yang sudah ada (sumber hitungan)

| Data | Bukti | Dipakai untuk |
|---|---|---|
| Ledger insentif | `app/Models/TechnicianIncentive.php`, kategori `IncentiveKategori` (games1..6, denda_telat, lainnya), `tipe` bonus/denda, `status_verifikasi` | Bonus Games 1–6 + denda telat |
| Absen harian | `app/Models/DailyAttendance.php` (`status_datang`: bonus/normal/telat/telat_toleransi, `dikecualikan_denda`) | Jumlah hari telat, dasar denda |
| Pengaturan absensi | `app/Models/AttendanceSetting.php` (`nominal_denda_telat` default **7.500**) | Nominal denda telat/hari |
| Absen per kunjungan | `app/Models/Attendance.php` | (opsional) verifikasi kehadiran |
| Insentif Games | `AttendanceSetting` (games1..6) + `TechnicianIncentive` | Angka bonus |

**Belum ada**: tabel profil gaji, tabel payroll/run gaji, slip, dan **pencatatan hari OFF/cuti**
(tidak ada tabel cuti; `daily_attendances` hanya mencatat hari hadir).

## 3. Kebutuhan dari user (kutipan)

Info penggajian dasar per teknisi (contoh):

| Teknisi | Tanggal gajian / batas absen | Gaji pokok | BPJS TK | Potongan OFF/hari |
|---|---|---|---|---|
| HUSAEN | tgl 16 | 3.050.000 | 129.424 | 100.000 |
| RIZKI | tgl 19 | 2.900.000 | 129.424 | 90.000 |
| BAYU | tgl 23 | 2.750.000 | 129.424 | 80.000 |
| IRSYAD | tgl 15 | 2.600.000 | — | 80.000 |

Aturan perhitungan:
- **Gaji bersih = Gaji Pokok + Bonus − Potongan.**
- **Potongan**: denda keterlambatan (per hari), OFF/libur (kuota **5 hari**; kelebihan
  saja yang dipotong), benefit BPJS TK.
- **Bonus**: Bonus Game 1–7 + **Bonus Umum** (tergantung pimpinan, manual).

Contoh (HUSAEN, Juni–Juli):
```
GAJI POKOK                    3.050.000
BONUS UMUM                      400.000
Bonus Game 5&6 (berdua, 1x)      90.000
Bonus Game 5&6 (bertiga, 1x)     60.000
Bonus Game 5 (1x)                50.000
Bonus Game 5 (bertiga, 1x)       35.000
- Keterlambatan 10 hari x 7.500  75.000
- Ketidak hadiran 1x            100.000
- BPJS TK                       129.424
= GAJI BERSIH                 3.380.576
```

## 4. Rancangan tabel (usulan)

1. **`technician_payroll_profiles`** (info penggajian dasar, 1 baris per teknisi)
   - `user_id` (FK unik), `gaji_pokok`, `potongan_bpjs_tk` (default 129.424),
     `potongan_off_per_hari`, `kuota_off_hari` (default 5), `tanggal_gajian` (1–28),
     `denda_telat_per_hari` (nullable → pakai default settings), `aktif`, `catatan`, timestamps.

2. **`payroll_runs`** (satu hitungan gaji = 1 teknisi + 1 periode)
   - `user_id`, `periode_mulai`, `periode_selesai`, `tanggal_gajian`,
     `gaji_pokok`, `total_bonus`, `total_denda_telat`, `jumlah_hari_telat`,
     `total_potongan_off`, `jumlah_hari_off`, `potongan_bpjs_tk`, `bonus_umum`,
     `gaji_bersih`, `status` (`draft`/`disetujui`/`dibayar`), `catatan`,
     `dihitung_oleh`, `disetujui_oleh`, `disetujui_pada`, `dibayar_pada`, timestamps.
   - Unik: (`user_id`, `periode_mulai`, `periode_selesai`).

3. **`payroll_run_items`** (rincian baris agar tampil seperti contoh)
   - `payroll_run_id`, `jenis` (bonus/potongan), `kategori` (games/telat/off/bpjs/bonus_umum/lainnya),
     `label`, `nominal` (negatif utk potongan atau pakai `jenis`), `referensi`, `urutan`.

4. **(opsional) `technician_leaves` / `off_days`** — bila hari OFF dicatat manual per periode.

> Slip **tidak** disimpan sebagai file default; dirender dari `payroll_runs` + items
> (opsi PDF saat dibutuhkan). Muncul ke teknisi hanya bila `status` ≥ `disetujui`.

## 5. Alur & perhitungan

1. HRD/Admin pilih teknisi + periode → **Hitung** → sistem tarik:
   - bonus = Σ `technician_incentives` (tipe bonus, `status_verifikasi` disetujui) di periode;
   - denda telat = Σ `denda_telat` (tipe denda, disetujui) → hari x nominal, atau dari jumlah `daily_attendances` telat;
   - potongan off = maks(0, jumlah_off − kuota_off) x potongan_off_per_hari;
   - BPJS TK dari profil.
   → disimpan sebagai `payroll_runs` (status `draft`) + `payroll_run_items`.
2. HRD bisa **sesuaikan** (bonus umum, koreksi item) selama `draft`.
3. **ACC** (Owner/HR/Admin) → status `disetujui` → **slip muncul** (tampilan + PDF).
4. Tandai **dibayar** → status `dibayar` (jejak tanggal).
5. (Nanti) teknisi melihat slip di portalnya bila `disetujui`/`dibayar`.

## 6. Menu admin HRD (usulan grup navigasi "SDM/HRD")

- **Data Gaji Teknisi** (`technician_payroll_profiles`) — CRUD profil penggajian.
- **Penggajian** (`payroll_runs`) — daftar per teknisi/periode (tabel item rinci +
  kolom Total), aksi: Hitung, Sesuaikan, ACC, Tandai Dibayar, Unduh Slip (PDF).
- **Rekap Gaji** — tabel semua teknisi per periode (kolom item seperti contoh) + ekspor.

Akses: **Owner + HR + Admin**. Karyawan = **teknisi saja** (keputusan user).

## 7. Berkas terdampak (rencana)

Baru: migrasi 3–4 tabel; model `TechnicianPayrollProfile`, `PayrollRun`, `PayrollRunItem`;
service `PayrollService` (hitung, ACC, bayar); resource `TechnicianPayrollProfileResource`,
`PayrollRunResource` + pages; view slip (+ PDF); grup navigasi "SDM/HRD"; tes.

## 8. Keputusan yang diminta

1. **Hari OFF dihitung bagaimana?** (a) HRD input manual jumlah off per periode;
   (b) tabel `off_days` per tanggal; (c) turunkan dari hari tanpa absen. — usul: **(a) manual**.
2. **Nominal denda telat/hari**: pakai global `attendance_settings.nominal_denda_telat`
   (7.500) atau **per teknisi**? — usul: **per teknisi** (kolom di profil) dengan fallback global.
3. **Bonus Game 7**: tambah kategori baru di `IncentiveKategori` (mis. `games7_...`)?
   — perlu nama & aturannya.
4. **Bonus Umum**: input manual per periode per teknisi (nominal + catatan)? — usul: **ya**.
5. **Definisi periode**: mengikuti `tanggal_gajian` tiap teknisi (mis. 16 Jun–15 Jul), atau
   HR pilih rentang bebas? — usul: **otomatis dari tanggal_gajian**, tetap bisa dikoreksi.
6. **Kuota OFF**: seragam 5 hari, atau per teknisi (kolom `kuota_off_hari`)? — usul: **per teknisi** (default 5).
7. **Slip**: cukup halaman (HTML) atau perlu **PDF** + nomor slip? — usul: HTML + unduh PDF.
8. **Status gaji**: `draft → disetujui → dibayar` (slip muncul saat disetujui)? — usul: **ya**.
9. Apakah **denda telat** dari ledger `technician_incentives` (sudah disetujui) atau dihitung
   ulang dari `daily_attendances` status `telat`? — usul: **dari ledger** (agar konsisten
   dengan pengecualian denda yang sudah ada).

---

## 9. Keputusan dikonfirmasi (11 Okt 2026) & revisi

1. **Hari OFF = admin mengatur jadwal off** tiap teknisi (jatah **5 hari/bulan**, seragam).
   → butuh tabel `technician_off_days` (user_id, tanggal, catatan, dibuat_oleh).
2. **Denda telat: per teknisi** — nominal/hari disimpan di profil (default 7.500);
   dihitung dari **absensi**: hari berstatus `telat` → potong nominal tsb/hari.
3. **Bonus Game 7: input manual admin** dulu (belum otomatis).
4. **Bonus Umum**: nominal ditentukan **Owner**, yang **input admin**.
5. **Periode gaji mengikuti `tanggal_masuk`** teknisi (day-of-month): mis. masuk tgl 15 →
   periode **15 Okt – 14 Nov** (satu bulan penuh). → profil simpan `tanggal_masuk`.
6. **Kuota OFF seragam 5 hari.**
7. **Slip: HTML + unduh PDF.**
8. **Denda telat diambil dari absensi** (`daily_attendances` status `telat`), bukan dari ledger.
9. **Games 1,2,3 dicatat & dibayar BULANAN; Games 4,5,6 dicatat & dibayar MINGGUAN** —
   Games 4,5,6 tetap tampil di slip **tetapi menjadi pengurang** dari total gaji (karena
   sudah dibayar tiap pekan).

### Revisi perhitungan (usulan)

```
Gaji Pokok
+ Bonus bulanan      (Games 1,2,3 + Bonus Umum [+ Game 7 manual])
+ Bonus mingguan      (Games 4,5,6)                     [ditampilkan]
− Sudah dibayar mingguan (Games 4,5,6)                  [pengurang]
− Denda telat         (jumlah hari telat × nominal/teknisi)
− Potongan OFF        (maks(0, jumlah_off − 5) × potongan_off/hari)
− BPJS TK
= Gaji Bersih
```

### Pertanyaan lanjutan

- **a.** Games 4,5,6: benar bersifat **net-nol** (masuk lalu dikurangkan) sehingga **tidak**
  menambah gaji bulanan? (Contoh lama HUSAEN menambahkan Games 5&6 — perlu dipastikan mana
  yang berlaku sekarang.)
- **b.** **Game 7**: nama kategori & aturannya? (usul: kategori baru `games7_*`, sumber manual,
  admin input nominal + catatan).
- **c.** **Jadwal OFF**: diinput lewat kalender per tanggal, atau tabel daftar tanggal per
  teknisi? (usul: tabel daftar tanggal per teknisi + validasi kuota).

---

## 10. Tambahan: Pulang Cepat (setengah hari) — DIEKSEKUSI

Permintaan user: teknisi bisa input **pulang cepat** pada tanggal tertentu, dan HRD punya
menu baru untuk data ini; penggajian mendeteksinya sebagai **setengah hari**.

- **Tabel** `technician_early_leaves` (user_id, tanggal, jam_pulang, alasan, status, catatan_hrd,
  diverifikasi_oleh, diverifikasi_pada; unik user+tanggal).
- **Teknisi**: halaman **"Pulang Cepat"** di portal teknisi (`/teknisi/pulang-cepat`) — isi
  tanggal + jam pulang + alasan; lihat riwayat & status.
- **HRD**: menu **SDM/HRD → Pulang Cepat** untuk verifikasi (Setujui/Tolak).
- **Penggajian**: tiap pulang cepat **disetujui** di periode dihitung **setengah hari** →
  potongan `0,5 × potongan_off_per_hari` per kejadian (kolom `jumlah_setengah_hari` &
  `total_potongan_setengah_hari` pada `payroll_runs`, muncul sebagai baris "Pulang cepat /
  setengah hari" di rincian & slip).
- Keputusan: butuh **ACC HRD**; hanya yang disetujui memengaruhi gaji; jam/tanggal diisi
  manual teknisi (tanggal tidak boleh di masa depan).
