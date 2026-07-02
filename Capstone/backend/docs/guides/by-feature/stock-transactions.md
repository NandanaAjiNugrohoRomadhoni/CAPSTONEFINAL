# Stock Transactions Feature Guide

Manages the movement and history of inventory items.

## Endpoints

  - `GET /api/v1/stock-transactions`: List transactions. Supports pagination (`page`, `perPage`), filtering (`type_id`, `status_id`, `category_id`, `spk_id`, `transaction_date_from`/`_to`, `created_at_from`/`_to`, `updated_at_from`/`_to`), search (`q` or `search` fuzzy-match on `spk_id`), and sorting (`sortBy`, `sortDir`).
- `POST /api/v1/stock-transactions`: Create a new stock transaction. Allowed fields: `type_id` / `type_name`, `transaction_date`, `spk_id` (optional), `details[]` with `item_id`, `qty`, `input_unit`.
- `GET /api/v1/stock-transactions/{id}`: Show transaction summary.
- `GET /api/v1/stock-transactions/{id}/details`: List specific items in the transaction.
- `PUT /api/v1/stock-transactions/{id}`: Update a pending BASAH OUT draft. Replaces detail rows; stock is not mutated.
- `POST /api/v1/stock-transactions/{id}/submit`: Submit a pending BASAH OUT draft. Approves, decrements stock, and finalizes the transaction.
- `POST /api/v1/stock-transactions/{id}/cancel`: Cancel (reject) a pending BASAH OUT draft. Frees the daily slot; stock is not mutated.
- `POST /api/v1/stock-transactions/{id}/submit-revision`: Propose changes to a transaction.
- `POST /api/v1/stock-transactions/{id}/approve`: Finalize and post transaction (Admin).
- `POST /api/v1/stock-transactions/{id}/reject`: Deny a pending transaction (Admin).
    - Accepts an optional request body: `{ "reason": "string" }`.
    - The `reason` key from the request is stored in the `stock_transactions.rejection_reason` column.
    - Backward compatible: the request body can be omitted or empty.
    - **Note**: The transaction-level `reason` field (set during creation) retains its original meaning as a general transaction note and is distinct from this rejection-specific reason.
- `POST /api/v1/stock-transactions/direct-corrections`: Immediate stock adjustment (Admin).

## Business Rules

- **Revision Lifecycle**: Transactions created by `gudang` often start as `PENDING` and require `admin` approval to affect actual stock.
- **Stock Impact**: Only `APPROVED` transactions or `direct-corrections` update the `items.qty` balance.
- **Types**:
  - `IN`: Receiving goods.
  - `OUT`: Issuing goods (requires sufficient stock).
  - `RETURN_IN`: Returning items back to inventory.

## Related Documentation
- [Stock Correction Workflow](../by-workflow/stock-correction-workflow.md)
- [Database Schema (Canonical)](../../reference/schema.md)
