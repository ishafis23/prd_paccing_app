# Usulan — Import Pengguna dari Excel (menu di resource Pengguna)

> STATUS: **DISETUJUI (8 September 2026)** — B36a, B36b, B36c, B36d, B36e.
> Disetujui via asumsi (klarifikasi timeout); tercatat di
> `02-keputusan-eksekusi.md`; dokumen ini acuan implementasi.
> Tambahan atas resource Pengguna (B20). Paket `phpoffice/phpspreadsheet`
> sudah terpasang (composer) untuk membaca/menulis .xlsx.

## 1. Konteks

Arahan owner (8 Sep): di halaman Pengguna (/admin → Manajemen → Pengguna)
ingin ada menu **Import Excel** — membuat banyak akun sekaligus dari file,
mis. saat onboarding karyawan/teknisi baru tanpa ketik satu-satu.

Saat ini akun hanya dibuat manual via tombol New (B20: Admin/Owner, hanya
Owner yang bisa membuat akun Owner; password min 8; email unik).

## 2. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B36a — Format & batas file
- **(a) Upload `.xlsx` ATAU `.csv` (UTF-8)** — dibaca via PhpSpreadsheet;
  maksimal **200 baris data** & **2 MB** per file (guard; pesan jelas bila
  lebih). Ada tombol **"Unduh Template"** (.xlsx berisi header + 2 contoh
  baris + sheet Petunjuk).
- (b) Hanya .csv (tanpa library) — tidak direkomendasikan, Excel adalah
  format utama kantor.

Rekomendasi: **(a)**.

### B36b — Kolom template
Header: **nama\* | email\* | no_hp | role\* | password | status**
- `role` = salah satu: `owner`, `admin`, `finance`, `hr`, `teknisi`
  (tulis kecil; template menyertakan daftar);
- `password` **opsional** — bila kosong, akun memakai **password default
  `paccing123`** (bisa diganti lewat kolom ini per baris, atau kolomnya diisi
  semua). Ada catatan di template: segera ganti via Reset Password;
- `status` opsional: `aktif` (default) / `nonaktif`.

Rekomendasi: **(a) sebagaimana di atas** — satu file cukup untuk seluruh role.

### B36c — Duplikat & baris bermasalah (IDEMPOTEN — tidak mengubah akun lama)
- Email sudah ada di sistem / duplikat dalam satu file → baris **dilewati**
  (tidak dibuat, tidak di-update);
- Baris tidak valid (nama/email/role kosong, role salah, password < 8,
  email tidak valid) → baris **dilewati**, dicatat alasannya;
- Baris ber-role `owner` ketika yang import **Admin** → dilewati dengan
  alasan "hanya Owner" (aturan B20 tetap berlaku); Owner boleh import owner.

Rekomendasi: **(a)** — file tetap diproses sebagian; tidak ada rollback total.

### B36d — Laporan hasil
Setelah import muncul notifikasi: **"X akun dibuat, Y dilewati, Z gagal"**
+ rincian baris bermasalah (nomor baris & alasan, maks. 15 baris pertama).

Rekomendasi: **(a)**.

### B36e — Akses & letak tombol
Tombol **"Import Excel"** (modal: pilih file + isian "password default bila
kosong") dan **"Unduh Template"** di header halaman Pengguna. Hanya
Admin/Owner (sama dengan hak buat akun B20); import memakai
`UserService::createUser` per baris sehingga semua aturan B20 (owner/self,
password, email unik) otomatis berlaku.

Rekomendasi: **(a)**.

## 3. Desain Teknis (ringkas)

### Komponen baru
1. `app/Services/UserImportService.php`
   - `template(): Spreadsheet` — sheet "pengguna" (header + contoh) & sheet
     "petunjuk" (daftar role, aturan password/duplikat);
   - `unduhTemplate()` — stream download .xlsx;
   - `import(string $pathFile, User $by, ?string $passwordDefault): array`
     — baca baris; header wajib mengandung nama/email/role; tiap baris valid
     → `UserService::createUser` (catch BusinessRuleException/
     AuthorizationException); kumpulkan hitungan + detail error
     (baris + alasan). File dibaca dari disk sementara lalu dihapus.
2. Aksi header di `UserResource/Pages/ListUsers.php`:
   - `Import Excel` (modal: FileUpload .xlsx/.csv + password default);
     hasil → Notification ringkas + rincian bila ada yang gagal;
   - `Unduh Template` (langsung unduh .xlsx).
3. Tidak ada migrasi/tabel baru. `.gitignore`/tmp: file import dihapus
   setelah diproses.

### Test yang direncanakan (Pest)
- Template & header benar (nama/email/no_hp/role/password/status).
- Import valid xlsx → akun dibuat (role benar, password default bila kosong,
  status default aktif); via `Storage::fake`/file sementara.
- Baris email duplikat DB dilewati; duplikat dalam file dilewati; akun lama
  tidak berubah.
- Baris role owner saat importir Admin → dilewati + alasan; importir Owner OK.
- Baris password < 8 / role salah / email invalid → dilewati + alasan.
- > 200 baris ditolak; header salah ditolak (BusinessRuleException).
- Importir bukan Admin/Owner → AuthorizationException.
- UI: tombol import tampil utk Admin/Owner; alur aksi menciptakan akun.
