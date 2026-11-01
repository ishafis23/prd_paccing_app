# 11 — Proses: Jadwal, Order & Laporan Pengerjaan

Padanan web: `JadwalHariIni`, `OrderDetail` (1338 baris), `LengkapiLaporan`, `RiwayatPengerjaan`. Service: `TeknisiService`, `UnitReportService`, `PhotoReportTemplateService`, `OrderService`, `PaymentService`.

Ini proses terbesar dan paling berisiko. **Prasyarat refactor** (dokumen 02 §6): sebagian logika `OrderDetail` harus dipindah ke Service sebelum endpoint ditulis.

## 1. Alur bisnis

1. **Jadwal**: order hari ini & mendatang milik teknisi (PIC **atau** anggota tim — `Order::untukTeknisi`). Ada penanda urutan "Titik N".
2. **Berangkat** (`berangkat`): ditolak bila ada penghalang administrasi (dokumen 13). Memicu notifikasi WA ke customer (sudah ada di server).
3. **Update lokasi** selama perjalanan (`updateLokasi`) — opsional, throttle.
4. **Check-in** di lokasi (`checkIn`); check-in pertama hari itu → foto Games 2.
5. **Laporan pengerjaan** per layanan/unit: `UnitReportService::siapkan/simpan/simpanDeskriptif`, foto wajib per unit/slot (template dinamis, dev-plan/17–18), suhu, keterangan, salin posisi dari unit sebelumnya.
6. **Edit order di lokasi**: tambah/hapus layanan, batal item, tambah material (`tambahLayanan`, `hapusLayanan`, `toggleItemBatal`, material) — dev-plan/25.
7. **Kendala** (`tandaiKendala`) atau **lapor perbaikan** (`laporPerbaikan`).
8. **Submit laporan** (`submitLaporan`), lalu **metode bayar** (`catatMetodeDipilih`), bukti bayar (`uploadBuktiPembayaran`), `laporBayar`.
9. **Tutup order** (`tutupOrder`) — bukan jalan pintas: syarat administrasi tetap berlaku (komit `7a55892`).
10. **Lengkapi laporan**: order yang foto/keterangannya kurang (`perluDilengkapi`, `lengkapiFotoWajib`, `perbaruiFotoLaporan`).
11. **Riwayat**: order selesai dengan filter.

Omset/pendapatan: tanggal = **tanggal dijadwalkan** (poin 1 revisi 1 Nov).

## 2. Endpoint

| Method & path | Service/aksi | Catatan |
|---|---|---|
| `GET /jadwal?tanggal=` | query `untukTeknisi` | default hari ini + mendatang; ikut flag `titik`, status, jam, ringkasan layanan |
| `GET /order/{id}` | `Order` + `UnitReportService::siapkan` | detail lengkap: customer, alamat (link peta), item, unit report, foto slot wajib & yang kurang (`fotoWajibKurang`), status administrasi |
| `POST /order/{id}/berangkat` | `TeknisiService::berangkat` | 422 + pesan penghalang bila tertahan |
| `POST /order/{id}/lokasi` | `updateLokasi` | body `lat`,`lng` |
| `POST /order/{id}/check-in` | `checkIn` (+ `catatTitikPertama` bila foto dikirim) | multipart opsional `foto_titik_pertama` |
| `PUT /order/{id}/unit/{unit}` | `UnitReportService::simpan` / `simpanDeskriptif` | JSON data unit |
| `POST /order/{id}/unit/{unit}/foto` | `UnitReportService::simpanFotoUnit` | multipart per foto, `slot` (dokumen 15) |
| `DELETE /order/{id}/foto/{foto}` | hapus/ganti foto kategori | hanya foto miliknya |
| `POST /order/{id}/layanan` · `DELETE /order/{id}/layanan/{item}` · `POST /order/{id}/item/{item}/batal` | **perlu service baru** (ekstrak dari `OrderDetail`) | |
| `POST /order/{id}/material` | ekstrak dari `OrderDetail` | |
| `POST /order/{id}/kendala` | `tandaiKendala` | body `alasan` |
| `POST /order/{id}/perbaikan` | `laporPerbaikan` | `catatan`, `estimasi_harga?` |
| `POST /order/{id}/laporan` | `submitLaporan` | payload besar → lihat §3 |
| `POST /order/{id}/pembayaran/metode` · `/bukti` · `/lapor-bayar` | `catatMetodeDipilih`, `uploadBuktiPembayaran`, `laporBayar` | |
| `POST /order/{id}/tutup` | `tutupOrder` | 422 bila syarat belum lengkap |
| `GET /lengkapi-laporan` | order `perluDilengkapi` | |
| `POST /order/{id}/lengkapi-foto` | `lengkapiFotoWajib` | |
| `GET /riwayat?bulan=&q=&page=` | query order selesai | paginasi |

## 3. Perilaku khusus app

- **Draf lokal otomatis** (dev-plan/28 "autosave foto"): data laporan yang belum dikirim disimpan di perangkat per order, dikirim ulang saat sinyal kembali. Server tetap idempotent: kirim ulang unit/foto yang sama tidak membuat duplikat (kunci `order_id + unit + slot`).
- **Submit laporan** dipecah: unit & foto dikirim satu per satu saat diisi; `POST /laporan` hanya "finalisasi" (validasi kelengkapan di server via `fotoWajibKurang` + `UnitReportService::lengkap`). Menghindari satu request raksasa di sinyal lemah.
- **Status order** sumber kebenarannya server; app memuat ulang detail setelah tiap aksi.
- **Peta**: tombol buka Google Maps dengan link yang sudah dihasilkan server (`GoogleMapsLinkService`).
- **Penghalang administrasi** ditampilkan sebagai banner di Jadwal (data dari dokumen 13) dan tombol Berangkat dinonaktifkan dengan alasan.

## 4. Aturan akses

- Jadwal/detail/laporan: **tim** (PIC atau anggota) — pekerjaan dilakukan bersama, laporan terkoneksi ke semua anggota.
- Pendapatan jasa & bonus: lihat dokumen 12/13 (PIC vs tim berbeda per metrik).
- Order milik teknisi lain → 404, bukan 403 (tidak membocorkan keberadaan).

## 5. Pengujian

`tests/Feature/Api/OrderTest.php`, `LaporanTest.php`: jadwal tim vs PIC, berangkat tertahan administrasi, check-in pertama memicu titik pertama, laporan kurang foto → 422 dengan daftar yang kurang, tutup order tanpa syarat → 422, idempotensi unggah ulang foto, order orang lain → 404. Tes web `SyaratAdministrasiTest`, tes laporan yang ada harus tetap lulus.
