-- 修复员工基数数据
UPDATE employees 
SET 
    social_security_base = 2800.00,
    housing_fund_base = 1400.00
WHERE id = 16;

-- 验证修改结果
SELECT 
    id,
    name,
    social_security_base,
    housing_fund_base,
    large_medical_base
FROM employees
WHERE id = 16;

