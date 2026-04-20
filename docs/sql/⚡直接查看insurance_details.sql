-- ========================================
-- 直接查看工资表中insurance_details的实际内容
-- ========================================

-- 查看最新的工资记录
SELECT 
    id,
    employee_name,
    project_id,
    month,
    insurance_details
FROM salaries
WHERE insurance_details IS NOT NULL
AND insurance_details != ''
AND insurance_details != 'null'
ORDER BY id DESC
LIMIT 3;

-- 如果上面没有数据，查看所有记录
SELECT 
    COUNT(*) as total_count,
    SUM(CASE WHEN insurance_details IS NULL THEN 1 ELSE 0 END) as null_count,
    SUM(CASE WHEN insurance_details = '' THEN 1 ELSE 0 END) as empty_count,
    SUM(CASE WHEN insurance_details IS NOT NULL AND insurance_details != '' THEN 1 ELSE 0 END) as has_data_count
FROM salaries;

