-- ========================================
-- 调试工资表合计问题
-- ========================================

-- 1. 查看 salaries 表的合计字段
SELECT 
    id,
    employee_id,
    month,
    company_insurance_total AS '单位合计',
    personal_insurance_total AS '个人合计',
    created_at AS '创建时间',
    updated_at AS '更新时间'
FROM salaries
WHERE month = '2025-10'
ORDER BY id DESC
LIMIT 5;

-- 2. 查看最新一条记录的详细信息
SELECT 
    *
FROM salaries
WHERE month = '2025-10'
ORDER BY id DESC
LIMIT 1;

-- 说明：
-- 如果 company_insurance_total 和 personal_insurance_total 都是 0.00
-- 说明是旧数据，需要删除重新生成

