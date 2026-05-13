# Stock Opname Feature Guide

Manages the physical inventory audit and adjustment process.

## Endpoints

- `POST /api/v1/stock-opnames`: Start a new opname (snapshot current stock).
- `GET /api/v1/stock-opnames/{id}`: Show opname results and discrepancies.
- `POST /api/v1/stock-opnames/{id}/submit`: Submit results for approval.
- `POST /api/v1/stock-opnames/{id}/approve`: Admin approval of audit.
- `POST /api/v1/stock-opnames/{id}/reject`: Admin rejection of audit.
- `POST /api/v1/stock-opnames/{id}/post`: Finalize and adjust `items.qty` based on discrepancy.

## Business Rules

- **Snapshot**: Creating an opname records the "expected" quantity at that moment.
- **Discrepancy**: Calculated as `physical_qty - system_qty`.
- **Posting**: Finalizing an opname generates a `CORRECTION` transaction to align system stock with the verified physical count.
- **Permissions**: `gudang` performs the count; `admin` approves and posts the adjustments.

## Related Documentation
- [Stock Opname Workflow](../by-workflow/stock-opname-workflow.md)
- [Gudang Quickstart](../by-user/gudang-quickstart.md)
