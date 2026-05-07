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
Before generation, you can preview the current operational stock for the day.

- **Endpoint**: `POST /api/v1/spk/basah/operational-stock-preview`
- **Role**: `admin`, `dapur`
- **Payload**:
  ```json
  {
    "target_date": "2026-04-16"
  }
  ```

### 2. Generate SPK
Generate fresh recommendations for a given target date and estimated number of patients.

- **Endpoint**: `POST /api/v1/spk/basah/generate`
- **Role**: `admin`, `dapur`
- **Payload**:
  ```json
  {
    "target_date": "2026-04-16",
    "estimated_patients": 100
  }
  ```

### 3. Override Recommendations (Optional)
Modify specific item quantities if manual adjustment is needed.

- **Endpoint**: `POST /api/v1/spk/basah/history/{id}/override`
- **Role**: `admin`, `dapur`
- **Payload**:
  ```json
  {
    "item_id": 1,
    "recommended_qty": 50.0,
    "override_reason": "Extra guest buffer"
  }
  ```

### 4. Post Stock Transactions
Finalizes the SPK and adjusts inventory (e.g., generating necessary stock mutations).

- **Endpoint**: `POST /api/v1/spk/basah/history/{id}/post-stock`
- **Role**: `admin`

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
If there are no dishes scheduled for the target date, SPK generation will fail.

- **Response (400 Bad Request)**:
  ```json
  {
    "message": "Validation failed.",
    "errors": {
      "target_date": "No dishes found in menu schedule for the specified date."
    }
  }
  ```
