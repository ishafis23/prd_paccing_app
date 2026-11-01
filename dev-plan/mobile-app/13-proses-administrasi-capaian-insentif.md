# 13 — Proses: Syarat Administrasi, Capaian & Insentif

Padanan web: banner di `JadwalHariIni`, `CapaianKerja`, `RiwayatAbsensi`, kartu Game di `Keuangan`. Service: `SyaratAdministrasiService`, `TechnicianIncentiveService`, `PayrollService`. Aturan: dev-plan/15, 29, `dev-plan/30`.

## 1. Syarat administrasi ("Administrasi belum tuntas")

`SyaratAdministrasiService::penghalang($user)` mengembalikan daftar penghalang berjenis:

| Jenis | Arti | Menahan |
|---|---|---|
| `setoran` | setoran tunai belum dilaporkan | berangkat & check-in (tanpa tenggang) |
| `laporan` | laporan pengerjaan belum terkirim ke customer | berangkat & check-in |
| `foto` | foto/keterangan wajib belum lengkap | berangkat & check-in |
| `pengeluaran` | pengeluaran pending **atau** catatan pengeluaran harian belum diisi (hari absen, bukan OFF, bukan hari ini) | berangkat & check-in |

Order yang ditutup lebih lama dari tenggang (`administrasi.tenggang_hari`, default 7) dianggap historis dan tidak memblokir.

### Endpoint

| Method & path | Catatan |
|---|---|
| `GET /administrasi` | daftar penghalang `{jenis, order_id?, judul, pesan}` urut sesuai `URUTAN`; `tertahan_setoran` (bool). Dipakai banner & menonaktifkan tombol Berangkat |

App **tidak** menghitung penghalang; ia memanggil endpoint ini saat membuka Jadwal, setelah tiap aksi tulis (pengeluaran, laporan, setoran), dan saat app kembali ke foreground. Setiap penghalang punya `aksi` (deep link ke layar penyelesaiannya: Pengeluaran / Order / Setoran) agar teknisi langsung bisa menuntaskan.

## 2. Capaian kerja

`CapaianKerja`: jumlah order selesai hari ini / bulan ini / total dan grafik harian bulan berjalan.

| `GET /capaian?bulan=` | kartu + deret harian |

## 3. Insentif / Games

Ledger `technician_incentives` (1 baris per teknisi/tanggal/kategori):

| Games | Sumber | Dihitung |
|---|---|---|
| 1 Hadir | absen datang tepat waktu | otomatis (foto → status verifikasi menunggu) |
| 2 Titik pertama | check-in pertama sebelum batas jam | otomatis |
| 3 Cuci motor | catat cuci motor | otomatis |
| 4 Kepulangan | titik & jam pulang | otomatis saat absen pulang |
| 5 Omset tim | omset jasa tim hari itu ≥ ambang (default 850.000): berdua 40.000 / sendiri 100.000 | otomatis (per tanggal order dijadwalkan) |
| 6 Unit | unit tim ≥ ambang (12 berdua / 6 sendiri) → 50.000 | otomatis |
| Denda telat | absen telat (tanpa pengecualian) | otomatis |

Games 4/5/6 dibayar **mingguan**: tampil di slip lalu dikurangkan sebagai "sudah dibayar mingguan" (tidak menambah gaji bersih).

| Method & path | Catatan |
|---|---|
| `GET /insentif?bulan=` | daftar ledger milik user: tanggal, kategori (label), nominal, `status_verifikasi`, catatan |
| `GET /games/harian?tanggal=` | `ringkasanGames56` (omset, unit, mode berdua/sendiri, target, tercapai, bonus) — sama dengan kartu di web |

## 4. Slip gaji

Slip PDF sudah ada di sisi web (`payroll.slip.pdf`, hanya setelah ACC). Fase 2:
`GET /gaji` (daftar periode + status) dan `GET /gaji/{run}/slip` (unduh PDF, hanya milik sendiri & status sudah disetujui). Rincian dihitung server; app hanya menampilkan.

## 5. Pengujian

`tests/Feature/Api/AdministrasiTest.php`: tiap jenis penghalang muncul/hilang (termasuk hari OFF, partner, tenggang), berangkat ditolak saat tertahan, deep link `aksi` benar. `InsentifTest.php`: format ledger, kasus Games 5/6 (1.075.000 / 7 unit), akses data orang lain → 404.
