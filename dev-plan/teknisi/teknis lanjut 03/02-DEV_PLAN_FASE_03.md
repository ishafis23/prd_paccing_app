# Development Plan - Portal Teknisi Phase 03

**Timeline:** 2026-09-26 - TBD  
**Status:** 📋 PLANNED  
**Owner:** Development Team

---

## 🎯 OBJECTIVE

Implement 4 new features untuk Portal Teknisi Phase 3:
1. Struktur Foto Per Layanan yang terorganisir & support multiple units
2. Menu Pendapatan/Pengeluaran dengan approval workflow
3. Game 2 Photo Upload Time-based Lock
4. Improve instruksi foto untuk better UX

---

## 📅 PHASE BREAKDOWN

### PHASE 1: CORE FEATURES (WEEK 1-2)
**Focus:** High-impact, foundational features

#### Task 1.1: Foto Per Layanan - Backend Setup
**Story:** As a developer, I need to restructure photo storage to support organized photo sections per service type and multiple units

**Sub-tasks:**
- [ ] 1.1.1 Design & create `order_photos` table schema
  - Type: lokasi, cuci, service
  - Unit number support (for multiple AC units)
  - Photo position field
  - Status & timestamps
  
- [ ] 1.1.2 Create migration script
  - Backup existing photos
  - Create new table
  - Migrate existing data if needed
  
- [ ] 1.1.3 Create/modify API endpoints
  - `POST /api/orders/:orderId/photos` - upload photo with type & position
  - `GET /api/orders/:orderId/photos` - get all photos organized
  - `DELETE /api/orders/:orderId/photos/:photoId` - delete photo
  - `PUT /api/orders/:orderId/photos/:photoId` - update photo metadata

**Acceptance Criteria:**
- ✅ New schema supports lokasi, cuci (multi-unit), service (multi-unit)
- ✅ API endpoints working & tested
- ✅ No data loss from existing photos
- ✅ API response structure documented

**Files:**
- `database/migrations/xxxxx_create_order_photos_v2.php`
- `app/Http/Controllers/PhotoController.php`
- `app/Models/OrderPhoto.php` (new model)

---

#### Task 1.2: Foto Per Layanan - Frontend Component
**Story:** As a technician, I need to upload photos organized by service type and unit number with clear instructions

**Sub-tasks:**
- [ ] 1.2.1 Create main component structure
  ```
  src/components/FotoPerLayanan/
  ├── FotoPerLayanan.tsx (parent, manages state)
  ├── FotoLokasi.tsx (single location photo)
  ├── CuciACSection.tsx (5 photos per unit + add section)
  ├── ServiceACSection.tsx (3 photos per unit + add section)
  ├── PhotoUpload.tsx (reusable upload component)
  └── InstructionText.tsx (blue text instructions)
  ```

- [ ] 1.2.2 Implement FotoLokasi component
  - Single photo upload for location
  - Checkbox: "Centang Jika Tidak Ada Lokasi"
  - Preview & delete functionality
  - Instruction: "📸 Tampak Depan Rumah/Kantor"

- [ ] 1.2.3 Implement CuciACSection component
  - Dynamic unit sections (Unit 1, Unit 2, etc)
  - 5 photo slots per unit:
    1. Cuci Indoor
    2. Cuci Outdoor
    3. Area Cuci (Indoor)
    4. Area Cuci (Outdoor)
    5. Foto Suhu Usai Cuci
  - "Add Unit" button untuk tambah unit baru
  - Checkbox: "Centang Jika Tidak Ada Cuci"
  - Each slot punya blue text instruction

- [ ] 1.2.4 Implement ServiceACSection component
  - Dynamic unit sections (Unit 1, Unit 2, etc)
  - 3 photo slots per unit:
    1. Foto Kendala
    2. Foto Pengerjaan
    3. Foto Selesai Service
  - "Add Unit" button
  - Checkbox: "Centang Jika Tidak Ada Service"
  - Blue text instructions per slot

- [ ] 1.2.5 Implement PhotoUpload component (reusable)
  - Drag-drop file upload
  - File validation (JPG/PNG, max 5MB)
  - Preview thumbnail
  - Upload progress indicator
  - Error handling & retry

