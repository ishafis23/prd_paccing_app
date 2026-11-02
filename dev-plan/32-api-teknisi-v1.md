# 32 — Kontrak API Teknisi v1

Dokumen pegangan app mobile (`crm_paccing_mobile`). Rencana & alasan: `dev-plan/mobile-app/`. Kode: `routes/api.php`, `app/Http/Controllers/Api/V1/*`, bentuk JSON di `app/Support/Api/Presenter.php`. Tes: `tests/Feature/Api/*`.

## 1. Konvensi

- **Base URL:** `{APP_URL}/api/v1`. Auth: `Authorization: Bearer <token>`. Header app: `X-App-Version: 1.0.0`, `X-Platform: android`, `Accept: application/json`.
- **Versi:** versi di bawah `API_MIN_APP_VERSION` (config `api.min_app_version`) → `426` + `code: upgrade_required` (diperiksa sebelum auth).
- **Sukses:** `{ "data": ... }`; daftar berpaginasi: `{ "data": [...], "meta": { "page", "per_page", "total" } }`.
- **Uang:** integer rupiah. **Tanggal:** `Y-m-d`. **Waktu:** ISO 8601. **Jam:** `HH:MM`.
- **Error:** `{ "message": "<siap tampil>", "code": "...", "errors": {field:[...]}? }`

| HTTP | `code` | Arti |
|---|---|---|
| 401 | `tidak_terautentikasi`, `akun_nonaktif` | token tidak valid/kedaluwarsa → ke layar login |
| 403 | `dilarang`, `bukan_teknisi`, `akun_nonaktif` (saat login) | bukan haknya |
| 404 | `tidak_ditemukan` | tidak ada **atau bukan milik teknisi ini** |
| 422 | `validasi` (+`errors`) / `aturan_bisnis` / `foto_titik_pertama_wajib` | `message` siap ditampilkan apa adanya |
| 426 | `upgrade_required` | perbarui app |
| 429 | — | terlalu banyak request |

- **Throttle:** login 5/menit per email+IP; aksi tulis 120/menit; lainnya 240/menit per user.
- **Token:** Sanctum, berlaku `API_TOKEN_HARI` (default 90). Admin mencabut dengan menghapus baris `personal_access_tokens` user.
- **Hak akses data:** order = tim (PIC **atau** anggota); pendapatan = hanya PIC; pengeluaran = pencatat (ubah/hapus) + partner (lihat).
- **Aksi tulis order** ditolak 422 selama ada setoran tunai telat (sama dengan gerbang web). Membaca tetap boleh.

## 2. Sesi & akun

| Endpoint | Body / Query | Respons `data` |
|---|---|---|
| `POST /login` (publik) | `email`, `password`, `device_name` | `{token, user{id,name,email,phone,foto_url}}` — hanya role teknisi & aktif |
| `POST /logout` | `device_token?` | `{ok}` — cabut token ini (+token push bila dikirim) |
| `GET /me` | | `{user, usaha{nama,logo_url}, min_app_version, waktu_server, fitur}` |
| `POST /akun/foto-profil` | multipart `foto` | `user` |
| `POST /perangkat` | `token`, `platform?`, `app_version?` | `{id}` (201) — token push |
| `DELETE /perangkat/{token}` | | `{ok}` |
| `GET /administrasi` | | `{penghalang[{jenis,order_id,judul,pesan,aksi{layar,order_id?}}], tertahan, tertahan_setoran, pesan}` |

`aksi.layar` ∈ `setoran` · `pengeluaran` · `order`. Panggil `/administrasi` saat membuka Jadwal, setelah tiap aksi tulis, dan saat app kembali ke foreground.

## 3. Absensi, cuci motor, pulang cepat

| Endpoint | Catatan |
|---|---|
| `GET /absensi/hari-ini` | `{state: belum_absen\|siap_pulang\|selesai, mode_absensi: qr\|lokasi, absen, batas{games1,normal_selesai,games2,games4}, lokasi_kantor[{id,nama,latitude,longitude,radius_meter}], waktu_server}` |
| `POST /absensi/datang` | multipart `foto`; mode QR: `kode` (mentah dari QR); mode lokasi: `lat`,`lng`. Server menilai telat/bonus |
| `POST /absensi/pulang` | multipart `foto` |
| `GET /absensi/riwayat?bulan=YYYY-MM&page=` | paginasi 15 |
| `GET /cuci-motor/rekan` | teknisi lain `[{id,name}]` |
| `POST /cuci-motor` | multipart `foto`, `rekan_id?` → `{id,tanggal,peserta[]}` |
| `GET /pulang-cepat` | riwayat, termasuk `nominal_potongan` bila ditetapkan admin |
| `POST /pulang-cepat` | `tanggal`, `jam_pulang`, `alasan?` — tanggal tidak boleh masa depan |

Absen tidak diantre offline; waktu server adalah acuan.

