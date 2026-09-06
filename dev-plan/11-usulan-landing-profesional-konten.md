# Usulan — Landing Page Profesional + Menu "Website" (kelola konten depan)

> STATUS: **DISETUJUI (8 September 2026)** — B38a, B38b, B38c, B38d, B38e,
> B38f, B38g (via asumsi, klarifikasi timeout). Tercatat di
> `02-keputusan-eksekusi.md`; dokumen ini acuan implementasi.

## 1. Konteks

Arahan owner (8 Sep): rapikan landing page agar lebih profesional, dan sediakan
menu khusus di admin untuk mengatur isi halaman depan — mulai dari
**carousel landscape**, **layanan (gambar, deskripsi, harga)**, **maps di
akhir**, dan info lain. Proses: riset pola → usulan → eksekusi.

Landing saat ini: statis (hero teks, kartu layanan mentah dari
service_catalog, area, WA, footer) — belum ada carousel, gambar layanan,
deskripsi, peta, atau pengaturan dari admin.

## 2. Hasil riset pola (referensi yang diikuti)

Pola landing profesional untuk bisnis jasa rumahan/AC (acuan umum: situs jasa
service & home-care profesional, mis. pola "hero + layanan + cara kerja +
area/peta + kontak", plus disiplin tipografi/spasi dari design system populer
seperti Stripe/Linear untuk kebersihan visual). Anatomi yang dipakai:

1. **Topbar tipis** — jam operasional & nomor WA (info singkat).
2. **Header sticky** — logo + nama usaha, menu halus (Beranda, Layanan, Cara
   Kerja, Area, Kontak), tombol CTA "Chat WA".
3. **Hero carousel landscape** — slide gambar lebar (16:6), overlay gradasi,
   judul/subjudul, 2 CTA; dot navigasi + panah; autoplay halus. Bila belum
   ada slide: fallback hero gradasi brand (konten lama).
4. **Strip keunggulan** — 4 poin singkat (Berpengalaman, Tepat waktu, Garansi
   pengerjaan, Area Makassar/Gowa/Maros).
5. **Layanan** — kartu: gambar, nama, deskripsi singkat, harga, label
   interval (mis. "rutin tiap 3 bulan"), CTA order.
6. **Cara Kerja** — 4 langkah (Hubungi → Jadwal → Teknisi datang → Beres).
7. **Area Layanan + Peta** — chips kota + Google Maps embed (dari admin).
8. **CTA kontak** — alamat, tombol WA.
9. **Footer** — nama usaha, alamat, jam operasional, sosmed, ©.

## 3. Keputusan yang Perlu Disetujui (default rekomendasi dicetak tebal)

### B38a — Data baru
- **(a)** Tiga sumber data:
  1. `hero_slides` (CRUD): gambar landscape, judul, subjudul, teks tombol,
     tautan tombol, urutan, aktif.
  2. Kolom tambahan `service_catalog`: `deskripsi`, `gambar`,
     `tampil_beranda` (bool), `urutan_beranda` — dikelola lewat resource
     **Layanan Beranda** (nama/harga ikut data katalog; admin tinggal pilih
     yang tampil, urutkan, isi gambar & deskripsi).
  3. `beranda_settings` (baris tunggal): maps embed URL, jam operasional,
     sosmed (IG/FB), toggle seksi.
- (b) Semua diketik manual di kode (tanpa menu admin) — tidak sesuai.

Rekomendasi: **(a)**.

### B38b — Menu admin khusus
Grup navigasi **"Website"** berisi:
1. **Hero Slider** — CRUD slide (upload gambar landscape, urutan, aktif).
2. **Layanan Beranda** — daftar layanan dari katalog: toggle tampil, urutan,
   isi deskripsi & gambar (harga & interval dari katalog).
3. **Pengaturan Beranda** — maps embed (Google Maps iframe src), jam
   operasional, sosmed, toggle seksi (Layanan / Cara Kerja / Area / Peta).
