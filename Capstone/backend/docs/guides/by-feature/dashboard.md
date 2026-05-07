# Dashboard Feature Guide

Provides a real-time summary of operational status and key metrics.

## Endpoints

- `GET /api/v1/dashboard`: Retrieve aggregate data for the dashboard view.

## Business Rules

- **Access**: Available to all primary roles (`admin`, `dapur`, `gudang`).
- **Data Points**: Typically includes:
  - Total items with low stock.
  - Pending stock transactions awaiting approval.
  - Today's menu schedule.
  - Recent stock movements.

## Related Documentation
- [Reporting Guide](./reports.md)
- [Runtime Status (Canonical)](../../architecture/runtime-status.md)