## 4. Jadwal & order

| Endpoint | Catatan |
|---|---|
| `GET /jadwal?tanggal=` | `{sudah_absen, tertahan_setoran, penghalang[], sebelumnya[order], jadwal[order]}` — `order` = ringkas: `id,status,tanggal_jadwal,jam_jadwal,titik{},customer{nama,no_hp},alamat,maps_url,layanan,jumlah_unit,total,is_klaim,ditutup` |
| `GET /riwayat?q=&tanggal=&bulan=&jenis_layanan=&page=` | order selesai/butuh follow-up; paginasi 10; + `opsi_layanan` |
| `GET /lengkapi-laporan` | order yang foto/keterangan unitnya kurang; `meta.jumlah` untuk lencana |
| `GET /order/{id}` | detail lengkap (lihat §4.1) |
| `POST /order/{id}/berangkat` | 422 + pesan penghalang bila tertahan |
| `POST /order/{id}/lokasi` | `lat`,`lng` → `{tercatat}` (gagal diam-diam) |
| `POST /order/{id}/check-in` | multipart `foto_titik_pertama` **wajib bila ini check-in pertama hari itu** (`detail.butuh_foto_titik_pertama`), kalau tidak 422 `foto_titik_pertama_wajib` |
| `POST /order/{id}/kendala` | `alasan` |
| `POST /order/{id}/perbaikan` | `catatan`, `estimasi_harga?` |
| `POST /order/{id}/tutup` | 422 bila belum selesai / metode belum dipilih / bukti belum ada (rumahan) |
| `GET /layanan/opsi` | kategori + `komponen_default` |
| `POST /order/{id}/layanan` | `nama_layanan`,`kategori`,`harga`,`jumlah`,`komponen?`,`catatan?` |
| `DELETE /order/{id}/layanan/{item}` | hanya baris tanpa foto |
| `POST /order/{id}/item/{item}/batal` · `DELETE …/batal` | batalkan / aktifkan lagi |
| `POST /order/{id}/pembayaran/metode` | `metode` ∈ cash,transfer,qris,ewallet (null = hapus tanda) |
| `POST /order/{id}/pembayaran/bukti` | multipart `foto` |
| `POST /order/{id}/pembayaran/lapor-bayar` | `sudah` bool |

Semua aksi di atas (kecuali `lokasi`) mengembalikan **detail order terbaru** (`data` = §4.1) agar app cukup menimpa state.

### 4.1 Detail order

Ringkas + `catatan_admin, alasan_kendala, perbaikan{menunggu_konfirmasi,catatan,estimasi_harga}, tim[{id,name,pic}], saya_pic, butuh_foto_titik_pertama, items[], units[], laporan{ada,catatan,dikirim_pada}, foto_wajib_kurang[], pembayaran{}`.

- `items[]`: `id,nama_layanan,kategori,komponen,harga,jumlah,catatan,dibatalkan,penyesuaian,punya_foto,foto_per_unit,slot_foto[{kode,label,wajib,foto{id,url}|null}]` (`slot_foto[].foto` null bila `foto_per_unit` — foto ada di `units[].foto`).
- `units[]`: `id,order_item_id,unit_no,nama_layanan,lokasi_label,posisi,jenis_pekerjaan,bagian,suhu,rpm,kondisi,catatan_kondisi,field_set(indoor_lengkap|outdoor|bebas),butuh_keterangan,suhu_wajib,lengkap,foto[]` — hanya muncul saat status dikerjakan/selesai/butuh_followup.
- `foto_wajib_kurang[]`: `{jenis: foto|keterangan, label, order_item_id, kode_slot, unit_id?, unit_no?}`.
- `pembayaran`: `metode_dipilih, metode_tunai, bayar_dilaporkan, bukti_url, status_bayar, setoran_aktif, saluran[] (rekening/QRIS), metode_opsi[]`.

## 5. Laporan pengerjaan & foto

Alur: isi keterangan tiap unit → unggah foto **satu per request** → finalisasi. Foto sebelum laporan **ditampung** per slot (unggah ulang slot yang sama menggantikan = idempoten), dipakai saat finalisasi. Setelah laporan ada, foto langsung tersimpan.

