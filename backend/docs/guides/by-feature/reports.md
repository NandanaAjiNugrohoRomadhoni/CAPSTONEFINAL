# Reports Feature Guide

Provides structured data for audit, evaluation, and operational history.

## Endpoints

- `GET /api/v1/reports/stocks`: Current inventory status and valuation.
- `GET /api/v1/reports/transactions`: History of all stock movements.
- `GET /api/v1/reports/spk-history`: Consolidated history of generated work orders.
- `GET /api/v1/reports/evaluation`: Comparison between planned vs. actual usage.

## Business Rules

- **Access**: Available to `admin`, `dapur`, and `gudang` roles.
- **Filtering**: Reports support date ranges and category filtering to isolate specific periods or item groups.

## Related Documentation
- [Dashboard Guide](./dashboard.md)
- [Database Schema (Canonical)](../../reference/schema.md)
