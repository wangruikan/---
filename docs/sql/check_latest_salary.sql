-- 检查最新生成的工资记录（按创建时间排序）

SELECT 
    id,
    employee_id,
    month,
    basic_salary,
    gross_salary,
    social_security,
    housing_fund,
    company_insurance_total,
    personal_insurance_total,
    created_at,
    updated_at
FROM salaries 
WHERE month = '2025-10' AND project_id = 4
ORDER BY created_at DESC
LIMIT 5;

-- 如果有多条记录，删除旧的，只保留最新的
-- DELETE FROM salaries WHERE month = '2025-10' AND project_id = 4 AND id < (SELECT MAX(id) FROM (SELECT id FROM salaries WHERE month = '2025-10' AND project_id = 4) as temp);



