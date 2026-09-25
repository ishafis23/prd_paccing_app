# Technical Specification - Portal Teknisi Phase 03

**Status:** 📋 SPECIFICATION  
**Version:** 1.0  
**Date:** 2026-09-26

---

## 📦 DATABASE SCHEMA

### 1. order_photos (Modified/New)

```sql
CREATE TABLE order_photos (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    order_id BIGINT UNSIGNED NOT NULL,
    
    -- Photo categorization
    type ENUM('lokasi', 'cuci', 'service') NOT NULL,
    unit_number INT DEFAULT 1 COMMENT '1 untuk single unit, incrementing untuk multiple units',
    photo_position VARCHAR(50) NOT NULL COMMENT 'indoor, outdoor, area_indoor, area_outdoor, suhu, kendala, pengerjaan, selesai, dll',
    
    -- File info
    file_path VARCHAR(255) NOT NULL,
    file_name VARCHAR(255),
    file_size INT,
    mime_type VARCHAR(50),
    
    -- Metadata
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_order_id (order_id),
    INDEX idx_type (type),
    INDEX idx_unit_number (unit_number),
    INDEX idx_status (status),
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. teknis_expenses (New)

```sql
CREATE TABLE teknis_expenses (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    teknisi_id BIGINT UNSIGNED NOT NULL,
    
    -- Expense info
    kategori ENUM('bensin', 'makan', 'material', 'transport', 'lainnya') NOT NULL,
    nominal INT NOT NULL COMMENT 'in IDR',
    keterangan TEXT,
    
    -- Bukti/Receipt (optional)
    bukti_file VARCHAR(255),
    
    -- Approval workflow
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by BIGINT UNSIGNED,
    catatan_approval TEXT,
    
    -- Date info
    tanggal_input DATE NOT NULL,
    tanggal_approve DATETIME,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_teknisi_id (teknisi_id),
    INDEX idx_status (status),
    INDEX idx_tanggal_input (tanggal_input),
    INDEX idx_kategori (kategori),
    
    FOREIGN KEY (teknisi_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. game_2_settings (New - Optional Config)

```sql
CREATE TABLE game_2_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    deadline_time TIME DEFAULT '08:30:00' COMMENT 'Photo upload deadline for Game 2',
    active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default config
INSERT INTO game_2_settings (deadline_time, active) VALUES ('08:30:00', TRUE);
```

---

## 🔌 API ENDPOINTS

### Photo Management

#### `POST /api/orders/{orderId}/photos`
Upload single or multiple photos

**Request:**
```json
{
  "type": "cuci",
  "unit_number": 1,
  "photo_position": "indoor",
  "file": "<binary file>"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "order_id": 456,
    "type": "cuci",
    "unit_number": 1,
    "photo_position": "indoor",
    "file_path": "/storage/photos/order_456/cuci_1_indoor.jpg",
    "created_at": "2026-09-26T10:30:00Z"
  }
}
```

**Validation:**
- File required
- Type must be: lokasi, cuci, service
- Photo position based on type (see mapping below)
- File size: max 5MB
- File type: JPG, PNG only

---

#### `GET /api/orders/{orderId}/photos`
Get all photos for an order organized by type

**Response:**
```json
{
  "success": true,
  "data": {
    "lokasi": [
      {
        "id": 1,
        "photo_position": "lokasi",
        "file_path": "/storage/...",
        "created_at": "2026-09-26T10:30:00Z",
        "required": true,
        "uploaded": true
      }
    ],
    "cuci": [
      {
        "unit_number": 1,
        "photos": [
          {"photo_position": "indoor", "file_path": "...", "uploaded": true},
          {"photo_position": "outdoor", "file_path": "...", "uploaded": false},
          ...
        ],
        "skipped": false
      },
      {
        "unit_number": 2,
        "photos": [...],
        "skipped": false
      }
    ],
    "service": [
      {
        "unit_number": 1,
        "photos": [...],
        "skipped": false
      }
    ],
    "summary": {
      "total_photos_required": 15,
      "total_photos_uploaded": 12,
      "progress_percent": 80,
      "sections_skipped": 0
    }
  }
}
```

---

#### `DELETE /api/orders/{orderId}/photos/{photoId}`
Delete single photo

**Response (204 No Content)** or
```json
{
  "success": true,
  "message": "Photo deleted successfully"
}
```

---

### Expense Management

#### `POST /api/expenses`
Create new expense entry

**Request:**
```json
{
  "tanggal_input": "2026-09-26",
  "kategori": "bensin",
  "nominal": 75000,
  "keterangan": "Bensin perjalanan ke lokasi customer",
  "bukti_file": "<optional binary>"
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "id": 789,
    "teknisi_id": 456,
    "tanggal_input": "2026-09-26",
    "kategori": "bensin",
    "nominal": 75000,
    "keterangan": "Bensin perjalanan ke lokasi customer",
    "status": "pending",
    "created_at": "2026-09-26T10:30:00Z"
  }
}
```

---

#### `GET /api/expenses`
List expenses (authorized user only)

**Query Params:**
```
GET /api/expenses?month=2026-09&status=pending&kategori=bensin
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 789,
      "tanggal_input": "2026-09-26",
      "kategori": "bensin",
      "nominal": 75000,
      "status": "pending",
      "keterangan": "Bensin perjalanan"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total": 25,
    "per_page": 15
  }
}
```

---

#### `PUT /api/expenses/{id}`
Update expense (only if pending)

**Request:**
```json
{
  "nominal": 80000,
  "keterangan": "Updated keterangan"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": { ... }
}
```

---

#### `POST /api/expenses/{id}/approve`
Approve expense (admin only)

**Request:**
```json
{
  "status": "approved",
  "catatan_approval": "Approved for reimbursement"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 789,
    "status": "approved",
    "approved_by": 1,
    "tanggal_approve": "2026-09-26T11:00:00Z",
    "catatan_approval": "Approved for reimbursement"
  }
}
```

---

#### `POST /api/expenses/{id}/reject`
Reject expense (admin only)

**Request:**
```json
{
  "status": "rejected",
  "catatan_approval": "Receipt missing"
}
```

---

#### `GET /api/expenses/summary/daily?date=2026-09-26`
Get daily expense summary

**Response:**
```json
{
  "success": true,
  "data": {
    "date": "2026-09-26",
    "total_pending": 255000,
    "total_approved": 1200000,
    "by_kategori": {
      "bensin": {"pending": 75000, "approved": 500000},
      "makan": {"pending": 50000, "approved": 200000},
      "material": {"pending": 130000, "approved": 500000}
    }
  }
}
```

---

#### `GET /api/expenses/summary/monthly?month=2026-09`
Get monthly expense summary

---

#### `GET /api/settings/game2-deadline`
Get Game 2 deadline configuration

**Response:**
```json
{
  "success": true,
  "data": {
    "deadline_time": "08:30:00",
    "active": true
  }
}
```

---

## 🧩 COMPONENT STRUCTURE

### FotoPerLayanan - Component Hierarchy

```
FotoPerLayanan (parent)
├─ State:
│  ├─ sections: {lokasi, cuci[], service[]}
│  ├─ uploadProgress: {photoId: percent}
│  ├─ errors: []
│  └─ game2Expired: boolean
│
├─ FotoLokasi
│  ├─ Props: value, onChange, error, instruction
│  ├─ PhotoUpload
│  ├─ Checkbox: "Centang Jika Tidak Ada Lokasi"
│  └─ Preview
│
├─ CuciACSection (repeatable)
│  ├─ Props: units: [], onChange, onAddUnit, onSkip
│  ├─ Unit 1
│  │  ├─ 5x PhotoUpload
│  │  ├─ Display: [Cuci Indoor, Cuci Outdoor, Area Indoor, Area Outdoor, Suhu]
│  │  └─ InstructionText per slot
│  ├─ Unit 2, 3, ... (add section)
│  ├─ Checkbox: "Centang Jika Tidak Ada Cuci"
│  └─ Button: "+ Tambah Unit/Layanan"
│
├─ ServiceACSection (repeatable)
│  ├─ Props: units: [], onChange, onAddUnit, onSkip
│  ├─ Unit 1
│  │  ├─ 3x PhotoUpload
│  │  ├─ Display: [Kendala, Pengerjaan, Selesai Service]
│  │  └─ InstructionText per slot
│  ├─ Unit 2, 3, ... (add section)
│  ├─ Checkbox: "Centang Jika Tidak Ada Service"
│  └─ Button: "+ Tambah Unit/Layanan"
│
└─ Summary
   ├─ Progress: x/y photos uploaded
   ├─ Sections completed: 2/3
   └─ Validation errors (if any)
```

---

### Photo Type Mapping

```javascript
// Photo position requirements per type
const PHOTO_REQUIREMENTS = {
  lokasi: {
    positions: ['lokasi'],
    required_count: 1,
    description: 'Foto Lokasi - Tampak Depan Rumah/Kantor'
  },
  cuci: {
    positions: ['indoor', 'outdoor', 'area_indoor', 'area_outdoor', 'suhu'],
    required_count: 5,
    description: 'Cuci AC - 5 foto per unit',
    repeatable: true
  },
  service: {
    positions: ['kendala', 'pengerjaan', 'selesai'],
    required_count: 3,
    description: 'Service AC - 3 foto per unit',
    repeatable: true
  }
};
```

---

### InstructionText - Predefined Instructions

```javascript
const PHOTO_INSTRUCTIONS = {
  'lokasi': '📸 Tampak Depan Rumah/Kantor - tampilan depan secara keseluruhan',
  'cuci_indoor': '🔧 Cuci Indoor - hasil pencucian bagian indoor sudah bersih',
  'cuci_outdoor': '🔧 Cuci Outdoor - hasil pencucian bagian outdoor sudah bersih',
  'cuci_area_indoor': '🏠 Area Cuci Indoor - area kerja saat pencucian indoor',
  'cuci_area_outdoor': '🏢 Area Cuci Outdoor - area kerja saat pencucian outdoor',
  'cuci_suhu': '🌡️ Suhu Indoor - capture layar temperature meter AC',
  'service_kendala': '⚠️ Kendala - foto menunjukkan masalah AC yang ada',
  'service_pengerjaan': '🔧 Pengerjaan - proses service sedang berlangsung',
  'service_selesai': '✅ Selesai Service - AC sudah berfungsi dengan baik',
};
```

---

## 🎮 Game 2 Time Lock Logic

### Backend Validation

```javascript
// In PhotoController.php
public function storePhoto(StorePhotoRequest $request, $orderId)
{
    $order = Order::findOrFail($orderId);
    
    // Check if trying to upload Game 2 photo after deadline
    if ($request->type === 'game2' || $request->photo_position === 'game2_bukti_cuci') {
        $deadline = now()->copy()->setTimeFromTimeString(config('game.game2_deadline', '08:30:00'));
        
        if (now()->greaterThan($deadline)) {
            // After deadline - reject unless override checkbox
            if (!$request->boolean('bypass_time_check')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Game 2 deadline sudah lewat (batas 8:30)'
                ], 422);
            }
        }
    }
    
    // ... rest of upload logic
}
```

### Frontend UI Logic

```tsx
// In Game2Section component
const isGame2Expired = useEffect(() => {
    const deadline = new Date();
    deadline.setHours(8, 30, 0, 0);
    return new Date() >= deadline;
});

return (
    <section className="game2-section">
        <h3>Game 2 - Foto Buka Cover</h3>
        
        <StatusBadge 
            status={isGame2Expired ? 'expired' : 'active'}
            label={isGame2Expired ? 'Game 2 Expired - Batas Jam 8:30' : 'Game 2 Aktif'}
        />
        
        {isGame2Expired && (
            <WarningBox>
                Batas waktu Game 2 sudah terlewat. Anda tidak bisa mendapat bonus untuk photo ini.
            </WarningBox>
        )}
        
        <PhotoUpload 
            disabled={isGame2Expired}
            // ...
        />
        
        <Checkbox 
            label="Centang jika sudah melewati batas waktu Game 2"
            onChange={handleBypassCheck}
        />
    </section>
);
```

---

## 🔐 AUTHORIZATION & PERMISSIONS

### Photo Management
- **Upload Photo:** Technician (for own order) or Admin
- **View Photo:** Technician (own order), Admin (all), Customer (own order)
- **Delete Photo:** Technician (pending only), Admin (any)

### Expense Management
- **Create Expense:** Technician (own)
- **List Expense:** Technician (own), Admin (all)
- **Edit Expense:** Technician (own, pending only)
- **Approve/Reject:** Admin only
- **View Reports:** Admin only

### Implementation (Laravel)
```php
// PhotoPolicy.php
public function upload(User $user, Order $order)
{
    return $user->isAdmin() || $user->id === $order->teknisi_id;
}

// ExpensePolicy.php
public function create(User $user)
{
    return $user->isTechnician();
}

public function approve(User $user, TeknikaExpense $expense)
{
    return $user->isAdmin();
}
```

---

## 🧪 VALIDATION RULES

### Photo Upload
```php
[
    'type' => 'required|in:lokasi,cuci,service',
    'unit_number' => 'required|integer|min:1|max:10',
    'photo_position' => 'required|string',
    'file' => 'required|image|mimes:jpeg,png|max:5120', // 5MB
]
```

### Expense Creation
```php
[
    'tanggal_input' => 'required|date|before_or_equal:today',
    'kategori' => 'required|in:bensin,makan,material,transport,lainnya',
    'nominal' => 'required|integer|min:1000|max:5000000',
    'keterangan' => 'nullable|string|max:255',
    'bukti_file' => 'nullable|image|mimes:jpeg,png|max:5120',
]
```

### Order Submission (with photos)
```php
[
    // Photo validation
    'photos.lokasi.*.file' => 'required_unless:photos.lokasi.*.skipped,true|...',
    'photos.cuci.*.photos.*.file' => 'required_unless:photos.cuci.*.skipped,true|...',
    'photos.service.*.photos.*.file' => 'required_unless:photos.service.*.skipped,true|...',
]
```

---

## 📱 MOBILE CONSIDERATIONS

### Photo Upload on Mobile
- **Progress Indicator:** Show upload % in real-time
- **Offline Support:** Queue uploads if offline, retry when online
- **Camera Integration:** Allow direct camera capture + file picker
- **Compression:** Auto-compress large images before upload
- **Retry Logic:** Auto-retry failed uploads with exponential backoff

### Form Usability
- **Large Touch Targets:** Min 44px button height
- **Vertical Layout:** Single column on mobile
- **Sticky Summary:** Keep progress visible while scrolling
- **Sections:** Collapsible sections to reduce scroll distance

### Network Handling
```tsx
// Auto-upload retry logic
const uploadWithRetry = async (file, maxRetries = 3) => {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
        try {
            return await uploadPhoto(file);
        } catch (error) {
            if (attempt === maxRetries) throw error;
            const delay = Math.pow(2, attempt) * 1000;
            await new Promise(resolve => setTimeout(resolve, delay));
        }
    }
};
```

---

## 🔄 DATA FLOW DIAGRAMS

### Photo Upload Flow
```
Technician         Frontend            Backend         Database
     |                |                   |                |
     | Select Photo   |                   |                |
     |--------------->|                   |                |
     | Validate File  |                   |                |
     |<---------------|                   |                |
     | Show Preview   |                   |                |
     |<---------------|                   |                |
     | Upload         |                   |                |
     |--------------->| POST /photos      |                |
     |                |------------------>|                |
     |                |     Validate      |                |
     |                |<-----+            |                |
     |                | Compress         |                |
     |                |      Save to      |                |
     |                |      Storage      |                |
     |                |     Save to DB    |                |
     |                |                   |-----+          |
     |                |                   |     INSERT     |
     |                |                   |<-----+         |
     |                | Response 201      |                |
     |                |<------------------|                |
     | Success        |                   |                |
     |<---------------|                   |                |
```

### Expense Approval Flow
```
Technician         Frontend            Backend         Database
     |                |                   |                |
     | Input Expense  |                   |                |
     |--------------->|                   |                |
     | Validate       |                   |                |
     |<---------------|                   |                |
     | Submit         |                   |                |
     |--------------->| POST /expenses    |                |
     |                |------------------>|                |
     |                |    Validate       |                |
     |                |     Save          |                |
     |                |                   |-----+          |
     |                |                   |     INSERT     |
     |                |                   |<-----+         |
     |                | Response 201      |                |
     |                |<------------------|                |
     | Success        |                   |                |
     |<---------------|                   |                |
                                                            
Admin                                                      
     |                |                   |                |
     | View Expenses  |                   |                |
     |--------------->| GET /expenses     |                |
     |                |------------------>|                |
     |                |     Query         |                |
     |                |                   |----->|         |
     |                |                   |      SELECT    |
     |                |                   |<-----+         |
     |                | Response 200      |                |
     |                |<------------------|                |
     | Approve        |                   |                |
     |--------------->| POST /approve     |                |
     |                |------------------>|                |
     |                |    Update         |                |
     |                |                   |-----+          |
     |                |                   |     UPDATE     |
     |                |                   |<-----+         |
     |                | Response 200      |                |
     |                |<------------------|                |
     | Success        |                   |                |
     |<---------------|                   |                |
```

---

## 🚨 ERROR HANDLING

### Common Error Scenarios

| Scenario | HTTP Code | Response |
|----------|-----------|----------|
| File too large (>5MB) | 422 | `{success: false, message: "File terlalu besar, max 5MB"}` |
| Invalid file type | 422 | `{success: false, message: "Format file harus JPG atau PNG"}` |
| Upload after deadline (Game 2) | 422 | `{success: false, message: "Game 2 deadline sudah lewat"}` |
| Order not found | 404 | `{success: false, message: "Order tidak ditemukan"}` |
| Photo position invalid | 422 | `{success: false, message: "Posisi foto tidak valid"}` |
| Expense approval failed | 400 | `{success: false, message: "Gagal approve expense"}` |
| Unauthorized | 403 | `{success: false, message: "Anda tidak memiliki akses"}` |
| Validation error | 422 | `{success: false, errors: {...}}` |

---

## 📊 Performance Metrics

### Targets
- **Photo Upload:** < 5s for 5MB file (on 4G)
- **Photo Load:** < 2s for list of 20 photos
- **Expense List:** < 1s for 50+ expenses
- **API Response:** < 500ms (p95)

### Optimization Strategies
- Lazy load photo thumbnails
- Paginate expense list (15 items per page)
- Index DB queries on: order_id, type, unit_number, status
- Cache config values (Game 2 deadline, categories)
- Compress images on upload

---

## 📋 TESTING CHECKLIST

### Unit Tests
- [ ] Photo model relationships
- [ ] Expense calculation logic
- [ ] Time-based validation (Game 2)
- [ ] Authorization policies

### Integration Tests
- [ ] Upload photo flow end-to-end
- [ ] Create & approve expense flow
- [ ] Multiple units scenario
- [ ] Skip section scenario

### E2E Tests (Cypress/Playwright)
- [ ] Complete order with all photos
- [ ] Expense creation & approval
- [ ] Game 2 time lock behavior
- [ ] Mobile upload flow

### Performance Tests
- [ ] Load test photo upload (concurrent)
- [ ] Load test expense list (large dataset)
- [ ] Image optimization validation

---

**End of Technical Specification**

Next: Review with team and proceed to implementation Phase 1.
