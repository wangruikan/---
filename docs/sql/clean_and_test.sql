-- 清理旧补差记录
DELETE FROM insurance_compensation_records WHERE employee_name = 'wrk';

-- 查看员工wrk实际参保的险种
SELECT 
    ip.employee_name AS '员工',
    ip.social_security_types AS '参保险种配置'
FROM insurance_personnel ip
WHERE ip.employee_name = 'wrk'
AND ip.status = 'active';

-- 然后在前端修改社保上下限，查看补差记录

