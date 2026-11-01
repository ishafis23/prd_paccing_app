# 20 — Adopsi UI/UX dari Portal Teknisi Web

**Ya — portal web teknisi dipakai sebagai acuan (living spec) UI/UX app.** Yang diadopsi: alur, struktur halaman, istilah, warna, dan perilaku. Yang tidak disalin: kodenya (Livewire/Blade/Alpine) — app ditulis ulang di React Native dengan komponen native, memanggil API (dokumen 02).

Alasan mengadopsi:
- Teknisi sudah terbiasa dengan alur & istilah yang sama → pelatihan minimal.
- Sudah melewati banyak revisi client; keputusan UX yang sudah matang tidak diulang.
- Web yang sama tetap berjalan sebagai cadangan selama transisi, sehingga kedua UI tidak boleh berbeda perilaku.

## 1. Pemetaan halaman web → layar app

| Halaman web (route) | Layar app | Dokumen | Catatan adaptasi |
|---|---|---|---|
| Jadwal Saya (`teknisi.jadwal`) | **Jadwal** (tab) | 11, 13 | banner "Administrasi belum tuntas" di atas; kartu order dengan badge status & "TITIK N" |
| Order Detail (`teknisi.order`) | **Detail Order** (stack) | 11 | web memuat semuanya di satu halaman panjang → app dipecah per langkah (Info · Layanan · Laporan per unit · Foto · Pembayaran · Tutup) dengan progres kelengkapan |
| Absensi (`teknisi.absensi`) | **Absensi** (tab) | 10 | pemindai QR native; tombol Datang/Pulang; cuci motor |
| Pulang Cepat (`teknisi.pulang-cepat`) | **Pulang Cepat** (stack dari Absensi/Lainnya) | 10 | |
| Riwayat Absensi | **Riwayat Absensi** (stack) | 10 | |
| Riwayat Pengerjaan (`teknisi.riwayat`) | **Riwayat** (tab) | 11 | pencarian + filter bulan, paginasi tak-hingga |
| Lengkapi Laporan (`teknisi.lengkapi-laporan`) | **Lengkapi** (stack, juga lencana angka) | 11 | lencana jumlah order yang perlu dilengkapi |
| Capaian (`teknisi.capaian`) | **Capaian** (stack) | 13 | grafik harian native |
| Keuangan (`teknisi.keuangan`) — tab Pengeluaran/Pendapatan | **Keuangan** (tab) | 12 | dua segmen; Pengeluaran + form catat/ubah, Pendapatan + kartu Game 5/6 |
| Laporan Pengeluaran (`teknisi.laporan-pengeluaran`) | bagian **Keuangan > Pengeluaran** | 12 | |
| Setoran Tunai (`teknisi.setoran`) | **Setoran** (stack dari Keuangan/Lainnya) | 12 | |
| Akun (`teknisi.akun`) | **Akun** (stack dari Lainnya) | 02 | foto profil, versi app, logout |
| Laporan Harian (`teknisi.daily-report`) | *belum diputuskan* | — | tidak ada di navigasi bawah; konfirmasi masih dipakai atau tidak sebelum dimasukkan |

## 2. Navigasi

Web punya **9 tab** di bar bawah (Jadwal, Absensi, Pulang, Riwayat, Lengkapi, Capaian, Keuangan, Setoran, Akun) — di app terlalu padat (ikon sempit, target sentuh kecil). Usulan **5 tab + "Lainnya"**:

| Tab | Isi |
|---|---|
| **Jadwal** | jadwal & banner administrasi |
| **Absensi** | datang/pulang/cuci motor (+ pintasan Pulang Cepat) |
| **Riwayat** | riwayat pengerjaan |
| **Keuangan** | Pengeluaran · Pendapatan (+ Setoran) |
| **Lainnya** | Lengkapi (lencana), Capaian, Riwayat Absensi, Pulang Cepat, Setoran, Akun, Keluar |

