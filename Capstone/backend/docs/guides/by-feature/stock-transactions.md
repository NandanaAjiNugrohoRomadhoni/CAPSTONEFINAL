# Stock Transactions Feature Guide

Manages the movement and history of inventory items.

## Endpoints

- `GET /api/v1/stock-transactions`: List transactions.
- `POST /api/v1/stock-transactions`: Create a new transaction (typically `PENDING`).
- `GET /api/v1/stock-transactions/{id}`: Show transaction summary.
- `GET /api/v1/stock-transactions/{id}/details`: List specific items in the transaction.
- `POST /api/v1/stock-transactions/{id}/submit-revision`: Propose changes to a transaction.
- `POST /api/v1/stock-transactions/{id}/approve`: Finalize and post transaction (Admin).
- `POST /api/v1/stock-transactions/{id}/reject`: Deny a pending transaction (Admin).
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