Akses: **Owner & Admin** (konsisten pengelolaan web).

Rekomendasi: **(a)**.

### B38c — Hero carousel
Gambar landscape disimpan di storage public `hero/` (guard kuota B25, hapus
file lama saat ganti/hapus slide). Tampil bila ada slide aktif; fallback hero
gradasi bila kosong. Autoplay + panah + dot; teks bisa dikosongkan (gambar
full). Test render.

Rekomendasi: **(a)**.

### B38d — Layanan
Resource **Layanan Beranda** menampilkan semua baris service_catalog aktif
dengan kolom: tampil (toggle), urutan, gambar, deskripsi, harga, layanan.
Landing menampilkan hanya yang `tampil_beranda = true`, urut
`urutan_beranda`, fallback bila belum ada pilihan: tampilkan semua layanan
aktif (urutan nama) supaya halaman tidak pernah kosong. Kartu profesional
(gambar, deskripsi, harga, interval, CTA).

Rekomendasi: **(a)**.

### B38e — Peta & info
Pengaturan Beranda menyimpan URL embed Google Maps (bukan koordinat mentah).
Landing: seksi peta di akhir sebelum CTA kontak; bila belum diisi → seksi
peta disembunyikan (tidak ada placeholder jelek). Jam operasional & sosmed
tampil di topbar/footer bila diisi; nama/alamat/WA tetap dari Info Usaha.

Rekomendasi: **(a)**.

### B38f — Tampilan (blade ulang)
`landing.blade.php` ditulis ulang mengikuti anatomi §2 (Tailwind, warna brand
sky/blue, mobile-first). Tidak ada dependensi frontend baru (carousel pakai
Alpine/JS kecil bawaan). Konten seksi memakai data DB; semua seksi punya
fallback agar halaman tetap rapi saat data belum lengkap.

Rekomendasi: **(a)**.

### B38g — Non-tujuan versi ini
Testimoni, galeri portofolio, artikel/blog, multi-bahasa, form booking:
**Fase 2** (opsional). Fokus: carousel + layanan + peta + info — sesuai
permintaan.

Rekomendasi: **(a)**.

## 4. Desain Teknis (ringkas)

- Migrasi: `hero_slides`, `beranda_settings`, + kolom service_catalog.
- Model: HeroSlide, BerandaSetting (+factory), ServiceCatalog + fillable baru.
- Service: `BerandaService` (hero aktif, layanan beranda dgn fallback,
  settings, helpers tampil), `HeroSlideService`? (CRUD lewat resource biasa +
  hapus file via hooks), kuota guard utk upload gambar.
- Resource baru: HeroSlideResource (group Website), LayananBerandaResource
  (model ServiceCatalog, scope utk beranda), halaman Pengaturan Beranda
  (custom page + form simpan via service) — atau gabung yg lebih ringkas saat
  eksekusi.
- LandingController: kirim data (slides, layanan, settings, info usaha,
  areas). Blade ditulis ulang.
- Policy Website: Owner/Admin.
- Test Pest: migrasi/skema, resource CRUD & otorisasi, upload slide + hapus
  file, settings simpan, landing render (slide muncul, layanan pilihan urut,
  fallback kosong, peta tampil/sembunyi), regresi Info Usaha tetap.

## 5. Definisi "Selesai"
1. Admin punya menu Website: Hero Slider (CRUD), Layanan Beranda (toggle/
   urut/gambar/deskripsi), Pengaturan Beranda (maps, jam, sosmed, toggle).
2. Landing tampil profesional: topbar, header sticky, carousel (atau
   fallback), keunggulan, layanan berkartu rapi, cara kerja, area + peta
   (opsional), CTA, footer.
3. Semua isi depan bisa diubah dari admin tanpa sentuh kode; kuota & aturan
   file lama aman; landing tidak pernah kosong/jelek walau data belum lengkap.
4. Test Pest hijau + laporan.
