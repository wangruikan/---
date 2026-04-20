-- 查看可用的公积金配置
SELECT id, config_name, company_ratio, personal_ratio 
FROM housing_fund_configs 
WHERE status = 'active';

-- 为员工16的参保记录设置公积金配置（假设使用配置ID=1）
UPDATE insurance_personnel 
SET housing_fund_config_id = 1
WHERE employee_id = 16 AND status = 'active';

-- 验证修改结果
SELECT 
    id,
    employee_id,
    employee_name,
    housing_fund_config_id,
    employee_housing_fund_base
FROM insurance_personnel
WHERE employee_id = 16 AND status = 'active';