- [ ] 1.2.6 Implement InstructionText component
  - Color: #0066CC (blue)
  - Size: 12px / 0.75rem
  - Format: 🎯 [Action] - [Expected Result]
  - Examples:
    - "📸 Cuci Indoor - bagian indoor bersih setelah dicuci"
    - "🌡️ Suhu Indoor - captura layar temperature display"

- [ ] 1.2.7 State management & validation
  - Track completed sections
  - Skip validation if checkbox checked
  - Warn if required photos missing before submit
  - Show progress indicator (6/15 photos uploaded)

**Acceptance Criteria:**
- ✅ All 3 sections render correctly
- ✅ Add unit button works & creates new section
- ✅ Checkbox skip works (no validation on skipped)
- ✅ Photo upload with preview works
- ✅ Mobile responsive design
- ✅ Instructions clear & consistent

**Files:**
- `resources/js/components/FotoPerLayanan/FotoPerLayanan.tsx` (new)
- `resources/js/components/FotoPerLayanan/FotoLokasi.tsx` (new)
- `resources/js/components/FotoPerLayanan/CuciACSection.tsx` (new)
- `resources/js/components/FotoPerLayanan/ServiceACSection.tsx` (new)
- `resources/js/components/FotoPerLayanan/PhotoUpload.tsx` (new)
- `resources/js/components/FotoPerLayanan/InstructionText.tsx` (new)

**Dependencies:**
- Task 1.1 (API endpoints)

---

#### Task 1.3: Order Detail Page - Integrate Foto Component
**Story:** As a technician, I can see & use new structured photo upload when editing order details

**Sub-tasks:**
- [ ] 1.3.1 Replace old photo upload section with new FotoPerLayanan component
  - Remove old photo UI
  - Add new component to order detail flow
  - Maintain form submission logic

- [ ] 1.3.2 Update order submission validation
  - Validate photos based on section checkboxes
  - Ensure required photos present if not skipped
  - Show validation errors clearly

- [ ] 1.3.3 Test full flow
  - Create new order
  - Add photos to all sections
  - Submit & verify in DB
  - Edit order & modify photos

**Acceptance Criteria:**
- ✅ Old photo UI completely replaced
- ✅ Form submit works & saves photos correctly
- ✅ Can edit existing order photos
- ✅ Validation prevents incomplete submissions

**Files:**
- `resources/views/orders/detail.blade.php` (modify)
- `resources/js/pages/OrderDetail.tsx` (modify)

**Dependencies:**
- Task 1.2 (Component)

---

#### Task 1.4: Pendapatan/Pengeluaran - Backend Setup
**Story:** As a developer, I need to create expense tracking system with approval workflow

**Sub-tasks:**
- [ ] 1.4.1 Design & create database schema
  ```sql
  teknis_expenses:
  - id (PK)
  - teknisi_id (FK to users)
  - kategori (enum: bensin, makan, material, transport, lainnya)
  - nominal (int)
  - keterangan (text)
  - status (enum: pending, approved, rejected)
  - bukti_file (nullable, image path)
  - tanggal_input (datetime)
  - tanggal_approve (nullable datetime)
  - approved_by (nullable FK to users)
  - catatan_approval (nullable text)
  ```

- [ ] 1.4.2 Create migration & model
  - `TeknikaExpense.php` model
  - Relations: belongsTo(User), teknisi
  - Scopes: pending(), approved(), rejected(), byTeknisi()
  
- [ ] 1.4.3 Create API endpoints
  - `POST /api/expenses` - create expense
  - `GET /api/expenses` - list (admin: all, teknisi: own)
  - `GET /api/expenses/:id` - detail
  - `PUT /api/expenses/:id` - update (teknisi only before approved)
  - `DELETE /api/expenses/:id` - delete
  - `POST /api/expenses/:id/approve` - admin approve
  - `POST /api/expenses/:id/reject` - admin reject
  - `GET /api/expenses/summary/daily` - daily summary
  - `GET /api/expenses/summary/monthly` - monthly summary

