# Stock Opname Workflow Guide

This guide describes the lifecycle of a Stock Opname, from initial draft creation to final stock posting.

## State Machine

The Stock Opname process follows a strict state transition flow:

1.  **DRAFT**: Initial state when a stock opname is created.
2.  **SUBMITTED**: The opname has been finalized by the creator and is awaiting approval.
3.  **APPROVED**: The opname has been verified and approved by an administrator.
4.  **REJECTED**: The opname was rejected by an administrator (can be re-submitted from DRAFT).
5.  **POSTED**: The final state where variances are applied to the actual item stock.

### Valid Transitions
- `DRAFT` → `SUBMITTED`
- `SUBMITTED` → `APPROVED`
- `SUBMITTED` → `REJECTED`
- `APPROVED` → `POSTED`
- `REJECTED` → `SUBMITTED` (after corrections)

## Step-by-step Endpoints

### 1. Create Draft
Create a new stock opname record with physical counts.

- **Endpoint**: `POST /api/v1/stock-opnames`
- **Role**: `admin`, `gudang`
- **Payload**:
  ```json
  {
    "opname_date": "2026-04-16",
    "notes": "Bulanan April",
    "details": [
      {
        "item_id": 1,
        "counted_qty": 100.5
      }
    ]
  }
  ```

### 2. Submit for Approval
Transition the opname from `DRAFT` to `SUBMITTED`.

- **Endpoint**: `POST /api/v1/stock-opnames/{id}/submit`
- **Role**: `admin`, `gudang`

### 3. Review (Approve or Reject)
Administrator reviews the submitted opname.

#### Approve
- **Endpoint**: `POST /api/v1/stock-opnames/{id}/approve`
- **Role**: `admin`

#### Reject
- **Endpoint**: `POST /api/v1/stock-opnames/{id}/reject`
- **Role**: `admin`
- **Payload**:
  ```json
  {
    "reason": "Count for Item #1 seems incorrect, please re-verify."
  }
  ```

### 4. Post Variances
Apply the calculated variances to the live inventory. This creates `IN` or `OUT` stock transactions automatically.

- **Endpoint**: `POST /api/v1/stock-opnames/{id}/post`
- **Role**: `admin`

## Failure Paths

### Insufficient Stock during Posting
If the variance is negative (stock reduction) and the live stock has dropped below the required variance amount since the opname was approved, the posting will fail.

- **Response (400 Bad Request)**:
  ```json
  {
    "message": "Validation failed.",
    "errors": {
      "details": "Insufficient stock to post stock opname variance."
    }
  }
  ```

### Invalid State Transition
Attempting to trigger an action that is not allowed for the current state (e.g., posting a DRAFT).

- **Response (400 Bad Request)**:
  ```json
  {
    "message": "Validation failed.",
    "errors": {
      "state": "Invalid state transition from DRAFT to POSTED."
    }
  }
  ```
