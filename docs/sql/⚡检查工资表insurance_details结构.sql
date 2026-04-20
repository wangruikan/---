-- ========================================
-- 检查工资表中insurance_details的实际结构
-- ========================================

-- 查看最新的一条工资记录的insurance_details
SELECT 
    id,
    employee_name,
    project_id,
    month,
    insurance_details
FROM salaries
WHERE insurance_details IS NOT NULL
ORDER BY id DESC
LIMIT 1;

-- 如果返回的是JSON，请复制完整的insurance_details字段内容

