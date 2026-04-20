-- 更新已存在工资记录的累计专项附加扣除

-- 步骤1：先将 special_deduction 的值复制到 special_deduction_monthly（当月专项附加扣除）
-- 因为旧数据的 special_deduction 实际上存储的是当月的值
UPDATE salaries 
SET special_deduction_monthly = special_deduction
WHERE special_deduction_monthly IS NULL OR special_deduction_monthly = 0;

-- 步骤2：重新计算累计专项附加扣除
-- 这个脚本会为每条工资记录计算从1月到当前月的专项附加扣除累计值

-- 创建临时表存储计算结果
CREATE TEMPORARY TABLE temp_cumulative_deductions AS
SELECT 
    s1.id,
    s1.employee_id,
    s1.month,
    s1.account_set_id,
    COALESCE(SUM(s2.special_deduction_monthly), 0) as cumulative_deduction
FROM salaries s1
LEFT JOIN salaries s2 ON 
    s2.employee_id = s1.employee_id 
    AND s2.account_set_id = s1.account_set_id
    AND YEAR(STR_TO_DATE(CONCAT(s2.month, '-01'), '%Y-%m-%d')) = YEAR(STR_TO_DATE(CONCAT(s1.month, '-01'), '%Y-%m-%d'))
    AND s2.month <= s1.month
GROUP BY s1.id, s1.employee_id, s1.month, s1.account_set_id;

-- 更新 salaries 表
UPDATE salaries s
INNER JOIN temp_cumulative_deductions t ON s.id = t.id
SET s.special_deduction = t.cumulative_deduction;

-- 删除临时表
DROP TEMPORARY TABLE temp_cumulative_deductions;

-- 验证结果
SELECT 
    id, 
    employee_id, 
    month, 
    special_deduction_monthly as '当月专项附加扣除', 
    special_deduction as '累计专项附加扣除'
FROM salaries 
WHERE employee_id IN (SELECT DISTINCT employee_id FROM salaries LIMIT 3)
ORDER BY employee_id, month;