Alternatif: tetap 5 tab dengan "Lengkapi" menggantikan "Riwayat" bila client lebih sering memakai Lengkapi. Putuskan bersama client (keputusan #7 di README).

## 3. Design token (diambil dari web)

Sumber: `resources/views/layouts/teknisi.blade.php`, `teknisi-bottom-nav.blade.php`, kelas Tailwind yang dipakai di `resources/views/livewire/teknisi/*`.

| Token | Nilai web | Pemakaian |
|---|---|---|
| Latar halaman | `gray-100` | semua layar |
| Header | gradasi `sky-600 → blue-600 → blue-700`, tinggi 56px, judul putih tebal, label usaha kecil kapital berspasi | AppBar |
| Aksen utama | `blue-600` / `blue-700` (tombol), `blue-50/100` (latar lembut) | tombol primer, tab aktif |
| Sukses / uang masuk | `emerald-600/700`, latar `emerald-50` ("tercapai", pendapatan) | Game tercapai, total pendapatan |
| Peringatan | `amber-100/50` (banner administrasi) | penghalang, status menunggu |
| Bahaya | `red-600`, latar `red-50` | hapus, error |
| Kartu | putih, `rounded-2xl`, `shadow-sm`, `ring-1 ring-gray-100`, padding 16 | semua kartu/daftar |
| Badge status | pil `rounded-full` + warna sesuai status (kuning=Menunggu, hijau=Disetujui, merah=Ditolak) | pengeluaran, pulang cepat |
| Tab bawah | tinggi 68, ikon outline (aktif: solid + pil `blue-100`), label 11px tebal | bottom tab |
| Font | Instrument Sans (web) → pakai font sistem Android/Inter di app | |
| Ikon | Heroicons (web) → `@expo/vector-icons` set yang setara, atau paket Heroicons RN | |
| Lebar konten | maks 480px di web → app penuh lebar layar | |

Nama usaha & logo di header berasal dari **Info Usaha** (`BusinessInfoService`) → dikirim lewat `GET /me` agar branding tetap dikelola dari panel admin.

## 4. Perilaku UX yang dipertahankan

- **Istilah sama persis** dengan web: Titik, Berangkat, Check-in, Lengkapi, Setoran, Administrasi belum tuntas, Games. Jangan ganti istilah tanpa konfirmasi client.
- Banner kuning "Administrasi belum tuntas" dengan pesan dari server, kini dengan **tombol langsung ke layar penyelesaiannya**.
- Slider "Check-in Sekarang" di detail order (web) → tombol geser/tekan-tahan di app agar tidak tersentuh tak sengaja.
- Foto wajib menampilkan slot kosong/terisi dan daftar yang kurang (`fotoWajibKurang`).
- Kartu Game 5/6: hijau "✓ Tercapai" + bonus; abu-abu bila belum, dengan target.
- Filter pengeluaran Perbulan / Perhari-Tanggal (revisi poin 4).
- Konfirmasi sebelum aksi tak terbalikkan (hapus, tutup order).

## 5. Yang sengaja diperbaiki di app (bukan sekadar disalin)

| Masalah di web | Perbaikan di app |
|---|---|
| Detail order = satu halaman 1300+ baris logika, scroll panjang | Dipecah per langkah dengan indikator kelengkapan |
| 9 tab sempit | 5 tab + Lainnya |
| Upload foto bergantung koneksi saat itu juga | Antrean unggah + status per foto (dokumen 15) |
| Pesan error teknis kadang bocor | Pesan dari `BusinessRuleException` ditampilkan apa adanya; error tak terduga → pesan umum + tombol coba lagi |
| Kamera `getUserMedia` browser | Kamera native, fokus/flash lebih andal |
| Tidak ada notifikasi | Push (dokumen 14) |

## 6. Proses desain

1. Ambil tangkapan layar tiap halaman web (HP) sebagai referensi — sebagian sudah ada di `dev-plan/req dev 1 nov 2026/`.
2. Buat wireframe cepat per layar (Figma/di kertas) hanya untuk layar baru/berubah (detail order bertahap, navigasi, antrean foto).
3. Bangun **pustaka komponen** dulu (Card, Badge, Banner, AppBar, TabBar, FormField, MoneyText, PhotoSlot) dengan token di atas, baru layar.
4. Uji bersama 2–3 teknisi nyata sebelum fase berikutnya (dokumen 30).
