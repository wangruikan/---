-- ========================================
-- 更新工资表身份证号和员工姓名
-- ========================================

-- 1. 更新现有工资表的身份证号和员工姓名
UPDATE salaries s
INNER JOIN employees e ON s.employee_id = e.id
SET 
    s.id_card = e.id_number,
    s.employee_name = e.name
WHERE s.id_card IS NULL OR s.employee_name IS NULL OR s.id_card = '' OR s.employee_name = '';

-- 2. 验证更新结果
SELECT 
    '工资表数据统计' as description,
    COUNT(*) as total_salaries,
    SUM(CASE WHEN id_card IS NOT NULL AND id_card != '' THEN 1 ELSE 0 END) as has_id_card,
    SUM(CASE WHEN employee_name IS NOT NULL AND employee_name != '' THEN 1 ELSE 0 END) as has_name,
    SUM(CASE WHEN id_card IS NULL OR id_card = '' THEN 1 ELSE 0 END) as missing_id_card
FROM salaries;

-- 3. 查看最新的5条工资记录（验证是否更新成功）
SELECT 
    id,
    employee_id,
    employee_name,
    id_card,
    month,
    gross_salary,
    created_at
FROM salaries 
ORDER BY created_at DESC
LIMIT 5;

-- 4. 检查员工表中哪些员工没有身份证号
SELECT 
    id,
    name,
    id_number,
    CASE WHEN id_number IS NULL OR id_number = '' THEN '缺失' ELSE '正常' END as status
FROM employees
WHERE id_number IS NULL OR id_number = ''
LIMIT 10;

