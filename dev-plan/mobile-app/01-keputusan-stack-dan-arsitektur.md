# 01 — Keputusan Stack & Arsitektur

## 1. Kondisi saat ini

- Backend **Laravel 12 + Filament 3**. Portal teknisi = halaman **Livewire** mobile-first (lebar maks 480px) di grup route `/teknisi`.
- Logika bisnis sebagian besar sudah di **Service**, tetapi **sebagian masih di komponen Livewire** (lihat `02-...md` bagian Refactor prasyarat).
- **API belum ada**: `routes/api.php` hanya 3 route, tidak ada Sanctum; auth memakai sesi web.
- Kebutuhan perangkat: kamera + QR (absensi), GPS (absen & check-in), unggah foto (banyak, sinyal sering lemah), push notification.

## 2. Pilihan stack

| Opsi | Cocok kalau | Kelebihan | Kekurangan |
|---|---|---|---|
| **A. React Native (Expo) + TypeScript** | Mau app native sungguhan, 1 basis kode | Kamera, QR, GPS, push (FCM), upload background, update OTA (EAS Update) matang; ekosistem & tenaga kerja luas | Perlu JS/TS; build iOS butuh akun Apple Developer |
| B. Flutter | Tim siap belajar Dart | UI konsisten, performa bagus | Bahasa baru; tak ada keunggulan khusus di proyek ini |
| C. PWA / Capacitor membungkus portal Livewire | Mau cepat & hemat | Hampir tanpa kode baru | Push/kamera/GPS latar belakang terbatas (iOS), upload tidak tahan putus-sambung; risiko ditolak toko |
| D. Native Kotlin (Android saja) | Hanya Android & ada developer Kotlin | Akses perangkat terbaik | Dua kali kerja bila butuh iOS |

### Rekomendasi: **A. React Native (Expo) + TypeScript**

1. Semua kebutuhan perangkat punya paket Expo stabil (`expo-camera`/`expo-barcode-scanner`, `expo-location`, `expo-notifications`, `expo-secure-store`, `expo-image-manipulator`, `expo-file-system`).
2. Satu basis kode Android + iOS.
3. Perbaikan kecil via **EAS Update** tanpa menunggu review toko — penting karena aturan client sering berubah.
4. Backend tidak terikat stack: API yang sama bisa dipakai stack apa pun, jadi risiko salah pilih kecil.

**Pengecualian:** bila tidak ada orang yang mau menulis JS/TS dan target hanya cepat tayang → mulai dengan **C (Capacitor)** sebagai Fase 0, API tetap dibangun sama persis, lalu pindah ke A. Tidak ada pekerjaan API yang terbuang.

## 3. Arsitektur

```
App (Expo / React Native)
   │  HTTPS + JSON  (Authorization: Bearer <token Sanctum>)
   ▼
Laravel  routes/api.php  (/api/v1/*)
   │  middleware: auth:sanctum, role teknisi, throttle
   ▼
Controller tipis  ──►  Service yang sudah ada (TeknisiService, AttendanceService, …)
   │
   ▼
API Resource (bentuk JSON)  ──►  Model/DB
```

Pendukung:
- Push: Laravel Notification → FCM/Expo Push (`14-notifikasi-push.md`).
- Foto: unggah per foto bertahap ke endpoint khusus, memakai kuota & pembersihan storage yang sudah ada (`15-upload-foto-dan-offline.md`).
- Penyimpanan lokal app: token (secure store), cache baca jadwal hari ini, antrean unggah (SQLite/file).

## 4. Struktur repo

```
crm_paccing/            (Laravel — repo yang sekarang)
  app/Http/Controllers/Api/V1/*
  app/Http/Resources/*
  routes/api.php
  tests/Feature/Api/*
mobile/                 (folder baru, project Expo, build & dependensi terpisah)
  app/            layar (expo-router)
  src/api/        klien HTTP + tipe respons
  src/features/   absensi, order, keuangan, ...
  src/ui/         komponen & design token (lihat dokumen 20)
```

Alternatif: repo terpisah `crm_paccing_mobile`. Rekomendasi: folder `mobile/` di repo yang sama selama developer-nya sama (kontrak API & tipe mudah disinkronkan); pisah bila tim berbeda.

## 5. Yang sengaja tidak dilakukan

- Tidak menduplikasi aturan bisnis di app (mis. perhitungan gaji, Games, syarat administrasi).
- Tidak mode offline-first penuh (lihat `15-...md`): data baca butuh online; hanya foto & form yang gagal kirim yang diantre.
- Tidak membuat app untuk admin/HR/owner — tetap panel web Filament.
