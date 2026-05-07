-- Task 3: stock_transactions.spk_id FK rollout verification
-- Deterministic strategy:
-- - Keep valid manual/non-SPK rows with spk_id IS NULL unchanged.
-- - For orphan non-null rows, perform explicit strategy BEFORE migration:
--   either backfill parent spk_calculations row, or set spk_id = NULL for rows proven manual/non-SPK.

SELECT DATABASE() AS active_database;

SELECT
    table_name,
    CASE
        WHEN COUNT(*) > 0 THEN 'present'
        ELSE 'missing'
    END AS table_status
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name IN ('stock_transactions', 'spk_calculations')
GROUP BY table_name
ORDER BY table_name ASC;

SELECT
    'orphan_before_strategy' AS check_name,
    COUNT(*) AS orphan_count
FROM stock_transactions st
LEFT JOIN spk_calculations sc ON sc.id = st.spk_id
WHERE st.spk_id IS NOT NULL
  AND sc.id IS NULL;

-- Explicit deterministic strategy (NO hidden mutation):
-- Null orphan non-null spk_id only for rows explicitly marked as manual/non-SPK.
-- This keeps valid NULL policy for manual transactions intact.
UPDATE stock_transactions st
LEFT JOIN spk_calculations sc ON sc.id = st.spk_id
SET st.spk_id = NULL
WHERE st.spk_id IS NOT NULL
  AND sc.id IS NULL
  AND st.reason = 'task3-manual-non-spk';

SELECT
    'orphan_after_strategy' AS check_name,
    COUNT(*) AS orphan_count
FROM stock_transactions st
LEFT JOIN spk_calculations sc ON sc.id = st.spk_id
WHERE st.spk_id IS NOT NULL
  AND sc.id IS NULL;

SELECT
    tc.constraint_name,
    tc.constraint_type,
    kcu.column_name,
    kcu.referenced_table_name,
    kcu.referenced_column_name
FROM information_schema.table_constraints tc
JOIN information_schema.key_column_usage kcu
  ON tc.constraint_schema = kcu.constraint_schema
 AND tc.table_name = kcu.table_name
 AND tc.constraint_name = kcu.constraint_name
WHERE tc.constraint_schema = DATABASE()
  AND tc.table_name = 'stock_transactions'
  AND tc.constraint_type = 'FOREIGN KEY'
  AND tc.constraint_name = 'fk_stock_transactions_spk_id';
