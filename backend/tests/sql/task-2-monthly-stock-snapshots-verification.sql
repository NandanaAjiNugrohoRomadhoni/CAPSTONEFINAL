-- Task 2: monthly_stock_snapshots schema verification (read-only checks)

SELECT DATABASE() AS active_database;

SELECT
    table_name,
    CASE
        WHEN COUNT(*) > 0 THEN 'present'
        ELSE 'missing'
    END AS table_status
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'monthly_stock_snapshots'
GROUP BY table_name;

SELECT
    column_name,
    column_type,
    is_nullable,
    column_key,
    extra
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'monthly_stock_snapshots'
ORDER BY ordinal_position ASC;

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
  AND tc.table_name = 'monthly_stock_snapshots'
  AND tc.constraint_type IN ('UNIQUE', 'FOREIGN KEY', 'PRIMARY KEY')
ORDER BY tc.constraint_name, kcu.ordinal_position;
