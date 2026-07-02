# SPK Basah Workflow Guide

The SPK Basah workflow manages the calculation, generation, and posting of recommendations for fresh items (items with category `BASAH`) based on menu schedules and patient numbers.

## State Machine

An SPK calculation follows a simple progression:

1.  **PENDING (IN_PROGRESS)**: Calculation is created and being refined (overridden).
2.  **POSTED**: Recommendations have been successfully pushed into the stock system as transactions.

### Valid Transitions
- `PENDING` → `POSTED`
- `PENDING` (override) → `PENDING`

## Step-by-step Endpoints

### 1. Preview Preparation
Before generation, you can preview the current operational stock for a specific service date and meal time.

- **Endpoint**: `POST /api/v1/spk/basah/operational-stock-preview`
- **Role**: `admin`, `dapur`, `gudang`
- **Payload**:
  ```json
  {
    "service_date": "2026-04-16",
    "meal_time": "SIANG",
    "total_patients": 120
  }
  ```

### 2. Generate SPK
Generate fresh recommendations from one service date (daily patient input is read from `daily-patients`).

- **Endpoint**: `POST /api/v1/spk/basah/generate`
- **Role**: `admin`, `dapur`, `gudang`
- **Payload**:
  ```json
  {
    "service_date": "2026-04-16",
    "regenerate": false
  }
  ```

If an unfinished SPK already exists for the same scope, the API returns a conflict response and includes the existing SPK metadata. Send `regenerate=true` to intentionally create a new version.

Generation-date policy for `service_date`:

- Regular rule: generation is allowed on even dates.
- Special rule for 31-day months:
  - Day 30 generates only day 31.
  - Day 31 generates next-month day 1 and day 2.
- Special rule for leap February:
  - Day 28 generates only day 29.
  - Day 29 generates March day 1 and day 2.
- Other odd dates are rejected.

Examples:

- `2026-03-10` -> target dates `2026-03-11`, `2026-03-12`
- `2026-03-30` -> target date `2026-03-31`
- `2026-03-31` -> target dates `2026-04-01`, `2026-04-02`
- `2028-02-28` (leap year) -> target date `2028-02-29`
- `2028-02-29` -> target dates `2028-03-01`, `2028-03-02`

### 3. Override Recommendations (Optional)
Modify specific item quantities if manual adjustment is needed.

- **Endpoint**: `POST /api/v1/spk/basah/history/{id}/override`
- **Role**: `admin`, `dapur`, `gudang`
- **Payload**:
  ```json
  {
    "recommendation_id": 1,
    "recommended_qty": 50.0,
    "reason": "Extra guest buffer"
  }
  ```

### 4. Post Stock Transactions
Finalizes the SPK and adjusts inventory (e.g., generating necessary stock mutations).

- **Endpoint**: `POST /api/v1/spk/basah/history/{id}/post-stock`
- **Role**: `admin`, `gudang`

## Failure Paths

### Already Posted
Attempting to post or override an SPK that has already been finalized.

- **Response (400 Bad Request)**:
  ```json
  {
    "message": "SPK already posted to stock transaction.",
    "errors": {
      "is_finish": true
    }
  }
  ```

### Menu Schedule Missing
If there are no dishes scheduled for one of the resolved target dates, SPK generation will fail.

- **Response (400 Bad Request)**:
  ```json
  {
    "message": "Validation failed.",
    "errors": {
      "menu_mapping": "No menu mapping found for target date 2026-03-11."
    }
  }
  ```
