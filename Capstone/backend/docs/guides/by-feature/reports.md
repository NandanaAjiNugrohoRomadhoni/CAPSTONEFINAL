# Reports Feature Guide

Provides structured data for audit, evaluation, and operational history.

## Endpoints

- `GET /api/v1/reports/stocks`: Current inventory status and valuation.
- `GET /api/v1/reports/transactions`: History of all stock movements.
- `GET /api/v1/reports/spk-history`: Consolidated history of generated work orders.
- `GET /api/v1/reports/evaluation`: Comparison between planned vs. actual usage.
- `GET /api/v1/reports/monthly-stock-export`: Monthly per-item stock movement export grouped by `transaction_date`.

## Business Rules

- **Access**: Available to `admin`, `dapur`, and `gudang` roles.
- **Filtering**: Reports support date ranges and category filtering to isolate specific periods or item groups.
- **Monthly stock export**: Uses approved transaction rows only, loads `stok_awal` from `monthly_stock_snapshots`, keeps category filtering at item level, and preserves separate `BASAH`, `KERING`, and `PENGEMAS` rows.

## Related Documentation
- [Dashboard Guide](./dashboard.md)
- [Database Schema (Canonical)](../../reference/schema.md)
