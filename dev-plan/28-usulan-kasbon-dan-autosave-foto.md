# 28 — Usulan: Menu Kasbon (nyambung gaji) + Auto-save Foto Laporan Teknisi

> STATUS: **DITULIS SEBELUM EKSEKUSI** (19 Okt 2026) — history permintaan client.
> Sumber: chat client, 19 Okt 2026.

## 1. Permintaan client (kutipan)

> "cek sistem penggajian, boleh ditambahkan menu **Kasbon** yang nantinya terkoneksi ke perhitungan gaji."
>
> "kondisikan ini bisakah sistemnya **auto save setelah upload foto di web**, jadi walaupun ter-refresh
> atau tiba-tiba keluar laman web, bisa menetap foto yg diupload. Soalnya ini teknisi sudah foto sampai
> 10 unit, tapi hilang karena ter-refresh."

Dua poin: **(A) Kasbon** dan **(B) Foto tidak boleh hilang saat refresh/keluar halaman**.

---

## 2. Poin A — Menu Kasbon

### 2.1 Kondisi sekarang
- `PayrollService::proses()` menghitung gaji per teknisi per periode (gaji pokok + bonus − denda telat −
  potongan OFF − potongan pulang cepat − BPJS TK). Rincian disimpan di `payroll_run_items`
  (`jenis` = bonus/potongan) dan tampil di slip (`payroll/_rincian.blade.php`).
- **Belum ada** pencatatan kasbon/pinjaman sama sekali.

### 2.2 Usulan
Menu baru di grup **SDM/HRD → "Kasbon"** (Owner/HR/Admin).

**Data kasbon** (tabel `technician_advances`):
| Kolom | Isi |
|---|---|
| `user_id` | teknisi |
| `tanggal` | tanggal kasbon diberikan |
| `nominal` | jumlah kasbon |
| `keterangan` | alasan / catatan |
| `cicilan_per_periode` | (opsional) potongan per periode gaji; kosong = potong lunas sekaligus |
| `dicatat_oleh` | admin yang input |
| `status` | `aktif` / `lunas` |

**Pelunasan** (tabel `technician_advance_repayments`): setiap potongan gaji tercatat (kasbon, run gaji,
nominal, tanggal) → sisa kasbon = nominal − total pelunasan. Kasbon otomatis `lunas` saat sisa 0.

**Koneksi ke gaji** (`PayrollService`):
- Saat gaji dihitung (draft), kasbon **aktif** teknisi dengan `tanggal` ≤ akhir periode ikut dipotong:
  `min(sisa, cicilan_per_periode)` (atau seluruh sisa bila cicilan kosong).
- Muncul sebagai baris potongan **"Potongan Kasbon"** di rincian & slip (kolom Potongan).
- `gaji_bersih` dikurangi total potongan kasbon (kolom baru `payroll_runs.total_potongan_kasbon`).
- Pelunasan **baru dicatat permanen saat gaji di-ACC** (draft boleh dihitung ulang tanpa menggandakan);
  kalau draft dihitung ulang, potongan kasbon dihitung ulang dari sisa yang sebenarnya.
- Gaji yang sudah ACC/dibayar tidak berubah (konsisten dengan aturan yang ada).
- Draft otomatis tersegarkan saat kasbon ditambah/diubah (pola `segarkanDraft` yang sudah ada).

**Batasan aman**: potongan kasbon tidak membuat gaji bersih negatif (dibatasi sampai gaji bersih sebelum
kasbon); sisanya terbawa ke periode berikutnya.

**UI Admin**: resource Filament "Kasbon" — daftar (teknisi, tanggal, nominal, terpotong, sisa, status),
form tambah/ubah, filter teknisi & status.

### 2.3 Di luar cakupan (bisa menyusul)
- Pengajuan kasbon dari portal teknisi (saat ini admin yang input).
- Tampilan kasbon di portal teknisi.

---

## 3. Poin B — Auto-save foto laporan teknisi

### 3.1 Akar masalah (hasil pengecekan kode)
- Upload foto di detail order (`public/js/camera-upload.js`, komponen `photoUpload`) mengunggah lewat
  **Livewire** (`$wire.upload`). File hanya disimpan sebagai *temporary upload Livewire* yang
  tertaut ke state komponen halaman — **begitu halaman di-refresh/ditutup, state hilang dan foto ikut hilang**.
- Endpoint penyimpanan permanen sementara (`teknisi.temp-photo.*` + tabel `temporary_photo_uploads`)
  sebenarnya sudah ada, **tapi hanya dipakai sebagai cadangan bila upload Livewire gagal**, dan fungsi
  `restoreTemporaryPhotos()` **tidak pernah dipanggil** → preview foto tidak dipulihkan walaupun tersimpan.
- Server (`OrderDetail::submitLaporan`, `simpanFotoUnitTerunggah`, `lengkapiFotoWajib`) sudah bisa
  membaca foto dari tabel temp — jadi yang kurang hanya sisi browser + sedikit perapian server.