**Acceptance Criteria:**
- ✅ Schema created & migrations work
- ✅ All endpoints tested & documented
- ✅ Proper authorization (teknisi can only edit own)
- ✅ Status workflow working

**Files:**
- `database/migrations/xxxxx_create_teknis_expenses_table.php` (new)
- `app/Models/TeknikaExpense.php` (new)
- `app/Http/Controllers/ExpenseController.php` (new)

---

### PHASE 2: FEATURES & POLISH (WEEK 3)
**Focus:** Complete features + refinement

#### Task 2.1: Pendapatan/Pengeluaran - Teknisi Portal UI
**Story:** As a technician, I can log daily expenses and track cash status

**Sub-tasks:**
- [ ] 2.1.1 Create Expense Input page/modal
  ```
  Form fields:
  - Tanggal (date picker)
  - Kategori (dropdown: bensin, makan, material, transport, lainnya)
  - Nominal (currency input)
  - Keterangan (textarea)
  - [Optional] Upload bukti (photo/receipt)
  - Submit button
  ```

- [ ] 2.1.2 Create Dashboard section in "Laporan" menu
  - Today's cash summary
    - Total input today
    - Status: [x] items pending, [y] items approved
  - Daily breakdown (accordion)
    - Per-day summary view
    - Item list with status badge
  - Monthly summary chart
    - Category breakdown (pie/bar chart)
    - Total by category

- [ ] 2.1.3 Create Expense list/history view
  - Table: Tanggal, Kategori, Nominal, Keterangan, Status
  - Filters: Date range, Category, Status
  - Action: Edit (if pending), Delete (if pending), View detail
  - Export to CSV/Excel

- [ ] 2.1.4 Create Expense detail page
  - All fields readonly after submitted (unless rejected)
  - Show approval notes if rejected
  - Show approver name & date if approved

**Acceptance Criteria:**
- ✅ Can create new expense
- ✅ Dashboard shows correct data
- ✅ Can view history & filter
- ✅ Cannot edit after approved
- ✅ Mobile responsive

**Files:**
- `resources/js/pages/Expenses/ExpenseInput.tsx` (new)
- `resources/js/pages/Expenses/ExpenseDashboard.tsx` (new)
- `resources/js/pages/Expenses/ExpenseList.tsx` (new)
- `resources/js/pages/Expenses/ExpenseDetail.tsx` (new)
- `resources/js/components/ExpenseForm.tsx` (new)

**Dependencies:**
- Task 1.4 (API)

---

#### Task 2.2: Pendapatan/Pengeluaran - Admin Portal UI
**Story:** As an admin, I can monitor and approve technician expenses

**Sub-tasks:**
- [ ] 2.2.1 Create Expense Management dashboard in admin
  - High-level summary
    - Total pending (today, this month)
    - Total approved (this month)
    - By technician breakdown

- [ ] 2.2.2 Create Expense approval list
  - Table: Teknisi, Tanggal, Kategori, Nominal, Status
  - Filter: Teknisi, Date range, Category, Status
  - Batch action: Approve selected, Reject selected
  - Inline action: View detail, Edit, Approve, Reject

- [ ] 2.2.3 Create approval detail modal
  - Show expense info
  - Edit nominal if needed
  - Approval form:
    - Status dropdown (approve/reject)
    - Catatan approval (text)
    - Submit button

- [ ] 2.2.4 Create reports
  - Daily expense report (by technician)
  - Category breakdown report
  - Monthly summary report
  - Export to Excel/PDF

**Acceptance Criteria:**
- ✅ Admin can see all pending expenses
- ✅ Can approve/reject with notes
- ✅ Can edit nominal before approval
- ✅ Reports available & exportable
- ✅ Real-time status update

**Files:**
- `resources/js/pages/Admin/Expenses/Dashboard.tsx` (new)
- `resources/js/pages/Admin/Expenses/ApprovalList.tsx` (new)
- `resources/js/pages/Admin/Expenses/ApprovalModal.tsx` (new)
- `resources/js/pages/Admin/Reports/ExpenseReport.tsx` (new)

