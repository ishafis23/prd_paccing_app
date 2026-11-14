# Plan: Reminder Servis Berkala Otomatis (Revisi 14 Nov 2026, poin 2a & 2b)

## Tujuan
- Customer (dan admin) diingatkan otomatis saat sudah waktunya cuci AC lagi.
- Interval default 3 bulan. Teknisi bisa menanyakan ke customer dan mengisi interval sendiri.
- Setelah pengingat pertama, bila customer belum order lagi, diingatkan **tiap bulan**.
- Pengiriman lewat cron. Sukses = `status_kirim 1`. Gagal = dicoba lagi hari berikutnya.

## Kondisi sekarang (hasil cek kode)
- `service_reminders` sudah dibuat otomatis saat order **lunas** (`PaymentService::buatReminder`), satu baris per order.
- Interval masih dipatok: company 1 bulan, selain itu 3 bulan (dev-plan/12 §3.6, ada test `ServiceReminderKategoriTest`).
- Admin hanya bisa "Tandai Dihubungi" manual (menu Notice Servis Berikutnya, badge `jatuhTempo` H-7).
- Belum ada cron pengirim. Jadwal di `routes/console.php` baru `foto:bersihkan`.
- Infrastruktur WA siap pakai: `NotifikasiWaService`, `WhatsappService::kirimTeksSistem`, `WaLog`, `WaTemplateService`, queue database, mode uji `WA_TEST_TARGET`.

## Keputusan (default, bisa diubah)
| Pertanyaan | Keputusan |
|---|---|
| Aturan company 1 bulan | **Dipertahankan** sebagai default. Input teknisi menimpa. |
| Pesan ke admin | **Dashboard saja** (badge + kolom status kirim di menu Notice Servis). Tanpa WA ke admin. |
| Retry gagal | Maksimal **3 percobaan**, satu per hari. Lewat itu tetap `gagal` dan tampil di admin. |
| Mode uji WA | Ikut `config('wa.test_target')` seperti notifikasi lain. |
| Reminder aktif | Satu baris per order tetap, tapi cron hanya mengirim **yang terbaru per customer**. Baris lama ditutup (`selesai`). |

## Desain data
Migrasi `add_pengiriman_ke_service_reminders`:
- `sumber_interval` string, default `default` (`default` / `teknisi`)
- `status_kirim` tinyint, default 0 (0 menunggu, 1 terkirim, 2 gagal)
- `percobaan` smallint, default 0
- `jumlah_terkirim` smallint, default 0
- `terakhir_dicoba_at`, `terkirim_at` timestamp null
- `error_terakhir` string null
- `tahap` string, default `awal` (`awal` / `bulanan`)

Migrasi `add_interval_servis_ke_orders`: `orders.interval_servis_bulan` smallint null (pilihan customer yang diisi teknisi).

## Alur
1. **Order lunas** -> `buatReminder` memakai `orders.interval_servis_bulan` bila ada (`sumber_interval=teknisi`), kalau tidak default (3, atau 1 untuk company).
2. **Teknisi isi interval** (API `POST /order/{order}/interval-servis`, 1..12 bulan) -> simpan di order. Bila reminder sudah ada dan belum pernah terkirim, interval dan tanggalnya dihitung ulang dari tanggal bayar.
3. **Cron `reminder:kirim`** harian 09:00 (`withoutOverlapping`):
   - Pilih reminder dengan `tanggal_servis_berikutnya <= hari ini`, `status_notice` bukan `selesai`/`sudah_dihubungi`, `percobaan < 3`.
   - Bila customer punya order lunas yang lebih baru dari order acuan -> tutup (`selesai`), tidak dikirim.
   - Kirim WA lewat `NotifikasiWaService::kirimReminderServis` (template `reminder_servis`, tercatat di `WaLog`).
   - **Sukses**: `status_kirim=1`, `terkirim_at`, `jumlah_terkirim++`, `percobaan=0`, `tahap=bulanan`, tanggal berikutnya +1 bulan.
   - **Gagal / dilewati karena error**: `status_kirim=2`, `percobaan++`, `error_terakhir`; dicoba lagi besok.
   - Dilewati tanpa hitung percobaan bila WA nonaktif, nomor customer kosong, atau device belum ada (dicatat di `error_terakhir`).
