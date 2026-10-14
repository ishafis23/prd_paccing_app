# 27 — Contoh Isian "Template Pesan WA" (input manual)

> Panduan mengisi form **WhatsApp → Template Pesan WA** (menu admin).
> File ini untuk input **manual**. Bila memakai seeder, jalankan:
> `php artisan db:seed --class=WaTemplateSeeder --force` (idempoten, tidak menimpa editan).

## Cara pakai form

Isi 4 field berikut, lalu **Create**:

| Field | Keterangan |
|---|---|
| **Kode** | Huruf kecil & underscore. Kode yang **terpicu otomatis** hanya: `berangkat`, `pembayaran_lunas`. Kode lain boleh dibuat (disiapkan, belum terpicu). |
| **Nama** | Label bebas untuk admin. |
| **Isi Pesan** | Isi pesan + placeholder. Placeholder kosong otomatis dibuang. |
| **Aktif** | ON = dipakai untuk kirim otomatis; OFF = notifikasi otomatis kode ini dilewati. |

**Placeholder tersedia:** `{nama}`, `{nama_usaha}`, `{layanan}`, `{alamat}`, `{teknisi}`, `{jadwal}`, `{nomor_invoice}`, `{total}`, `{tautan_invoice}`.

> Catatan: `{nama}` = nama customer. Jangan ganti jadi kata lain (harus sama persis, huruf kecil, dalam kurung kurawal).

---

## 1) Kode: `berangkat`  — TERPICU OTOMATIS
**Nama:** `Notifikasi Teknisi Berangkat`
**Aktif:** ✅ ON
**Kapan terkirim:** otomatis saat teknisi menggeser tombol "berangkat" (status order → `menuju_lokasi`). Target = `customer.no_hp`.

**Isi Pesan** (copy persis ke textarea):
```
Assalamu'alaikum Warahmatullahi Wabarakatuh,

Bapak/Ibu {nama}

Teknisi kami ({teknisi}) sedang menuju lokasi Anda untuk mengerjakan {layanan}, diperkirakan tiba sekitar {jadwal}.

Alamat pengerjaan:
{alamat}

Mohon pastikan lokasi dapat diakses saat teknisi tiba. Jika ada perubahan, silakan hubungi kami.

Terima kasih atas kepercayaan Anda.

Hormat kami,
{nama_usaha}
```

<details><summary>Alternatif versi ramah/singkat (ganti Isi Pesan)</summary>

```
Halo Bapak/Ibu {nama} 👋

Teknisi {nama_usaha} ({teknisi}) sedang dalam perjalanan ke lokasi Anda untuk {layanan}. Mohon ditunggu ya 🙏

Salam,
{nama_usaha}
```
</details>

---

## 2) Kode: `pembayaran_lunas`  — TERPICU OTOMATIS (tombol admin)
**Nama:** `Notifikasi Pembayaran + Invoice`
**Aktif:** ✅ ON
**Kapan terkirim:** saat admin menekan tombol **"Kirim Notif Pembayaran"** di halaman Invoice yang berstatus **Lunas**. PDF invoice otomatis dilampirkan.

**Isi Pesan** (copy persis ke textarea):
```
Assalamu'alaikum Warahmatullahi Wabarakatuh,

Bapak/Ibu {nama}

Terima kasih, pembayaran invoice {nomor_invoice} sebesar {total} telah kami terima dengan baik. Invoice resmi terlampir pada pesan ini.

Rincian dapat dilihat pada tautan berikut:
{tautan_invoice}

Terima kasih atas kepercayaan Anda menggunakan layanan kami.

Hormat kami,
{nama_usaha}
```

<details><summary>Alternatif versi singkat (ganti Isi Pesan)</summary>

```
Terima kasih Bapak/Ibu {nama} 🙏

Pembayaran invoice {nomor_invoice} sebesar {total} sudah kami terima. Invoice terlampir pada pesan ini. Sampai jumpa di layanan berikutnya!

{nama_usaha}
```
</details>

---

## 3) Kode: `selesai_pengerjaan`  — BELUM TERPICU (disiapkan)
**Nama:** `Notifikasi Pengerjaan Selesai`
**Aktif:** ✅ ON (boleh OFF bila belum mau dipakai)
**Catatan:** belum ada pemicu otomatis; disiapkan untuk fase lanjut (lihat dev-plan/27 §9).

**Isi Pesan:**
```
Assalamu'alaikum Warahmatullahi Wabarakatuh,

Bapak/Ibu {nama}

Pengerjaan {layanan} di {alamat} telah selesai dikerjakan oleh {teknisi}. Dokumen/bukti pengerjaan menyusul bila diperlukan.

Terima kasih atas kepercayaan Anda.

Hormat kami,
{nama_usaha}
```

---

## 4) Kode: `reminder_tagihan`  — BELUM TERPICU (disiapkan)
**Nama:** `Pengingat Tagihan`
**Aktif:** ✅ ON (boleh OFF bila belum mau dipakai)
**Catatan:** belum ada pemicu otomatis; disiapkan untuk pengingat terjadwal fase lanjut.

**Isi Pesan:**
```
Assalamu'alaikum Warahmatullahi Wabarakatuh,

Bapak/Ibu {nama}

Kami mengingatkan invoice {nomor_invoice} sebesar {total} belum kami terima pembayarannya. Mohon konfirmasi pembayaran melalui tautan berikut:
{tautan_invoice}

Terima kasih.

Hormat kami,
{nama_usaha}
```

---

## Ringkasan cepat

| Kode | Nama | Aktif | Terpicu otomatis? |
|---|---|---|---|
| `berangkat` | Notifikasi Teknisi Berangkat | ON | ✅ (slider berangkat teknisi) |
| `pembayaran_lunas` | Notifikasi Pembayaran + Invoice | ON | ✅ (tombol admin saat invoice Lunas) |
| `selesai_pengerjaan` | Notifikasi Pengerjaan Selesai | ON | ⏳ belum (fase lanjut) |
| `reminder_tagihan` | Pengingat Tagihan | ON | ⏳ belum (fase lanjut) |

> Tips: setelah mengubah Isi Pesan, kirim **Tes Kirim** di menu **Perangkat & Tes WA** (pilih device) untuk melihat hasilnya, lalu cek **Riwayat Kirim WA**.
