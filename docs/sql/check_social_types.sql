-- 检查员工wrk的社保险种和比例
SELECT 
    e.name AS '员工',
    r.name AS '地区',
    t.name AS '险种',
    t.company_ratio AS '公司比例',
    t.employee_ratio AS '个人比例'
FROM employees e
JOIN social_security_regions r ON e.social_security_region_id = r.id
JOIN social_security_types t ON r.id = t.region_id
WHERE e.name = 'wrk'
ORDER BY t.id;

