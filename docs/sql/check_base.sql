-- 查询wrk的参保人员基数
SELECT 
    employee_name,
    employee_social_security_base AS '社保基数',
    employee_medical_insurance_base AS '医保基数',
    employee_housing_fund_base AS '公积金基数',
    last_updated_at AS '最后更新时间'
FROM insurance_personnel
WHERE employee_name = 'wrk';

-- 查询wrk的增减记录中的原始基数
SELECT 
    employee_name,
    employee_social_security_base AS '原始社保基数',
    employee_medical_insurance_base AS '原始医保基数',
    employee_housing_fund_base AS '原始公积金基数',
    social_security_types AS '社保配置JSON',
    status
FROM insurance_changes
WHERE employee_name = 'wrk'
ORDER BY id DESC
LIMIT 1;