| Endpoint | Catatan |
|---|---|
| `GET /material/opsi` | barang stok `[{id,nama,satuan,stok}]` |
| `PUT /order/{id}/unit/{unit}` | `lokasi_label,posisi,jenis_pekerjaan,bagian,suhu,rpm,kondisi,catatan_kondisi` — 422 bila `tidak_normal` tanpa catatan / suhu wajib kosong. Respons `data` = unit |
| `GET /order/{id}/foto` | `{tersimpan[{id,order_item_id,unit_id,slot,url}], sementara[{id,field_name,url}], foto_wajib_kurang[]}` |
| `POST /order/{id}/foto` | multipart `file`, `tipe` (kategori\|unit\|sebelum\|sesudah), `order_item_id` (kategori), `unit_id` (unit), `slot`. → `{status: ditampung\|tersimpan, ...}` (201) |
| `POST /order/{id}/foto/{foto}` | multipart `file` — ganti file foto tersimpan |
| `DELETE /order/{id}/foto/{foto}` | hapus; slotnya muncul lagi di `foto_wajib_kurang` |
| `DELETE /order/{id}/foto-sementara/{id}` | hapus foto tampungan |
| `POST /order/{id}/laporan` | JSON `catatan`(≥3), `materials[{stock_item_id,jumlah}]?`, `butuh_followup?`, `is_klaim?` → `{order, foto_wajib_kurang[labels], lengkap}`. Kekurangan foto tidak memblokir, tetapi menahan berangkat berikutnya sampai dilengkapi |

Kompres foto di perangkat (≈1200px, JPEG 0.7); maks 5 MB per file. Kuota penuh → 422 dengan pesan kuota.

## 6. Keuangan

| Endpoint | Catatan |
|---|---|
| `GET /pengeluaran?mode=bulanan\|harian&bulan=&tanggal=&kategori=&status=&page=` | `data[]` pengeluaran + `meta{...,mode,periode}` + `ringkasan{hari_ini,bulan_ini}` + `breakdown{kategori:{pending,approved,total,count}}`; termasuk entri tempat user jadi partner (`sebagai_partner`, `dicatat_oleh`, `bisa_ubah`, `bisa_hapus`) |
| `GET /pengeluaran/opsi` | `{kategori[{kode,label}], order[{id,label}], partner[{id,name}], batas}` |
| `POST /pengeluaran` | `tanggal_input,kategori,qty,harga,nominal,keterangan?,order_id?,partner_id?` — nominal 1.000–5.000.000, tanggal ≤ hari ini |
| `PUT /pengeluaran/{id}` | hanya pencatat (selain itu 404); respons memuat `status` & `perlu_acc_ulang` |
| `DELETE /pengeluaran/{id}` | hanya pencatat & status `pending` |
| `GET /pendapatan/ringkasan` | `{hari_ini,bulan_ini,total}` (omset jasa order yang dia PIC, tanggal = tanggal dijadwalkan) |
| `GET /pendapatan?mode=&bulan=&tanggal=` | `{mode,periode,total,daftar[{tanggal,order_id,customer,layanan,total}],games|null}` |
| `GET /games/harian?tanggal=` | `{tanggal,omset,unit,mode,minimal_omset,minimal_unit,dapat_games5,dapat_games6,nominal_games5,nominal_games6}` |
| `GET /capaian?bulan=` | `{hari_ini,bulan_ini,total_selesai,bulan,harian[{tanggal,hari,selesai,terkendala}]}` |
| `GET /insentif?bulan=` | ledger `{tanggal,kategori,kategori_label,tipe,nominal,status_verifikasi,catatan,foto_url}` |
| `GET /setoran` | `{ringkasan, kewajiban[{order_id,customer,tanggal_jadwal,total,telat}], riwayat[]}` |
| `POST /setoran` | multipart `order_id,nominal,diserahkan_ke,tanggal_setor,foto,catatan?` |
| `GET /gaji` | periode yang sudah di-ACC milik sendiri |
| `GET /gaji/{id}/slip` | PDF (hanya milik sendiri & sudah di-ACC) |
| `GET /kasbon` | baca saja: nominal, terbayar, sisa |

## 7. Belum ada di v1 (sengaja)

- **Pengiriman push** (tabel `device_tokens` & endpoint daftar sudah ada; kirim dari server = fase notifikasi, `mobile-app/14`).
- **Laporan Harian** (`DailyReport`) — belum diputuskan masih dipakai.
- **Pengaturan notifikasi** (`/notifikasi/pengaturan`) — menyusul bersama push.
- Pencabutan semua token teknisi dari panel Filament (tombol admin) — hapus lewat DB sementara.

## 8. Catatan implementasi & selisih dari rencana awal

- Logika `OrderDetail` **tidak** dipindah: ternyata sudah berada di `TeknisiService`/`UnitReportService`/`OrderService`; controller API memanggil service itu langsung. Yang diekstrak: `TeknisiExpenseService` (dari `LaporanPengeluaran`, kini dipakai web & API) dan `TeknisiKeuanganService` (pendapatan/capaian; `Keuangan` web memakainya).
- Foto pra-laporan memakai tabel `temporary_photo_uploads` yang sudah ada (satu slot satu foto) — tidak butuh `client_uuid`.
- Route lama `teknisi.expense.*` (web) tidak disentuh.
- Konfigurasi: `.env` `API_MIN_APP_VERSION`, `API_TOKEN_HARI`; migrasi baru `personal_access_tokens`, `device_tokens` (jalankan `php artisan migrate`).
