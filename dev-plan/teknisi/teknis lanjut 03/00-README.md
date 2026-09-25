# Portal Teknisi - Phase 03 Development Documentation

**Project:** CRM Packing - Portal Teknisi  
**Phase:** 03 (Lanjutan)  
**Status:** 📋 PLANNED  
**Created:** 2026-09-26

---

## 📚 DOKUMENTASI STRUKTUR

Folder ini berisi dokumentasi lengkap untuk Phase 03 pengembangan Portal Teknisi:

### 1. 📄 **00-README.md** (File Ini)
**Untuk:** Quick reference & navigation guide  
**Isi:** Overview, quick links, getting started

### 2. 🔍 **01-ANALISIS_REQUIREMENT.md**
**Untuk:** Memahami requirement dari customer  
**Isi:**
- Ringkasan 6 requirement (2 done, 4 baru)
- Detail problem statement & solution untuk setiap requirement
- Prioritas & complexity assessment
- Dependencies & database changes needed
- Success criteria

**Baca ketika:**
- Perlu understand apa yang customer minta
- Perlu confirm scope dengan stakeholder

### 3. 📋 **02-DEV_PLAN_FASE_03.md**
**Untuk:** Task breakdown & implementation roadmap  
**Isi:**
- Phase-based breakdown (Phase 1, 2, 3)
- Detailed tasks dengan subtasks, acceptance criteria, files, dependencies
- Estimated effort per task
- Integration testing & deployment checklist
- Stakeholders & timeline

**Baca ketika:**
- Perlu assign tasks ke team
- Perlu plan sprint
- Perlu understand dependencies

### 4. 🔧 **03-TECHNICAL_SPEC.md**
**Untuk:** Technical implementation details  
**Isi:**
- Database schema (DDL)
- API endpoint specifications (OpenAPI-like format)
- Component structure & hierarchy
- Business logic & validation rules
- Mobile considerations
- Performance metrics & optimization
- Testing checklist
- Error handling matrix

**Baca ketika:**
- Mulai implementasi
- Perlu referensi API contract
- Debugging implementation issue

---

## 🎯 QUICK SUMMARY

### Requirement Overview

#### ✅ COMPLETED (Fase 2)
1. **Riwayat Order Terpisah Harian** - Sudah done (filter order by date/customer/service)
2. **Tambah Layanan untuk Order Selesai** - Sudah done (allow additional service request)

#### 🆕 NEW (Phase 3)

| # | Requirement | Priority | Complexity | Days | Status |
|---|-------------|----------|-----------|------|--------|
| 3 | Menu Pendapatan/Pengeluaran (Expense Tracking) | 🔴 HIGH | MEDIUM | 4-5 | 📋 |
| 4 | Game 2 Photo Upload Lock (Time-based) | 🟠 MED | LOW | 1 | 📋 |
| 5 | Struktur Foto Per Layanan (Photo Organization) | 🔴 HIGH | HIGH | 5-6 | 📋 |
| 6 | Instruksi Foto Lebih Jelas (Blue Text + Icons) | 🟠 MED | LOW | 1 | 📋 |

**Total Estimate:** ~18-20 hari kerja (4 minggu)

---

## 🚀 GETTING STARTED

### For Product/Stakeholder
1. Read: **01-ANALISIS_REQUIREMENT.md** (understand what customer asked)
2. Review scope, confirm OK dengan customer
3. Assign priority & timeline

### For Dev Lead/Architect
1. Read: **02-DEV_PLAN_FASE_03.md** (task breakdown)
2. Read: **03-TECHNICAL_SPEC.md** (technical design)
3. Plan sprints, assign team members
4. Create GitHub issues from task list

### For Developer (Implementation)
1. Check assigned task dari **02-DEV_PLAN_FASE_03.md**
2. Read related section di **03-TECHNICAL_SPEC.md**
3. Review database schema, API spec, component structure
4. Start implementation

