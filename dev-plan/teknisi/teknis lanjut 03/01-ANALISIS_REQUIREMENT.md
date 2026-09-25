# Analisis Requirement Lanjutan Portal Teknisi - Phase 03

**Tanggal Analisis:** 2026-09-26  
**Sumber:** LIST Portal Teknisi (terbaru).pdf  
**Status:** Requirements Mapping & Prioritization

---

## 📋 RINGKASAN REQUIREMENT

Customer memberikan 6 requirement utama untuk portal teknisi fase lanjutan, dengan 2 item sudah completed dan 4 item baru yang perlu dikerjakan.

---

## ✅ COMPLETED (Fase Sebelumnya)

### 1. Riwayat Order Terpisah Harian
- **Deskripsi:** Riwayat order dapat dipisahkan sesuai tanggal, tidak tercampur
- **Implementation:** Filter/dropdown untuk Select Nama, Tanggal, Pelayanan
- **Status:** ✅ DONE (dari commit: feat: filter Riwayat Pengerjaan teknisi)
- **Component Impact:** Riwayat Pengerjaan page

### 2. Tambah Layanan untuk Order Selesai
- **Deskripsi:** Teknisi dapat mengajukan tambah layanan meski order sudah diselesaikan
- **Implementation:** Allow request tambah layanan dengan status "Selesai", dengan warning
- **Status:** ✅ DONE (dari commit: feat: izinkan Tambah Layanan utk order Selesai)
- **Component Impact:** Order detail, Teknisi action flow

---

## 🆕 NEW REQUIREMENTS (Priority)

### 3. Menu Pendapatan/Pengeluaran (EXPENSE TRACKING)

**Priority:** 🔴 HIGH  
**Complexity:** MEDIUM

#### Problem Statement
- Admin perlu tracking pengeluaran teknisi (biaya bahan, transport, dll)
- Teknisi harus bisa input laporan cash/pengeluaran harian
- Admin portal perlu akses monitoring & edit biaya
- Guna menghitung pengeluaran untuk financial reporting

#### Requirements Detail
- **Teknisi Portal (new menu):**
  - "Laporan Pengeluaran" / "Pendapatan" menu di navigation
  - Form input: Tanggal, Kategori (Bensin, Makan, Material, dll), Nominal, Keterangan
  - Display total cash hari ini vs pending
  - View riwayat pengeluaran bulanan
  - Submit untuk approval admin

- **Admin Portal (new feature):**
  - Dashboard view semua pengeluaran teknisi
  - Filter by: Teknisi, Tanggal, Kategori, Status (pending/approved)
  - Action: Approve, Reject, Edit nominal
  - Export laporan pengeluaran

#### DB Tables Required
- `teknis_expenses` - track semua pengeluaran
- `expense_categories` - kategori pengeluaran

#### Connected Systems
- Admin portal untuk monitoring & approval
- Financial reporting / Dashboard

---

### 4. Game 2 - Upload Foto Tidak Wajib (TIME-BASED LOCK)

**Priority:** 🟠 MEDIUM  
**Complexity:** LOW

#### Problem Statement
- Game 2 memiliki bonus jika upload foto sebelum jam 8:30
- Khawatir teknisi upload foto setelah batas waktu tapi dihitung dapat bonus
- Perlu mechanism untuk prevent/lock upload setelah deadline

#### Requirements Detail
- **Visual Indicator:**
  - Checkbox "Centang Jika Sudah Melewati Batas Waktu Game 2"
  - Status label: "Game 2 Aktif" / "Game 2 Expired (lewat 8:30)"

- **Behavior:**
  - Jika system time >= 08:30, disable/lock upload foto untuk Game 2
  - Alternatif: Show warning "Batas waktu Game 2 sudah terlewat"
  - Tetap allow teknisi untuk centang checkbox (bypass untuk edge case)

- **Configuration:**
  - Hardcoded: Batas jam 08:30 (atau configurable di admin)
  - Per-order basis (cek based on order date, bukan current date)

#### Files to Modify
- Detail order component (Game 2 section)
- Photo upload handler
- Order submission validation

---

### 5. Detail Pelaksanaan Order - Foto Per Layanan Terstruktur

**Priority:** 🔴 HIGH  
**Complexity:** HIGH

#### Problem Statement
- Struktur upload foto saat ini tidak terorganisir dengan baik
- Perlu terstruktur berdasarkan tipe layanan & unit
- Support multiple units dengan "add section" dynamic

#### Current State
- Upload foto tercampur dalam 1 section
- Tidak ada pembedaan antara cuci vs service
- Tidak support multiple units dengan jelas

#### Target Structure

