-- 检查补差记录表
-- 1. 查看补差记录表结构
SHOW COLUMNS FROM insurance_compensation_records;

-- 2. 查看所有补差记录
SELECT 
    id,
    employee_id,
    employee_name,
    compensation_type,
    old_base,
    new_base,
    compensation_start_month,
    compensation_end_month,
    compensation_months,
    company_total,
    personal_total,
    total_amount,
    status
FROM insurance_compensation_records
LIMIT 10;

-- 3. 查看2025-10月的补差记录
SELECT 
    id,
    employee_id,
    employee_name,
    compensation_type,
    old_base,
    new_base,
    compensation_start_month,
    compensation_end_month,
    company_total,
    personal_total,
    total_amount
FROM insurance_compensation_records
WHERE compensation_start_month <= '2025-10'
AND compensation_end_month >= '2025-10';

-- 4. 检查wrk员工的补差记录
SELECT 
    icr.*
FROM insurance_compensation_records icr
JOIN employees e ON icr.employee_id = e.id
WHERE e.name = 'wrk';
