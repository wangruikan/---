-- ========================================
-- 检查并重新生成工资表
-- ========================================

-- 1. 查看当前工资数据的合计
SELECT 
    employee_id,
    CONCAT((SELECT name FROM employees WHERE id = salaries.employee_id), '(', employee_id, ')') AS employee_name,
    company_insurance_total AS '单位合计',
    personal_insurance_total AS '个人合计',
    created_at AS '生成时间'
FROM salaries
WHERE month = '2025-10'
ORDER BY employee_id;

-- 2. 如果合计为 0，执行下面的删除语句重新生成
-- DELETE FROM salaries WHERE month = '2025-10';

-- 说明：
-- 如果上面查询显示合计都是 0，说明是旧数据
-- 取消注释第 16 行的 DELETE 语句，执行删除
-- 然后在前端重新点击"生成工资表"

