# 14 — Notifikasi Push

Saat ini server hanya mengirim **WhatsApp ke customer** (`NotifikasiWaService`, `WhatsappService`); belum ada notifikasi ke teknisi. Dokumen ini mendefinisikan yang baru.

## 1. Teknologi

- Expo Notifications di app; pengiriman dari Laravel via **FCM** (Android) — langsung atau lewat Expo Push Service (lebih sederhana; pilih Expo Push di Fase 1, FCM langsung bila perlu kontrol penuh).
- Tabel baru `device_tokens`: `id`, `user_id`, `token` (unik), `platform`, `app_version`, `last_seen_at`, timestamps.
- Laravel `Notification` dengan channel kustom `ExpoPush`/`Fcm`; antrean (`queue`) agar kirim tidak menahan request.

## 2. Endpoint

| Method & path | Catatan |
|---|---|
| `POST /perangkat` | daftarkan/perbarui token (dipanggil setelah login & saat token berganti) |
| `DELETE /perangkat/{token}` | dipanggil saat logout |
| `GET/PUT /notifikasi/pengaturan` | (Fase 2) pilih jenis yang aktif |

## 3. Jenis notifikasi (usulan — konfirmasi ke client)

| Pemicu | Isi | Fase |
|---|---|---|
| Order dijadwalkan/dipindah/dibatalkan oleh admin | "Order #443 CK Pengayoman dijadwalkan 26 Okt 13:00" | 1 |
| Ditambahkan sebagai anggota tim order | ... | 1 |
| Pengingat pengeluaran belum diisi (hari absen kemarin) | "Catatan pengeluaran 30 Okt belum diisi" — jam tetap pagi via scheduler | 1 |
| Penghalang administrasi baru muncul | ringkas | 2 |
| Pulang cepat disetujui/ditolak | status | 2 |
| Setoran diverifikasi/ditolak | status | 2 |
| Pengeluaran disetujui/ditolak/dikembalikan ke menunggu | status | 2 |
| Slip gaji tersedia | ... | 2 |

Pengiriman dipicu dari titik yang sudah ada (observer/service saat admin mengubah order, scheduler harian untuk pengingat — `routes/console.php`, pola `Schedule::command(...)`).

## 4. Aturan

- Isi notifikasi **tanpa data sensitif** (tidak ada nominal gaji); detail dimuat setelah dibuka.
- Ketuk notifikasi membuka layar terkait (deep link, mis. `/order/443`).
- Token yang ditolak FCM (tidak valid) dihapus otomatis.
- Satu pengguna bisa punya banyak perangkat; kirim ke semua token aktif.
- Notifikasi bersifat tambahan: alur kerja tidak boleh bergantung padanya (banner administrasi tetap sumber utama).

## 5. Pengujian

Tes dengan channel palsu: pemicu menghasilkan notifikasi ke token yang benar, token tidak valid dibersihkan, logout menghapus token, pengingat harian hanya untuk teknisi yang memang punya hari absen tanpa catatan.
