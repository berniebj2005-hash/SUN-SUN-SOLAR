-- Read-only preflight for user_db before applying migrations 002 and 003.
-- Review all results and resolve duplicates / schema drift manually before running either migration.

SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('customer_information','employee_db','admin_db','services','bookings','notifications')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

SELECT Username, COUNT(*) AS duplicate_count FROM customer_information GROUP BY Username HAVING COUNT(*) > 1;
SELECT Email, COUNT(*) AS duplicate_count FROM customer_information GROUP BY Email HAVING COUNT(*) > 1;
SELECT Username, COUNT(*) AS duplicate_count FROM employee_db GROUP BY Username HAVING COUNT(*) > 1;
SELECT Email, COUNT(*) AS duplicate_count FROM employee_db GROUP BY Email HAVING COUNT(*) > 1;
SELECT Username, COUNT(*) AS duplicate_count FROM admin_db GROUP BY Username HAVING COUNT(*) > 1;

-- Cross-table duplicate identity check: these legacy tables do not enforce global uniqueness.
SELECT Username, COUNT(*) AS account_count FROM (
  SELECT Username FROM customer_information
  UNION ALL SELECT Username FROM employee_db
  UNION ALL SELECT Username FROM admin_db
) AS account_names GROUP BY Username HAVING COUNT(*) > 1;

SELECT Email, COUNT(*) AS account_count FROM (
  SELECT Email FROM customer_information
  UNION ALL SELECT Email FROM employee_db
) AS account_emails GROUP BY Email HAVING COUNT(*) > 1;
