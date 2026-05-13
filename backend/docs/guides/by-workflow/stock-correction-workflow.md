# Stock Correction Workflow Guide

Stock corrections are used to resolve discrepancies in the inventory. Two main correction paths exist: **Direct Corrections** (Administrator only) and **Revisions** (Staff-submitted).

## State Machine

Stock corrections follow the state transitions defined by the standard `Approval Status` lookup:

1.  **PENDING**: A revision has been submitted and is awaiting administrator review.
2.  **APPROVED**: The revision was accepted and the stock mutation has been applied.
3.  **REJECTED**: The revision was dismissed; no stock changes occurred.

**Note on Direct Corrections**: Administrator direct corrections skip the `PENDING` state and are applied immediately as `APPROVED` transactions.

### Valid Transitions (Revisions)
- `PENDING` → `APPROVED`
- `PENDING` → `REJECTED`

## Step-by-step Endpoints

### Path A: Direct Correction (Administrator)
An immediate adjustment to a specific item's stock.

- **Endpoint**: `POST /api/v1/stock-transactions/direct-corrections`
- **Role**: `admin`
- **Payload**:
  ```json
  {
    "transaction_date": "2026-04-16",
    "item_id": 1,
    "expected_current_qty": 50.0,
    "target_qty": 45.0,
    "reason": "Broken package discovery"
  }
  ```

### Path B: Revision (Staff)
Submit a proposed change to an existing transaction.

#### 1. Submit Revision
- **Endpoint**: `POST /api/v1/stock-transactions/{id}/submit-revision`
- **Role**: `admin`, `gudang`
- **Payload**:
  ```json
  {
    "transaction_date": "2026-04-16",
    "details": [
      {
        "item_id": 1,
        "qty": 40.0,
        "input_unit": "base"
      }
    ]
  }
  ```

#### 2. Review Revision
Administrator reviews the submitted revision.

##### Approve
- **Endpoint**: `POST /api/v1/stock-transactions/{id}/approve`
- **Role**: `admin`

##### Reject
- **Endpoint**: `POST /api/v1/stock-transactions/{id}/reject`
- **Role**: `admin`

## Failure Paths

### Mismatched Current Quantity (Direct Correction)
When submitting a direct correction, if the live stock no longer matches your `expected_current_qty`, the request fails to prevent race conditions.

- **Response (400 Bad Request)**:
  ```json
  {
    "message": "Validation failed.",
    "errors": {
      "expected_current_qty": "Current stock no longer matches expected_current_qty. Reload the item and retry the correction."
    }
  }
  ```

### Pending Revision Conflict
Attempting to submit a revision while another sibling revision in the same lineage is still pending review.

- **Response (400 Bad Request)**:
  ```json
  {
    "message": "Validation failed.",
    "errors": {
      "id": "Another revision for this transaction is still pending review."
    }
  }
  ```

### Sequential Sibling Revision Baseline

- Revisions remain sibling rows (`is_revision = true`) that all point to the same original parent (`parent_transaction_id = <root id>`).
- Revision-on-revision submission is still blocked.
- Only one `PENDING` sibling is allowed at a time.
- Successive approvals are allowed over time.
- Approval baseline is deterministic:
  1. latest approved sibling in the same lineage (highest revision id), or
  2. original parent transaction when no approved sibling exists.

### Revision on Revision
Attempting to submit a revision on a transaction that is already a revision.

- **Response (400 Bad Request)**:
  ```json
  {
    "message": "Validation failed.",
    "errors": {
      "id": "Revision transactions cannot be revised again."
    }
  }
  ```