### For QA
1. Read: **02-DEV_PLAN_FASE_03.md** → Integration Testing section
2. Read: **03-TECHNICAL_SPEC.md** → Testing Checklist
3. Create test cases & scenarios

---

## 📊 PHASE BREAKDOWN SUMMARY

### PHASE 1 (Week 1-2): Core Infrastructure
- [ ] Photo backend schema & API
- [ ] Photo frontend components
- [ ] Order detail integration
- [ ] Expense backend (DB + API)

**Output:** Photo system fully functional, Expense API ready

### PHASE 2 (Week 3): Features & Polish
- [ ] Expense tracking UI (Teknisi portal)
- [ ] Expense management UI (Admin portal)
- [ ] Game 2 time lock
- [ ] Photo instructions (blue text)

**Output:** All features UI complete, ready for testing

### PHASE 3 (Week 4): Testing & Deployment
- [ ] Integration testing
- [ ] Performance optimization
- [ ] Documentation
- [ ] Deployment & monitoring

**Output:** Production ready, deployed, supported

---

## 🔗 KEY FEATURES BREAKDOWN

### Feature 1: Foto Per Layanan (Photo Organization)
**Problem:** Struktur upload foto tidak terorganisir  
**Solution:** Organize photos by service type (lokasi, cuci, service) dengan support multiple units

```
FOTO PER LAYANAN
├─ A. Foto Lokasi (1 foto)
├─ B. Cuci AC (5 foto per unit, repeatable)
│  ├─ Unit 1: [Indoor, Outdoor, Area Indoor, Area Outdoor, Suhu]
│  └─ Unit 2, 3, ... (add section button)
└─ C. Service AC (3 foto per unit, repeatable)
   ├─ Unit 1: [Kendala, Pengerjaan, Selesai]
   └─ Unit 2, 3, ... (add section button)
```

**Key Implementation:**
- Dynamic sections dengan "Add Unit" button
- Checkbox untuk skip section jika tidak perlu
- Validation hanya untuk section yang tidak di-skip
- Blue text instructions untuk clarity

---

### Feature 2: Menu Pendapatan/Pengeluaran (Expense Tracking)
**Problem:** Admin tidak bisa track pengeluaran teknisi  
**Solution:** Add expense tracking di portal teknisi + approval workflow di admin portal

**Teknisi Portal:**
- Input expense (date, category, nominal, description, optional receipt)
- Dashboard: today's summary, monthly breakdown
- History: list semua expenses dengan status

**Admin Portal:**
- Dashboard: pending vs approved expenses
- Approval list: batch approve/reject
- Reports: daily/monthly/by category
- Can edit nominal before approval

---

### Feature 3: Game 2 Photo Lock (Time-based)
**Problem:** Teknisi bisa upload Game 2 foto setelah jam 8:30 tapi masih dihitung bonus  
**Solution:** Lock Game 2 photo upload setelah deadline jam 8:30

**Implementation:**
- Check: jika current time >= 08:30, disable upload untuk Game 2
- Show: status badge "Game 2 Active" vs "Game 2 Expired"
- Allow: checkbox untuk bypass (edge case)

---

### Feature 4: Photo Instructions (UX)
**Problem:** Deskripsi teknis tidak jelas untuk teknisi  
**Solution:** Replace dengan blue text instructions + emoji

```
📸 Cuci Indoor - hasil pencucian bagian indoor sudah bersih
🏠 Area Cuci Indoor - area kerja saat pencucian indoor
🌡️ Suhu Indoor - capture layar temperature meter AC
```

---

## 📈 Implementation Checklist

### Pre-Implementation
- [ ] Review & approve docs dengan stakeholder
- [ ] Finalize database schema dengan DBA
- [ ] Assign team members ke tasks
- [ ] Setup dev environment

### Phase 1 - Infrastructure
- [ ] Photo migration & DB setup
- [ ] Photo API endpoints
- [ ] Photo components
- [ ] Order integration
- [ ] Expense DB setup
- [ ] Expense API endpoints

