# Usulan — Manajemen Info Usaha (nama, alamat, kontak, owner, logo)

> STATUS: **DISETUJUI (8 September 2026)** — B37a, B37b, B37c, B37d, B37e,
> B37f. Tercatat di `02-keputusan-eksekusi.md`; dokumen ini acuan
> implementasi.

## 1. Konteks

Arahan owner (8 Sep): perlu halaman untuk mengelola **info usaha** — edit
**nama usaha, alamat, kontak, owner (nama pemilik)**, dan **upload logo**.
Logo & nama itu yang dipakai di **header sidebar admin, head title (tab
browser), dan halaman login** — plus (konsisten) portal teknisi, landing page,
dan resi.

Kondisi sekarang: identitas tersebar & sebagian hardcoded:
- Admin panel: brand "Paccing Official" (string di AdminPanelProvider) — belum
  bisa diubah dari UI; head title ikut brand tsb; login admin tampil brand.
- Teknisi: header kecil bertuliskan "Paccing CRM" (hardcoded); login teknisi
  memakai teks/ikon statis.
- Landing `/`: nama, logo `public/assets/logo.png`, alamat & kontak WA masih
  placeholder tertulis di view.
- Resi publik: kop memakai logo statis & tulisan "Paccing".

## 2. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B37a — Penyimpanan data
- **(a) Tabel `business_infos` (baris tunggal id=1)** + `BusinessInfoService`
  (baca pakai cache pendek; tulis via service) + seeder data awal = nilai
  sekarang (nama "Paccing Official", alamat/kontak sesuai yg ada di landing,
  logo awal kosong → fallback aset lama). Bisa diedit dari UI, tanpa deploy.
- (b) Simpan di config/.env — tidak bisa diedit owner dari UI. Tidak sesuai.

Rekomendasi: **(a)**.

### B37b — Cakupan tampilan yang memakai data ini
- **(a) Semua permukaan konsisten**: admin (brand sidebar + head title +
  login), portal teknisi (login + header), landing page, & resi publik —
  satu sumber data, sekali ubah semua ikut.
- (b) Hanya area admin (sidebar/head/login) sesuai permintaan; landing &
  teknisi tetap statis (bisa menyusul).

Rekomendasi: **(a)** — sekalian membereskan placeholder alamat/WA di landing.

### B37c — Isian (field)
**Nama Usaha***, Alamat, No. HP/WA (kontak), Email (opsional), Nama Pemilik
(owner), **Logo** (PNG/JPG/SVG, maks 2 MB, opsional). Bila logo kosong →
tampil fallback: logo aset lama (untuk teknisi/landing/resi) / teks nama saja
(untuk sidebar admin).

Rekomendasi: **(a)** sebagaimana daftar di atas.

### B37d — Letak & akses pengelolaan
Menu **Manajemen → "Info Usaha"**: halaman edit tunggal (bukan daftar):
form isi + pratinjau logo + info "terakhir diubah oleh … pada …". Yang boleh
mengubah: **Owner & Admin**. Finance/HR tidak melihat menu ini (info tetap
tampil di permukaan publik apa adanya).

Rekomendasi: **(a)**.

### B37e — Perlakuan file logo
Disimpan di storage public folder `business/`. Upload divalidasi (file gambar,
≤ 2 MB) dan dicek ke kuota penyimpanan (`StorageQuotaService::pastikanCukup`,
selaras B25). Logo lama dihapus saat diganti; tombol "hapus logo" mengosongkan
logo (kembali ke fallback). File logo TIDAK ikut pembersihan otomatis foto tua
(B23 — di luar folder work-reports).

Rekomendasi: **(a)**.

### B37f — Sinkron tampilan
- `AdminPanelProvider`: brandName & logo panel dibaca dari service (closure),
  sehingga sidebar, login, dan `<title>` ikut nama usaha & logo baru.
- `layouts/teknisi`, login teknisi, `landing`, `resi`: nama/logo/kontak/alamat
  dibaca dari service (fallback ke nilai lama bila belum diisi).

Rekomendasi: **(a)**.

## 3. Desain Teknis (ringkas)

### Komponen baru
1. Migrasi `business_infos`: id (baris tunggal), nama_usaha, alamat (text,
   nullable), kontak_wa, email, nama_pemilik, logo_path, diubah_oleh (FK users,
   nullable), timestamps.
2. `app/Models/BusinessInfo.php` + factory; relasi `pengubah()`.
3. `app/Services/BusinessInfoService.php`
   - `data(): BusinessInfo` — cache pendek (mis. 300 dtk), fallback buat baris
     id=1 bila belum ada (dgn nilai default);
   - `namaUsaha()`, `logoUrl()`, `kontakWa()`, dst — akses aman;
   - `perbarui(array $data, ?UploadedFile $logo, bool $hapusLogo, User $by)`
     — validasi, simpan/update file, hapus file lama, reset cache;
   - `lupakanCache()`.
4. `app/Filament/Pages/KelolaInfoUsaha.php` (grup Manajemen, label "Info
   Usaha", ikon bangunan) + view blade: form (nama, alamat, kontak, email,
   owner), upload logo (Livewire `WithFileUploads` + preview), toggle hapus
   logo, tombol simpan, info terakhir diubah; `canAccess` = Owner/Admin.
5. Seeder `BusinessInfoSeeder` (data awal) — dipanggil di DatabaseSeeder.
6. Pembaruan tampilan (B37f) memakai service langsung di blade/panel.

### Tidak berubah
- Skema order/payment/resource lain; kebijakan pembersihan file (B23–B26);
  kuota tetap menghitung seluruh disk public (logo ikut terhitung wajar).

### Definisi "Selesai"
1. Admin: Manajemen → "Info Usaha" bisa mengubah nama/alamat/kontak/owner &
   upload/hapus logo (Owner/Admin); tersimpan permanen.
2. Sidebar admin, login admin, dan head title ikut nama usaha & logo terbaru.
3. Teknisi (login + header), landing, resi memakai data yang sama; bila alamat/
   kontak diisi, landing/resi menampilkannya (placeholder terganti).
4. Logo aman: validasi + kuota; hapus logo → fallback; logo tidak ikut
   pembersihan otomatis.
5. Test Pest hijau (service, halaman, sinkronisasi brand/title) + laporan.

### Test yang direncanakan
- Service: data default bila kosong; perbarui semua field; ganti logo (file
  baru tersimpan, file lama terhapus); hapus logo; kuota penuh menolak upload;
  cache di-reset.
- Halaman: canAccess Owner/Admin; 403 selain itu; simpan via Livewire mengubah
  DB; preview logo tampil.
- Sinkron: brandName panel & <title> halaman admin memakai nama usaha dari DB
  (HTTP ke /admin/login); view teknisi/landing/resi menampilkan nilai DB.
