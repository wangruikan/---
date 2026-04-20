-- 检查员工wrk的调基记录
SELECT 
    ba.id,
    e.name AS '员工',
    ba.old_social_security_base AS '旧社保基数',
    ba.new_social_security_base AS '新社保基数',
    ba.social_security_effective_date AS '生效日期',
    ba.status AS '状态',
    ba.applied_at AS '应用时间',
    ba.created_at AS '创建时间'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
ORDER BY ba.social_security_effective_date DESC;

-- 检查员工表的当前基数
SELECT 
    name AS '员工',
    social_security_base AS '当前社保基数',
    housing_fund_base AS '当前公积金基数'
FROM employees
WHERE name = 'wrk';