**Dependencies:**
- Task 1.4 (API)

---

#### Task 2.3: Game 2 Photo Upload Lock
**Story:** As a system, I prevent technician from uploading Game 2 photos after 08:30 deadline

**Sub-tasks:**
- [ ] 2.3.1 Add time-based validation logic
  - In FotoPerLayanan component, detect order date
  - Check if current time >= 08:30 on order date
  - If yes, set Game2 section as "expired"

- [ ] 2.3.2 Update Game 2 UI
  - Show status: "Game 2 Aktif" (green) or "Game 2 Expired - Batas Jam 8:30" (red/gray)
  - If expired:
    - Disable upload button
    - Show explanation message
    - Checkbox: "Centang jika sudah lewat batas waktu"
  - Allow checkbox even if expired (for edge case)

- [ ] 2.3.3 Backend validation
  - On photo submit, verify time constraint
  - Reject if time > 08:30 & photo marked as Game 2

- [ ] 2.3.4 Configuration
  - Game 2 deadline time: configurable in admin settings
  - Default: 08:30
  - Per-order configuration option

**Acceptance Criteria:**
- ✅ After 08:30, Game 2 section is locked
- ✅ Cannot upload Game 2 photos after deadline
- ✅ Can still checkbox if needed (override)
- ✅ Backend validation prevents invalid submissions
- ✅ Clear messaging to user

**Files:**
- `resources/js/components/FotoPerLayanan/Game2Section.tsx` (new)
- `app/Http/Controllers/PhotoController.php` (modify)
- `app/Models/Order.php` (add method: isGame2Expired())

---

#### Task 2.4: Instruksi Foto - Blue Text & Icons
**Story:** As a technician, I understand photo upload instructions clearly with visual cues

**Sub-tasks:**
- [ ] 2.4.1 Replace all photo descriptions with blue-text instructions
  - Create instruction templates:
    ```
    📸 [Photo Type] - [What should be visible]
    Example: "📸 Cuci Indoor - bagian indoor AC sudah bersih"
    ```

- [ ] 2.4.2 Update all sections with consistent instruction format
  - FotoLokasi:
    - "📸 Tampak Depan Rumah/Kantor - tampilan depan secara keseluruhan"
  - CuciACSection:
    - "🔧 Cuci Indoor - hasil pencucian indoor sudah bersih"
    - "🔧 Cuci Outdoor - hasil pencucian outdoor sudah bersih"
    - "🏠 Area Cuci Indoor - area kerja indoor ditampilkan"
    - "🏢 Area Cuci Outdoor - area kerja outdoor ditampilkan"
    - "🌡️ Suhu Indoor - capture layar temperature meter"
  - ServiceACSection:
    - "⚠️ Kendala - foto menunjukkan masalah yang ada"
    - "🔧 Pengerjaan - proses service sedang berlangsung"
    - "✅ Selesai Service - AC sudah berfungsi dengan baik"

- [ ] 2.4.3 Style consistency
  - Color: #0066CC
  - Font-size: 12px / 0.75rem
  - Font-weight: 500
  - Line-height: 1.5

- [ ] 2.4.4 Review & adjust based on feedback
  - Test with actual technicians
  - Adjust emoji/wording if needed

**Acceptance Criteria:**
- ✅ All instructions use blue text format
- ✅ Instructions are clear & actionable
- ✅ Emoji used consistently
- ✅ Tested with users

**Files:**
- `resources/js/components/FotoPerLayanan/instructions.ts` (new - constant definitions)
- Various component files (update instruction text)

**Dependencies:**
- Task 1.2, 2.1

---

### PHASE 3: TESTING & DEPLOYMENT (WEEK 4)
**Focus:** QA, optimization, deployment

#### Task 3.1: Integration Testing
- [ ] 3.1.1 Photo workflow end-to-end
  - Create order → Upload photos (all sections) → Submit → Verify in DB
  
- [ ] 3.1.2 Expense workflow end-to-end
  - Create expense → Approve in admin → Verify status change
  