### 3.2 Usulan perbaikan
1. **Auto-save**: setiap foto yang dipilih (kamera/galeri), setelah dikompres, **langsung disimpan juga ke
   server** (draft permanen per teknisi + order + slot) — tidak menunggu tombol Simpan/Kirim Laporan.
2. **Pulihkan otomatis**: saat halaman dibuka lagi (refresh/keluar-masuk), tiap slot foto menampilkan
   kembali foto draft yang sudah tersimpan (preview dari server) + label "tersimpan otomatis".
3. **Satu slot satu foto**: mengunggah ulang pada slot yang sama menggantikan draft lama (tidak
   menumpuk file).
4. **Hitungan "foto wajib lengkap" ikut menghitung draft** supaya indikator slot/progres tidak kembali
   ke 0 setelah refresh.
5. **Perbaikan kecil**: cek kepemilikan pada hapus draft (`TemporaryPhotoUploadController::destroy`
   salah logika — selalu lolos), dan tombol hapus foto ikut menghapus draft di server.
6. Draft dibersihkan otomatis setelah laporan/foto berhasil disimpan (perilaku yang sudah ada).

Slot yang dicakup: `fotoSebelum`, `fotoSesudah`, `fotoKategori.*`, `fotoUnit.*` (foto per unit — kasus
10 unit), `fotoLengkapi.*`.

### 3.3 Catatan
- Foto yang **sudah hilang** sebelum perbaikan ini tidak bisa dipulihkan (tidak pernah tersimpan di server).
- Draft menempati storage sampai laporan dikirim; kuota storage tetap dicek saat penyimpanan final.

---

## 4. Rencana eksekusi & tes
1. Migrasi kasbon + kolom `total_potongan_kasbon`; model, enum status, `KasbonService`; sambungkan ke
   `PayrollService` + slip; resource Filament. Tes: potongan masuk gaji, cicilan, sisa terbawa, ACC mencatat
   pelunasan, hitung ulang draft tidak menggandakan.
2. Auto-save foto: ubah `camera-upload.js`, `TemporaryPhotoUploadController` (replace per slot, URL preview,
   perbaikan otorisasi), hitungan foto lengkap di `OrderDetail`. Tes: store mengganti slot, index memberi URL,
   destroy menolak milik orang lain.

## 5. Laporan eksekusi (19 Okt 2026)

**Status: DIEKSEKUSI.** Jalankan `php artisan migrate` di server, lalu build/refresh cache aset
(`public/js/camera-upload.js` berubah; versi query otomatis via `filemtime`).

### Kasbon
- Migrasi `2026_10_19_000005_create_technician_advances_tables` (`technician_advances`,
  `technician_advance_repayments`, kolom `payroll_runs.total_potongan_kasbon`).
- Model `TechnicianAdvance` / `TechnicianAdvanceRepayment`, enum `AdvanceStatus`.
- Menu **SDM/HRD → Kasbon** (`TechnicianAdvanceResource`): tambah/ubah/hapus (selama belum ada
  potongan), kolom Terpotong, Sisa, Status; filter teknisi & status.
- `PayrollService`: potongan kasbon dihitung terakhir (setelah denda/OFF/BPJS), dibatasi agar gaji tidak
  negatif, cicilan per periode didukung; item "Potongan Kasbon" di rincian/slip/tabel penggajian.
  Pelunasan dicatat permanen saat **ACC** (ACC ditolak bila kasbon berubah sejak dihitung → hitung ulang
  dulu); kasbon otomatis **lunas** saat sisa 0. Draft otomatis tersegarkan saat kasbon berubah.
- Tes: `KasbonTeknisiTest` (8).

### Auto-save foto
- `camera-upload.js`: tiap foto laporan (`fotoSebelum/Sesudah`, `fotoKategori.*`, `fotoUnit.*`,
  `fotoLengkapi.*`) langsung disimpan ke server sebagai draft; saat halaman dibuka ulang, preview
  dipulihkan otomatis dari server (`init()` + `muatDraftFoto`). Tombol Simpan/Kirim menunggu draft
  selesai tersimpan.
- `TemporaryPhotoUploadController`: unggahan baru menggantikan draft slot yang sama, `url` preview
  dikembalikan, draft yang filenya hilang tidak dipulihkan, perbaikan otorisasi `destroy`.
- `OrderDetail`: indikator "foto wajib lengkap / jumlah terunggah" ikut menghitung draft.
- Alur simpan final tidak diubah (sudah membaca draft server saat submit, lalu membersihkannya).
- Tes: `AutosaveFotoTeknisiTest` (4).

### Catatan
- Pemulihan di browser (preview muncul lagi setelah refresh) belum diuji manual di HP — mohon dicoba
  satu order dengan beberapa unit: pilih foto → refresh → foto harus tetap tampil → Kirim Laporan.
- Foto yang sudah terlanjur hilang sebelum perbaikan ini tidak dapat dipulihkan.
- Draft order yang tidak pernah dikirim tetap di storage; pembersihan berkala bisa ditambahkan bila perlu.
