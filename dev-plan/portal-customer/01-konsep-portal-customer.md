# Konsep Modul — Portal Customer (Corporate)

> STATUS: **✅ SELESAI DIIMPLEMENTASIKAN** (dev-plan/12 §3.6) — semua
> ketergantungan teknis (§3.4 order multi-unit, §3.10 penautan Unit AC)
> sudah selesai duluan sesuai rencana di dokumen ini. Keputusan multi-user
> (§4 poin 1) sudah dijawab final: **1 akun per customer** (bukan
> multi-user staf) — lihat §4 (update) utk detail implementasi. Sumber:
> diskusi WA 13 Sept 2026, lihat
> [`../12-analisis-chat-13sep-dan-roadmap.md`](../12-analisis-chat-13sep-dan-roadmap.md)
> §3.6.

## 1. Ringkasan & Tujuan

Halaman khusus untuk **customer company** (bukan perorangan) yang punya
banyak unit AC (sekolah, kantor, dll) untuk memantau:
1. **Riwayat pencucian per unit AC** — kapan terakhir dicuci, siapa
   teknisinya, catatan kondisi.
2. **Progress/jadwal berikutnya** — kapan unit tsb dijadwalkan dicuci
   lagi, dan status order yang sedang berjalan (kalau ada).

Nilai plus ke klien corporate: transparansi & bukti kerja terdokumentasi
per unit, bukan cuma per invoice/order.

Fondasi data sudah ada sebagian: `CustomerAcUnit` (master data unit AC,
lihat §3.10) dan `ServiceReminder` (jadwal servis berikutnya, per
customer — belum per unit).

## 2. Fitur (usulan awal)

1. **Daftar Unit AC customer** — dari `CustomerAcUnit`: kode unit,
   ruangan, jenis, PK.
2. **Riwayat pencucian per unit** — **BELUM ada datanya**: `WorkReport`
   sekarang tersambung ke `Order`, bukan ke unit AC spesifik. Perlu
   kolom penghubung (`work_reports.customer_ac_unit_id` atau tabel
   pivot) supaya laporan teknisi bisa ditandai "ini untuk unit AC yang
   mana" saat order melibatkan banyak unit sekaligus.
3. **Jadwal berikutnya per unit** — `ServiceReminder` sekarang per
   **customer** (satu tanggal utk semua), bukan per unit. Perlu
   diperluas jadi per-unit (`customer_ac_unit_id` nullable di
   `service_reminders`, atau tabel baru) supaya tiap ruangan bisa beda
   jadwal (mis. lantai 1 baru dicuci bulan lalu, lantai 2 belum).
4. **Status order berjalan** — kalau ada order aktif yg mencakup unit
   tsb, tampilkan status ringkas (terjadwal/menuju lokasi/dikerjakan).

## 3. Ketergantungan ke fitur lain

- §3.4 (Import Excel dispatch massal) & alur input order corporate multi-unit
  — perlu ada dulu supaya satu Order bisa mencakup banyak `CustomerAcUnit`
  sekaligus (baru dari situ riwayat per-unit bisa tercatat otomatis).
- §3.8 (foto laporan per kategori) — kalau foto laporan juga mau
  ditandai per-unit (bukan cuma per-order), portal ini butuh itu juga.

**Konsekuensi**: portal ini secara teknis **tidak bisa dibangun penuh**
sebelum alur order multi-unit (§3.4) ada — kalau dipaksa sebelum itu,
"riwayat per unit" isinya akan kosong/tidak akurat karena laporan
teknisi belum tertaut ke unit mana pun.

## 4. Keputusan (final, 13 Sept 2026 — update setelah §3.4 selesai)

1. **Cara akses/login customer**: **Opsi B — akun login sungguhan**
   (email+password), khusus PIC customer corporate, terpisah dari akun
   Admin/Teknisi. Bukan token-link spt resi. **✅ diimplementasikan.**
   - **Multi-user vs 1 akun — dijawab final: 1 akun per customer**
     (bukan multi-user staf). Kolom login (`password`, hashed) ditaruh
     langsung di tabel `customers` yg sudah ada, pakai `customers.email`
     sbg username — tidak perlu tabel `customer_users` terpisah.
   - Guard Laravel baru `customer` (`config/auth.php`, provider
     `customers` → model `Customer` yg skrg implement
     `Authenticatable`). Halaman login terpisah `/portal/login`
     (`App\Livewire\Portal\Login`), dashboard `/portal`
     (`App\Livewire\Portal\Dashboard`), logout `/portal/logout`. Tamu ke
     `/portal/*` diarahkan ke `/portal/login` (bukan `/login`
     admin/teknisi) via `redirectGuestsTo` di `bootstrap/app.php`.
   - Admin yg mengelola aktivasi (bukan self-service registrasi
     customer): aksi "Atur Password Portal" (set/reset password, wajib
     customer punya email dulu, validasi email tak dipakai customer lain
     yg jg py akses portal) & "Cabut Akses Portal" (`password` diset
     `null` lagi) di `CustomerResource` (`CustomerPortalService`).
   - Alur lupa-password self-service **belum ada** (di luar scope
     awal) — kalau customer lupa password, admin reset manual lewat
     aksi di atas.
2. **Ruang lingkup "progress"**: diimplementasikan sbg dashboard berisi
   daftar Unit AC customer + kapan/siapa teknisi yg terakhir
   mengerjakan tiap unit (`CustomerAcUnit::latestOrderItem()`) + notice
   jadwal servis berikutnya (`ServiceReminder` terbaru milik customer).
   Status order yg SEDANG berjalan (real-time) belum ditampilkan
   terpisah — bisa disimpulkan dari histori kalau order barusan belum
   `selesai`, tapi tidak ada indikator status eksplisit di dashboard ini.
3. **Timing pembangunan**: dikerjakan setelah §3.4 (order multi-unit)
   selesai, sesuai rencana. **✅ selesai** — 14 test baru
   (`tests/Feature/CustomerPortalTest.php`).