- [ ] 3.1.3 Game 2 time lock testing
  - Test before 08:30 (can upload)
  - Test after 08:30 (cannot upload)
  - Test with checkbox bypass

- [ ] 3.1.4 Mobile testing
  - Test photo upload on mobile
  - Test form input on mobile
  - Test navigation & usability

#### Task 3.2: Performance Optimization
- [ ] 3.2.1 Photo upload optimization
  - Image compression before upload
  - Lazy loading for photo previews
  - Progressive upload feedback

- [ ] 3.2.2 Query optimization
  - Optimize expense queries (indexes)
  - Optimize photo queries (eager loading)

#### Task 3.3: Documentation
- [ ] 3.3.1 Create user guide for technicians
  - How to upload photos correctly
  - How to track expenses
  - Game 2 deadline explanation

- [ ] 3.3.2 Create admin guide
  - How to approve expenses
  - How to view reports
  - Configuration options

#### Task 3.4: Deployment
- [ ] 3.4.1 Database migrations
- [ ] 3.4.2 Deploy to staging
- [ ] 3.4.3 Staging QA
- [ ] 3.4.4 Deploy to production
- [ ] 3.4.5 Monitor & support

---

## 📊 TASK SUMMARY TABLE

| Phase | Task | Complexity | Est. Days | Status |
|-------|------|-----------|----------|--------|
| 1 | 1.1 Photo Backend | MEDIUM | 2 | 📋 |
| 1 | 1.2 Photo Frontend | HIGH | 3 | 📋 |
| 1 | 1.3 Order Integration | MEDIUM | 1.5 | 📋 |
| 1 | 1.4 Expense Backend | MEDIUM | 2 | 📋 |
| 2 | 2.1 Expense Teknisi UI | MEDIUM | 2.5 | 📋 |
| 2 | 2.2 Expense Admin UI | MEDIUM | 2.5 | 📋 |
| 2 | 2.3 Game 2 Lock | LOW | 1 | 📋 |
| 2 | 2.4 Instructions | LOW | 1 | 📋 |
| 3 | 3.1-3.4 Testing & Deploy | MEDIUM | 2 | 📋 |

**Total Estimate:** ~18-20 days (4 weeks with buffer)

---

## 🚀 DEPLOYMENT CHECKLIST

- [ ] All tasks completed & tested
- [ ] Code review passed
- [ ] Database backups created
- [ ] Migrations tested in staging
- [ ] Performance benchmarks passed
- [ ] Security review completed
- [ ] User documentation ready
- [ ] Deployment script prepared
- [ ] Rollback plan documented
- [ ] Stakeholder approval obtained
- [ ] Deploy to production
- [ ] Post-deployment monitoring
- [ ] Support team briefed

---

## 📝 NOTES & CONSIDERATIONS

### Technical Decisions
1. **Photo Storage:** Use existing storage system (S3/Local disk based on current setup)
2. **Component State:** React hooks (useState, useReducer) for complex state management
3. **Form Validation:** Formik or React Hook Form for consistency
4. **API Response:** RESTful with consistent error handling

### Known Risks
- **Photo Upload Performance:** Handle large files gracefully
- **Mobile Upload:** Ensure works on slow connections
- **Concurrent Edits:** Prevent race conditions on expense approval
- **Data Migration:** Backup before running migration scripts

### Future Enhancements
- Expense category budgeting & alerts
- Photo AI validation (detect incomplete photos)
- Automated expense categorization
- Integration with accounting software

---

## 📞 STAKEHOLDERS

| Role | Name | Responsibility |
|------|------|-----------------|
| Product Owner | - | Requirement validation |
| Backend Dev | - | API & DB implementation |
| Frontend Dev | - | UI/Component development |
| QA | - | Testing & validation |
| DevOps | - | Deployment & monitoring |

---

## 🔗 RELATED DOCUMENTS

- [01-ANALISIS_REQUIREMENT.md](01-ANALISIS_REQUIREMENT.md) - Detailed requirement analysis
- DB Schema Design (TBD)
- API Documentation (TBD)
- UI Mockups (TBD)

---

**Last Updated:** 2026-09-26  
**Version:** 1.0
