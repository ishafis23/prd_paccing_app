# Dev Plan — One Gate System Paccing

Folder ini berisi dokumen perencanaan teknis (development plan) yang diturunkan dari konsep di [`../PRD.md`](../PRD.md).

`PRD.md` menjelaskan **apa** yang dibangun dan **kenapa** (konsep, modul, keputusan tech stack).
Folder ini menjelaskan **bagaimana & kapan** dibangun — breakdown tugas, urutan pengerjaan, dan detail teknis per tahap.

## Struktur

```
dev-plan/
├── 00-overview.md              ← urutan pengerjaan & dependency lintas modul
├── 02-keputusan-eksekusi.md    ← jawaban final semua pertanyaan terbuka (acuan implementasi)
├── 03-tech-setup.md            ← langkah setup Laravel, environment, konvensi kode
├── database-schema.md          ← skema tabel & relasi (satu sumber kebenaran, lintas modul)
├── admin/01-konsep-admin.md    ← konsep, alur, checklist modul Admin
├── teknisi/01-konsep-teknisi.md
├── hrd/01-konsep-hrd.md
└── finance/01-konsep-finance.md
```

Konsep & fitur dipecah **per folder modul** (Admin, Teknisi, HRD, Finance) supaya bisa dibahas terpisah dengan client per bagian. Skema database tetap **satu file gabungan** karena tabel-tabelnya saling terhubung lintas modul (mis. `orders` disentuh Admin, Teknisi, dan Finance sekaligus) — alasannya dijelaskan di `00-overview.md`.

`03-tech-setup.md` (langkah setup project Laravel, environment, konvensi kode) menyusul setelah keputusan hosting/server difinalkan bersama client.

## Referensi

- Konsep & keputusan tech stack: [`../PRD.md`](../PRD.md)
- Sumber konsep awal: mind map client "One Gate System Paccing" + profil bisnis Instagram @paccingofficial
