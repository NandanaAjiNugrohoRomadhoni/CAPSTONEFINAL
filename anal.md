# Root Cause Analysis: "Masuk Error" — Validasi SPK Basah

## Case
User (super-admin/gudang) melakukan input pasien di halaman **Transaksi Keluar → Bahan Basah**, lalu:
1. Isi jumlah pasien → klik **Simpan**
2. Muncul **popup tabel rekomendasi** (hasil `operationalStockPreview`)
3. Tutup popup
4. Mau **edit rekomendasi** → klik **Validasi** → **gabisa** (tombol disabled/locked)
5. Mau **Simpan** → **gabisa** (alert "Klik Validasi dulu")

State deadlock — user gabisa ngapa-ngapain setelah langkah 1.

---

## Flow Detail (Gudang Version `transaksi/keluar/page.tsx`)

### Step 1 — Input pasien, klik "Simpan" (first time)

```typescript
// Baris ~564-568
if (!savedRecommendation) {
    void prepareRecommendationPreview();
    return;
}
```

`prepareRecommendationPreview()` (baris 159-246):
```
→ operationalStockPreview per meal_time × N
→ ensureDailyPatientForDate() → POST /daily-patients  ✅
→ aggregatePreviewItems()
→ writeSavedRecommendation(nextRecommendation)   → localStorage
→ setSavedRecommendation(nextRecommendation)     ← state di-set
→ setValidatedRows([])                             ← ❌ KOSONG
→ show popup rekomendasi
```

### Step 2 — Tutup popup

```
→ useEffect(serviceDate) fires (baris 120-127):
    readSavedRecommendation(serviceDate)
    → setSavedRecommendation(saved)
    → setPatientCount(...)
    → setValidatedMeta(...)
    → NOT setting validatedRows                      ← ❌ GAK DI-TOUCH
```

### Step 3 — Klik "Validasi" → GABISA

```typescript
// Baris 84
const basahValidationLocked = Boolean(savedRecommendation);  // = true

// Baris 446-452 — tombol Validasi
disabled={validating || basahValidationLocked || ...}
```

- `basahValidationLocked = true` → tombol `disabled` → **gabisa diklik**
- Teks tombol jadi **"Sudah Divalidasi"**

### Step 4 — Klik "Simpan" (second time) → GABISA

```typescript
// Baris 589-611
onClick={() => {
    if (!savedRecommendation) { ... return; }  // ← saved recom EXISTS, skip
    if (validatedRows.length === 0) {
        openAlert(..., "Klik tombol Validasi dulu");  // ← ❌ HIT THIS
        return;
    }
    setConfirmSaveOpen(true);
}}
```

- `savedRecommendation` ada → skip first if
- `validatedRows.length === 0` → **masuk alert**, gak lanjut ke save

---

## Root Cause

`validatedRows` **hanya bisa diisi** oleh `loadValidatedBasahFromSavedRecommendation()`:

```typescript
function loadValidatedBasahFromSavedRecommendation() {
    if (basahValidationLocked) {          // ← TRUE karena savedRecommendation sudah ada
        openAlert("Validasi Terkunci");
        return;                             // ← ❌ RETURN SEBELUM SET ROWS
    }
    ...
    setValidatedRows(saved.rows.map(...));  // ← gak pernah kesini
}
```

### Deadlock State Machine

| State | `savedRecommendation` | `validatedRows` | Validasi button | Simpan button |
|-------|----------------------|-----------------|-----------------|---------------|
| Awal | `null` | `[]` | 🔒 `disabled` (pasien=0) | ✅ → call `prepareRecommendationPreview()` |
| **Setelah popup tutup** | **SET** | **`[]`** | **🔒 `basahValidationLocked=true`** | **🔒 "Klik Validasi dulu"** |
| Yang diharapkan | SET | Rows dari localStorage | ✅ Bisa edit | ✅ Bisa save |

### 2 Bug Sites

**Bug 1 — `prepareRecommendationPreview()` baris 226 (gudang) / ~226 (super-admin)**
```typescript
// ❌ Yang sekarang:
setValidatedRows([]);

// ✅ Harusnya:
const nextRows = aggregated.map((row) => ({ ...row, locked: false }));
setValidatedRows(nextRows);
```

`setValidatedRows([])` ngosongin rows setelah rekomendasi berhasil dibuat. Akibatnya, meskipun data lengkap di localStorage, `validatedRows` tetap kosong dan gak ada jalur untuk ngisi ulang.

**Bug 2 — `useEffect` baris 120-127**
```typescript
// ❌ Yang sekarang:
setSavedRecommendation(saved);
setPatientCount(String(saved.patientCount));
setValidatedMeta({ totalItems: saved.totalItems, menuName: saved.menuName });
// validatedRows NOT set

// ✅ Harusnya tambah:
setValidatedRows(saved.rows.map((row) => ({ ...row })));
```

`useEffect` restore localStorage ke state tapi lupa set `validatedRows`.

---

## Efek Samping

1. **User gabisa edit rekomendasi** — tombol Validasi terkunci padahal belum pernah diedit
2. **User gabisa simpan** — alert looping "Klik Validasi dulu" → "Validasi Terkunci"
3. **Harus reload page** untuk reset state

---

## Super-admin Version — Problem Berbeda

Di super-admin, Validasi button `disabled={validating || !savedRecommendation}`:
- **Sebelum Simpan pertama**: `!savedRecommendation = true` → Validasi **disabled**
- **Setelah Simpan pertama**: `savedRecommendation` ada → Validasi **enabled**
- Tapi Simpan pertama belum set `validatedRows` → klik Validasi jalan ke `loadValidatedBasahFromSavedRecommendation()` → karena `savedRecommendation` ada, `basahValidationLocked=false` (gak ada variable ini di super-admin), jadi **berhasil** load rows

Super-admin **tidak kena deadlock yang sama** karena gak punya guard `basahValidationLocked`. Tapi UX tetap bermasalah: user harus **Simpan → preview → Tutup → Validasi → Simpan lagi** (4 langkah buat 1 transaksi).

---

## Fix

### Fix 1 — `prepareRecommendationPreview()` (gudang + super-admin)

Set `validatedRows` dengan data aggregasi, bukan `[]`.

**Gudang `page.tsx:226`:**
```typescript
// Ganti:
setValidatedRows([]);
// Jadi:
setValidatedRows(aggregated.map((row) => ({ ...row, locked: false })));
```

### Fix 2 — `useEffect` restore dari localStorage (gudang `page.tsx:120-127`)

```typescript
// Tambahkan di dalam blok if(saved):
setValidatedRows(saved.rows.map((row) => ({ ...row })));
```

### Setelah Fix — Flow jadi:

```
Input pasien → Simpan
  → prepareRecommendationPreview()
    → operationalStockPreview
    → ensureDailyPatientForDate
    → setValidatedRows(aggregated)          ← ROWS LANGSUNG TERISI
    → setSavedRecommendation(nextRecom)
    → show popup

Tutup popup → useEffect restore → validatedRows tetap terisi ✅

User edit → Simpan
  → savedRecommendation ada → skip if
  → validatedRows.length > 0 → ✅ setConfirmSaveOpen(true)
  → saveBasahOutput()
```
