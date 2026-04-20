-- 查看 compensation_details 的格式

SELECT 
    id,
    employee_name,
    compensation_details
FROM insurance_compensation_records
WHERE employee_name = 'wrk'
  AND compensation_type = 'social_security';

