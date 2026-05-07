-- Task 1: Migration Baseline Audit and Orphan Detection (SELECT-only)
-- Non-destructive integrity checks for:
-- 1) Null distribution in stock_transactions.spk_id
-- 2) Orphan distribution where spk_id is non-null but parent is missing in spk_calculations
-- 3) Existence check for monthly_stock_snapshots

SELECT DATABASE() AS active_database;

SELECT
    table_name,
    CASE
        WHEN COUNT(*) > 0 THEN 'present'
        ELSE 'missing'
    END AS table_status
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('stock_transactions', 'spk_calculations', 'monthly_stock_snapshots')
GROUP BY table_name
ORDER BY table_name ASC;

SELECT
    CASE
        WHEN st.spk_id IS NULL THEN 'spk_id_null'
        ELSE 'spk_id_non_null'
    END AS anomaly_class,
    COUNT(*) AS row_count
FROM stock_transactions st
GROUP BY anomaly_class
ORDER BY anomaly_class ASC;

SELECT
    CASE
        WHEN st.spk_id IS NULL THEN 'not_evaluated_null_spk_id'
        WHEN sc.id IS NULL THEN 'spk_id_orphan_missing_parent'
        ELSE 'spk_id_referenced_parent_exists'
    END AS anomaly_class,
    COUNT(*) AS row_count
FROM stock_transactions st
LEFT JOIN spk_calculations sc ON sc.id = st.spk_id
GROUP BY anomaly_class
ORDER BY anomaly_class ASC;

SELECT
    st.spk_id AS orphan_spk_id,
    COUNT(*) AS transaction_count
FROM stock_transactions st
LEFT JOIN spk_calculations sc ON sc.id = st.spk_id
WHERE st.spk_id IS NOT NULL
  AND sc.id IS NULL
GROUP BY st.spk_id
ORDER BY transaction_count DESC, orphan_spk_id ASC;
