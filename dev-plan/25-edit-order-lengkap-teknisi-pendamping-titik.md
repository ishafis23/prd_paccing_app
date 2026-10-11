# 25 — Edit Order Lengkap (Ganti Teknisi, Pendamping, & Titik)

> STATUS: **DIEKSEKUSI** (keputusan §7 dikonfirmasi 11 Okt 2026).
> Otoritas: **Admin/Owner saja** (bukan teknisi).
>
> Keputusan final: (1) UI = **modal aksi "Edit Order"** di tabel; (2) PIC &
> pendamping = **pilih teknisi individu langsung** (bukan konsep Tim baku);
> (3) titik = **pilih dari master Titik saja**; (4) order **selesai/batal terkunci**;
> (5) audit = **`catatan_admin`**.
>
> Implementasi: `OrderService::ubahPenugasan()`, `OrderService::ubahTitik()`;
> aksi `editOrder` di `OrderResource`; tes `tests/Feature/EditOrderLengkapTest.php`.

## 1. Permintaan

Pada order, admin bisa melakukan edit lengkap: mengganti **teknisi (PIC)**,
**pendamping** (anggota tim pengerjaan), dan **titik** (slot/jam kunjungan).

## 2. Kondisi sekarang (dengan bukti)

Assign/ubah penugasan saat ini **terpisah-pisah** sebagai aksi tabel, dan **tidak ada
halaman/modal "Edit Order"** (hanya `ViewOrder`; `getPages()` hanya index/create/view —
`app/Filament/Resources/OrderResource.php:1111`):

| Aksi | Berkas | Batasan sekarang |
|---|---|---|
| Assign Teknisi (multi, pertama = PIC) | `OrderResource.php:804` | hanya bila `teknisi_id === null` |
| Assign Tim baku | `OrderResource.php:850` | hanya bila `teknisi_id === null` |
| Ganti PIC | `OrderResource.php:875` | PIC saja; order belum selesai/batal |
| Tambah Anggota Tim | `OrderResource.php:908` | hanya menambah, **tidak bisa hapus** anggota |
| Jadwalkan Ulang | `OrderResource.php:1021` | **hanya** saat status `terkendala`; set tanggal+jam (bukan `titik_id`) |
| Koreksi Total / Catat Pembayaran | `OrderResource.php` | — |

Service (`app/Services/OrderService.php`): `assignTechnician` (:373), `assignTeam` (:401),
`tambahTeknisi` (:440), `gantiPic` (:473), `reschedule` (:524). **Tidak ada** method untuk
**menghapus anggota tim**, **mengubah `titik_id`**, atau menyimpan penugasan+jadwal sekaligus.

Model: `order_technicians` (keanggotaan termasuk PIC) `app/Models/OrderTechnician.php`;
`orders.teknisi_id` = PIC; `orders.titik_id` + `orders.jam_jadwal` = slot kunjungan
(`app/Models/Titik.php`). Titik dipilih saat Create Order (`CreateOrder.php:244`).

## 3. Gap

1. Tidak ada satu tempat untuk ganti PIC + atur pendamping (tambah **dan** hapus) sekaligus.
2. Anggota tim tidak bisa **dikeluarkan** (hanya bisa ditambah).
3. `titik_id` **tidak bisa diubah** setelah order dibuat (`reschedule` hanya isi tanggal/jam, dan hanya saat `terkendala`).

## 4. Usulan desain

Satu aksi **"Edit Order"** (Owner/Admin) di tabel Order, berisi:

- **Teknisi (PIC)** — `Select` teknisi (default PIC sekarang).
- **Pendamping** — `Select` **multiple** anggota tim; nilai akhir = daftar anggota yang
  diinginkan. PIC otomatis dikecualikan dari pendamping. Yang dihapus dari pilihan =
  dikeluarkan dari `order_technicians`.
- **Titik** — `Select` titik aktif (`titik_id`); saat dipilih, `jam_jadwal` ikut terisi dari
  `titik.jam` (boleh override manual).
- **Tanggal Jadwal** — `DatePicker` (opsional berubah).
- **Alasan Perubahan** (opsional) → dicatat ke `catatan_admin` (pola yang sudah dipakai
  `gantiPic`/`reschedule`).

Service baru (satu pintu, transaksional):
- `ubahPenugasan(Order $order, ?User $pic, array $pendampingIds, User $actor)` — ganti PIC
  (reuse logika `gantiPic` termasuk menutup attendance terbuka PIC lama) + sinkron
  `order_technicians` (hitung selisih: tambah yang baru, hapus yang tak lagi dipilih).
- `ubahJadwal(Order $order, ?int $titikId, ?string $tanggal, ?string $jam, User $actor)` —
  set `titik_id`/`jam_jadwal`/`tanggal_jadwal`. (Boleh dipakai juga untuk order
  non-terkendala, beda dari `reschedule`.)
- Tambah `hapusTeknisi(Order $order, User $teknisi, User $actor)` untuk kebutuhan "keluarkan anggota".

Aturan (usulan default, mohon dikonfirmasi di §7):
- Hanya **Owner/Admin**.
- Order **selesai/batal** tidak boleh diubah penugasan/jadwalnya (kecuali Anda minta bisa).
- PIC wajib teknisi aktif; PIC juga tersimpan di `order_technicians`.
- Setelah ganti PIC/pendamping, **status order tidak direset** (mengikuti perilaku `gantiPic`).

## 5. Berkas terdampak

- `app/Filament/Resources/OrderResource.php` — aksi "Edit Order" (modal form lengkap).
- `app/Services/OrderService.php` — `ubahPenugasan`, `ubahJadwal`, `hapusTeknisi`.
- (opsional) `app/Filament/Resources/OrderResource/Pages/EditOrder.php` bila dipilih
  halaman penuh alih-alih modal.
- `tests/Feature/…` — tes baru.

## 6. Test (rencana)

- Ganti PIC + ubah daftar pendamping sekaligus (tambah 1, hapus 1) — `order_technicians`
  dan `teknisi_id` benar.
- PIC otomatis tidak boleh ada di daftar pendamping.
- Ubah `titik_id` → `jam_jadwal` ikut berubah.
- Order `selesai`/`batal` ditolak; teknisi tidak punya akses.
- Perubahan tercatat di `catatan_admin`.

## 7. Keputusan yang diminta

1. **Bentuk UI**: modal aksi "Edit Order" (di tabel) **atau** halaman Edit penuh? (usul: modal)
2. **Pendamping**: pilih teknisi langsung (sinkron `order_technicians`), atau tetap pakai
   konsep **Tim baku** + override? (usul: pilih langsung)
3. **Titik**: hanya mengganti slot/jam (`titik_id` + `jam_jadwal`), atau sekaligus boleh
   mengubah tanggal? (usul: boleh tanggal + jam)
4. **Order `selesai`**: tetap terkunci, atau admin boleh mengubah penugasan/titiknya juga?
   (usul: terkunci; kecuali Anda minta sebaliknya)
5. **Jejak audit**: cukup `catatan_admin`, atau butuh tabel log tersendiri? (usul: `catatan_admin`)
