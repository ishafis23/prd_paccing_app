# Ringkasan Progres Revisi 17 Nov 2026 (poin 24–33)

Semua poin ini sisi **admin web** (Filament). Tidak ada kontrak API baru dan tidak ada pekerjaan di app mobile (lihat `PLAN-teknisi.md`). Rencana: `PLAN-superadmin.md`, `PLAN-teknisi.md`, `PLAN-pengerjaan.md`.

| # | Revisi | Status | Di mana / catatan |
|---|---|---|---|
| 24 | Bonus Admin/HRD/Finance 1% omset jasa, minimal Rp 50 jt | Selesai | Menu **SDM/HRD → Bonus Admin**. Pilih periode (mulai–sampai), lihat omset jasa, status memenuhi syarat, dan bonus per penerima. Persen, minimal omset, peran penerima, dan pembagian diatur Owner di halaman yang sama. Default: tiap penerima mendapat 1% penuh. |
| 25 | Jenis pelanggan otomatis | Selesai | Buat Order, Pelanggan Terdaftar: pilihan jenis disembunyikan, tampil "Instansi/Cust Umum (otomatis dari data customer)". Pelanggan Baru tetap memilih. |
| 26 | Unit AC tersimpan bisa dicari | Selesai | Cari berdasarkan kode unit, ruangan, atau PK pada langkah Layanan. |
| 27 | Filter periode di Jadwal OFF | Selesai | Filter **Periode**: Mulai tanggal / Sampai tanggal. |
| 28 | Edit periode gaji, hapus data salah input | Selesai | Penggajian → **Edit Periode** dan **Hapus** (hanya draft; ada juga di halaman detail). Edit ditolak bila periode bertumpuk dengan gaji lain teknisi yang sama. Hitung Ulang kini memakai periode tersimpan. |
| 29 | Input bonus / potongan lain berulang + catatan | Selesai | Halaman detail gaji: **+ Input Bonus** dan **+ Input Potongan Lain** (keterangan, nominal, tanggal, catatan). Bisa berkali-kali, bisa dihapus, bertahan saat Hitung Ulang, tampil di rincian dan slip. |
| 30 | Filter tanggal di Aspek Performa | Selesai | Dashboard Pimpinan → Aspek Performa: Mulai/Sampai tanggal (lintas bulan). Kosong = bulan terpilih. |
| 31 | Pendapatan Jasa vs Material per teknisi, rincian jasa | Selesai | 3.1: kolom Pendapatan Jasa, Pendapatan Material, Total, dan rincian jasa per Cuci/Service/Pemasangan/Pengecekan/Lainnya. Periode order kini mengikuti tanggal pendapatan (sama dengan omset). |
| 32 | Tingkat terkendala & batal customer | Selesai | Sub-tab baru **3.4 Kendala & Batal**: jumlah dan persentase terhadap pekerjaan yang dijadwalkan. Aksi **Batalkan** kini wajib memilih pihak (Customer / Internal). |
| 33 | Filter metode bayar di Payments | Selesai | Filter **Metode Bayar**. |

## Asumsi yang perlu dikonfirmasi
1. **#24**: tiap penerima (Admin, HRD, Finance) mendapat 1% penuh dari omset jasa seluruh perusahaan. Kalau maksudnya 1% dibagi rata, ganti "Pembagian" di pengaturan. Hasilnya berupa perhitungan; belum masuk slip karena admin belum punya modul gaji.
2. **#29**: bonus "Game 4/5/6" yang diinput di sini menambah gaji sebagai bonus biasa. Entri otomatis di Rekap Insentif tidak berubah.
3. **#31**: "Pengecekan" kini jenis layanan sendiri (migrasi `2026_11_17_000004`, bisa diubah di menu Jenis Layanan); tampil terpisah di rincian per teknisi dan Klasifikasi Pengerjaan.
4. **#32**: order batal lama tanpa catatan pihak masuk "tidak dicatat". Kendala lama diisi dari data yang ada (status terkendala, alasan kendala, catatan `[KENDALA]`, atau bukti kendala), pihaknya "tidak dicatat" kecuali ada bukti.

## Catatan teknis
- Migrasi baru: `2026_11_17_000001` (penyesuaian gaji + 2 kolom total), `000002` (kendala/batal di orders + isi data lama), `000003` (pengaturan bonus admin), `000004` (jenis layanan Pengecekan). Jalankan `php artisan migrate`.
- Tes baru: `PayrollRevisi17NovTest`, `Revisi17NovAdminTest`, `BonusAdminTest`.
- Tes `OrderAcUnitLinkTest › infolist order menampilkan Unit AC di Rincian Layanan` gagal juga sebelum perubahan ini (sudah diverifikasi dengan stash); tidak terkait revisi ini.