4. **Admin** melihat kolom Status Kirim, jumlah terkirim, dan error di menu Notice Servis Berikutnya. Ada aksi "Kirim Sekarang".

## Berkas yang disentuh
- `database/migrations/*` (2 baru)
- `app/Models/ServiceReminder.php`, `app/Models/Order.php` (fillable)
- `app/Services/PaymentService.php` (`buatReminder`)
- `app/Services/ServiceReminderService.php` (baru: atur interval, proses kirim)
- `app/Services/NotifikasiWaService.php`, `app/Services/WaTemplateService.php` (template `reminder_servis`)
- `app/Console/Commands/KirimReminderServis.php` (baru) + `routes/console.php`
- `app/Http/Controllers/Api/V1/OrderController.php` + `routes/api.php`
- `app/Filament/Resources/ServiceReminderResource.php`
- `tests/Feature/ServiceReminderKirimTest.php` (baru)

## Di luar cakupan tahap ini
- Input interval di UI app mobile (Flutter) dan portal web teknisi. API disiapkan dulu.
- Poin lain revisi 14 Nov (foto bukti, timestamp, video, ulasan, auto-WA laporan, dst.).

## Verifikasi
- Test Pest baru: interval teknisi, default, cron sukses, gagal + retry, batas 3x, customer sudah order baru, mode uji.
- Test yang ada (`ServiceReminder*`, `NotifBerangkat*`) tetap hijau.

---

# Progres tahap backend + admin (14 Nov 2026)

| # | Revisi | Status | Catatan |
|---|---|---|---|
| 2a/2b | Reminder servis berkala | Selesai (backend/API/admin) | Input interval di UI app/portal belum. |
| 4 | Foto bukti pengeluaran + tolak foto kembar | Selesai (API + admin) | Wajib via API (`EXPENSE_FOTO_WAJIB`, default true). Deteksi kembar: sha256 + hash perseptual. Bug "Rp NaN": API kini mengirim `ringkasan.*.total`. Form pengeluaran di portal web belum mewajibkan foto. |
| 10 | Tanggal di Riwayat Pengerjaan | Selesai (API) | `tanggal_tampil` ("Sel, 10 Nov 2026"), filter dan urutan menurut tanggal pengerjaan. Portal web sudah menampilkan tanggal. |
| 11 | Foto setoran tunai | Sudah ada | Foto sudah wajib, API mengirim `foto_url`, admin menampilkan. Yang kurang: app menampilkan `foto_url` di daftar. |
| 17 | Bukti kendala/pending sudah diberitahukan ke customer | Selesai (API + admin) | Wajib via API (`KENDALA_BUKTI_WAJIB`, default true): `bukti` (foto) + `pihak` (customer/teknisi). Riwayat di tabel `order_kendala_buktis`, tampil di detail order admin. API kini juga menerima `jenis=sore`. Portal web belum mewajibkan bukti. |
| 18 | Auto-WA laporan + invoice/kuitansi saat selesai | Selesai (backend) | Default **MATI**: `WA_NOTIF_OTOMATIS_SELESAI=true` untuk menyalakan. Dipicu saat laporan selesai dan saat lunas (job antre, idempoten). Tagihan/kuitansi hanya terkirim bila order sudah punya invoice aktif. |

Perilaku baru yang perlu diketahui app mobile:
- `POST /pengeluaran` dan `PUT /pengeluaran/{id}` harus multipart dengan field `foto` (PUT lewat `_method=PUT`).
- `POST /order/{id}/kendala` wajib `bukti` (gambar), `pihak` (`customer`|`teknisi`), opsional `jenis` (`besok`|`sore`).
- `POST /order/{id}/interval-servis` `{interval_bulan: 1..12}`.
- Respons order ringkas punya `tanggal_tampil`; detail order punya `kendala_bukti`; pengeluaran punya `foto_url`.