### Phase 2 - Features
- [ ] Expense tracking UI
- [ ] Expense admin UI
- [ ] Game 2 lock logic
- [ ] Photo instructions
- [ ] Mobile testing

### Phase 3 - Deployment
- [ ] Integration tests
- [ ] Performance tests
- [ ] UAT dengan teknisi
- [ ] Staging deployment
- [ ] Production deployment
- [ ] Monitoring & support

---

## 🔐 Database Changes Summary

### Tables to Create
1. `order_photos` (new) - reorganized photo storage
2. `teknis_expenses` (new) - expense tracking
3. `game_2_settings` (optional) - config for Game 2 deadline

### Tables to Modify
None (backward compatible approach)

### Migration Safety
- ✅ Existing photos can be migrated
- ✅ New structure doesn't break existing orders
- ✅ Backup before migration recommended

---

## 🔌 API Summary

### Photo Endpoints
- `POST /api/orders/{id}/photos` - Upload
- `GET /api/orders/{id}/photos` - Get organized photos
- `DELETE /api/orders/{id}/photos/{id}` - Delete

### Expense Endpoints
- `POST /api/expenses` - Create
- `GET /api/expenses` - List (with filters)
- `PUT /api/expenses/{id}` - Update
- `POST /api/expenses/{id}/approve` - Approve
- `POST /api/expenses/{id}/reject` - Reject
- `GET /api/expenses/summary/daily` - Daily summary
- `GET /api/expenses/summary/monthly` - Monthly summary

---

## 📱 Mobile First

Considerations untuk teknisi (mostly mobile user):
- ✅ Large touch targets (44px+)
- ✅ Auto-compress images
- ✅ Auto-retry upload on failure
- ✅ Offline queue for later upload
- ✅ Direct camera capture option
- ✅ Vertical layout (single column)
- ✅ Progress indicators

---

## 🧪 Testing Strategy

### Manual Testing Path
1. **Photo Flow:** Create order → Upload all photo sections → Submit → Verify in DB
2. **Expense Flow:** Create expense → Approve in admin → Verify status update
3. **Game 2:** Upload before 08:30 (should work), after 08:30 (should block)
4. **Mobile:** Test all flows on real phone, slow network, offline

### Automated Testing
- Unit tests: Model, Policy, Validation logic
- Integration tests: API endpoints, workflows
- E2E tests: Complete user scenarios (Cypress/Playwright)

---

## ❓ FAQ

### Q: Apakah Phase 3 ini breaking change?
**A:** Tidak. Backward compatible. Struktur photo baru bisa co-exist dengan yang lama.

### Q: Berapa effort untuk Game 2 lock?
**A:** Kecil (~1 hari). Cuma tambah time validation di backend + UI indicator.

### Q: Expense tracking perlu budget management?
**A:** Tidak untuk Phase 3. Bisa jadi future enhancement.

### Q: Photo compression aggressive atau moderate?
**A:** Moderate untuk maintain quality. Target: 1-2MB per photo dari 5MB max.

---

## 📞 CONTACT & QUESTIONS

**Product Owner:** [TBD]  
**Tech Lead:** [TBD]  
**Frontend Lead:** [TBD]  
**Backend Lead:** [TBD]

---

## 📝 DOCUMENT UPDATES

| Date | Version | Changed | By |
|------|---------|---------|-----|
| 2026-09-26 | 1.0 | Initial creation | Dev Team |

---

## 🎯 NEXT STEPS

1. **Today:** Stakeholder review & approval
2. **Tomorrow:** Team kick-off meeting
3. **Week 1:** Phase 1 implementation starts
4. **Week 2:** Phase 1 complete, Phase 2 starts
5. **Week 3:** Phase 2 complete, Phase 3 testing starts
6. **Week 4:** Deployed to production

---

**Status:** Ready for implementation 🚀

Last updated: 2026-09-26