```
📸 FOTO PER LAYANAN

A. FOTO LOKASI (Tampak Depan Rumah/Kantor)
   └─ Upload: 1 foto
   └─ Checkbox: [☐] Centang Jika Tidak Ada Lokasi

B. CUCI AC
   ├─ Unit 1:
   │  ├─ Upload 5 Foto:
   │     1. Cuci Indoor
   │     2. Cuci Outdoor
   │     3. Area Cuci (Indoor)
   │     4. Area Cuci (Outdoor)
   │     5. Foto Suhu Usai Cuci (Indoor)
   │  └─ Checkbox: [☐] Centang Jika Tidak Ada Cuci
   │
   ├─ Unit 2: (add section)
   │  └─ Format sama dengan Unit 1
   │
   └─ [+ Tambah Unit/Layanan] button

C. SERVICE AC
   ├─ Unit 1:
   │  ├─ Upload 3 Foto:
   │     1. Foto Kendala
   │     2. Foto Pengerjaan
   │     3. Foto Selesai Service
   │  └─ Checkbox: [☐] Centang Jika Tidak Ada Service
   │
   ├─ Unit 2: (add section)
   │  └─ Format sama dengan Unit 1
   │
   └─ [+ Tambah Unit/Layanan] button
```

#### Implementation Notes
- **Dynamic Sections:** Use React component with array state untuk manage multiple units
- **Conditional Rendering:** Show/hide section based on checkbox state
- **Photo Upload:** Max 5MB per foto, support JPG/PNG
- **Validation:** Enforce required photos hanya jika section tidak di-skip
- **Instruction Display:** Tampilkan instruksi dalam blue text (bukan deskripsi label)

#### DB Schema
- `order_photos` table dengan struktur:
  - `type`: 'lokasi' | 'cuci' | 'service'
  - `unit_number`: integer (untuk cuci/service multiple units)
  - `photo_position`: lokasi | indoor | outdoor | area_indoor | area_outdoor | suhu | kendala | pengerjaan | selesai
  - `file_path`: storage path
  - `uploaded_at`: timestamp

#### Files to Create/Modify
- New: `components/FotoPerLayanan.tsx` (main component)
- New: `components/sections/FotoLokasi.tsx`
- New: `components/sections/CuciACSection.tsx`
- New: `components/sections/ServiceACSection.tsx`
- Modify: Order detail page layout
- Modify: Photo upload API endpoint

---

### 6. Instruksi Foto Lebih Jelas (UX Improvement)

**Priority:** 🟠 MEDIUM  
**Complexity:** LOW

#### Problem Statement
- Deskripsi teknis tidak mudah dipahami teknisi
- Perlu instruksi yang lebih visual & jelas

#### Requirements Detail
- **Replace teknis deskripsi dengan:**
  - Blue text (color: #0066CC) untuk instruksi
  - Short & actionable language
  - Emoji/icon untuk visual hint

- **Example:**
  ```
  ❌ Before: "Upload foto dengan kualitas HD, format JPG/PNG, max 5MB, 
             menunjukkan kondisi unit sebelum & sesudah pekerjaan"
  
  ✅ After: "📸 Foto Indoor - cuci bagian indoor (kondisinya bersih)"
  ```

- **Instruction Template:**
  ```
  🎯 [Emoji] [Action] - [Result]
  ```

---

## 📊 IMPLEMENTATION ROADMAP

### Phase 1 (Priority: HIGH)
- [ ] Requirement #5: Foto Per Layanan Terstruktur
- [ ] Requirement #3: Menu Pendapatan/Pengeluaran

### Phase 2 (Priority: MEDIUM)
- [ ] Requirement #4: Game 2 Upload Photo Lock
- [ ] Requirement #6: Instruksi Foto Lebih Jelas

---

## 🔄 DEPENDENCIES & IMPACTS

| Requirement | Dependent On | Impacts | Risk Level |
|------------|-------------|---------|-----------|
| #3 Expense | DB Schema | Admin Portal | MEDIUM |
| #4 Game 2 | Order Model | Validation Logic | LOW |
| #5 Foto | Storage, Upload Handler | Multiple Pages | HIGH |
| #6 Instructions | Requirement #5 | UX/Copy | LOW |

---

## 💾 Database Changes Required

```sql
-- New table for expense tracking
CREATE TABLE teknis_expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    teknisi_id INT NOT NULL,
    kategori VARCHAR(50),
    nominal INT NOT NULL,
    keterangan VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    tanggal_input DATETIME DEFAULT CURRENT_TIMESTAMP,
    tanggal_approve DATETIME,
    FOREIGN KEY (teknisi_id) REFERENCES users(id)
);

-- Modify orders table to support new photo structure
ALTER TABLE order_photos ADD COLUMN (
    type ENUM('lokasi', 'cuci', 'service'),
    unit_number INT DEFAULT 1,
    photo_position VARCHAR(50)
);
```

---

## ✨ Success Criteria

- ✅ Foto per layanan terstruktur sesuai spec dan support multiple units
- ✅ Teknisi dapat input pengeluaran dan admin dapat approve
- ✅ Game 2 photo upload lock berdasarkan waktu
- ✅ Instruksi foto jelas dan mudah dipahami
- ✅ Semua fitur terintegrasi tanpa break existing functionality
- ✅ Mobile responsive (teknisi use mobile mostly)

---

## 🎯 Next Steps

1. Breakdown requirement #5 (Foto) ke technical spec detailed
2. Design DB schema untuk requirement #3 (Expense)
3. Create component mockups untuk review
4. Start implementation Phase 1
