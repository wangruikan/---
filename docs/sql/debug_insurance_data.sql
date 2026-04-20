-- ========================================
-- 调试保险数据 - 检查为什么保险金额为0
-- ========================================

-- 1. 检查员工基本信息
SELECT 
    id,
    name,
    social_security_base,
    housing_fund_base,
    large_medical_base
FROM employees
WHERE name = 'wrk'; -- 替换为实际员工姓名

-- 2. 检查员工的参保记录
SELECT 
    id,
    employee_id,
    status,
    social_security_types,
    housing_fund_config_id
FROM insurance_personnel
WHERE employee_id = (SELECT id FROM employees WHERE name = 'wrk' LIMIT 1);

-- 3. 检查公积金配置
SELECT 
    id,
    name,
    company_ratio,
    personal_ratio
FROM housing_fund_configs
WHERE id = (
    SELECT housing_fund_config_id 
    FROM insurance_personnel 
    WHERE employee_id = (SELECT id FROM employees WHERE name = 'wrk' LIMIT 1)
    LIMIT 1
);

-- 4. 检查工资表中的保险金额
SELECT 
    id,
    employee_id,
    month,
    social_security,
    housing_fund,
    company_insurance_total,
    personal_insurance_total
FROM salaries
WHERE employee_id = (SELECT id FROM employees WHERE name = 'wrk' LIMIT 1)
    AND month = '2025-10';

-- 5. 完整的员工保险信息
SELECT 
    e.id AS employee_id,
    e.name AS employee_name,
    e.social_security_base,
    e.housing_fund_base,
    e.large_medical_base,
    ip.status AS insurance_status,
    ip.social_security_types,
    ip.housing_fund_config_id,
    hfc.name AS housing_fund_config_name,
    hfc.company_ratio AS hf_company_ratio,
    hfc.personal_ratio AS hf_personal_ratio
FROM employees e
LEFT JOIN insurance_personnel ip ON e.id = ip.employee_id AND ip.status = 'active'
LEFT JOIN housing_fund_configs hfc ON ip.housing_fund_config_id = hfc.id
WHERE e.name = 'wrk';

