-- 查看当前月份
SELECT NOW() AS '当前时间', DATE_FORMAT(NOW(), '%Y-%m-01') AS '当前月第一天';

-- 查看员工wrk的所有调基记录
SELECT 
    ba.id,
    e.name AS '员工',
    ba.old_social_security_base AS '旧社保基数',
    ba.new_social_security_base AS '新社保基数',
    ba.social_security_effective_date AS '生效日期',
    ba.status AS '状态',
    CASE 
        WHEN ba.social_security_effective_date < DATE_FORMAT(NOW(), '%Y-%m-01') THEN '✅ 本月之前'
        ELSE '❌ 本月或之后'
    END AS '是否本月之前',
    ba.created_at AS '创建时间'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
  AND ba.status = 'applied'
  AND ba.social_security_effective_date IS NOT NULL
ORDER BY ba.social_security_effective_date DESC;

-- 模拟代码逻辑：查找本月之前最近一次生效的基数
SELECT 
    ba.id,
    e.name AS '员工',
    ba.new_social_security_base AS '应该读取的旧基数',
    ba.social_security_effective_date AS '生效日期'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
  AND ba.status = 'applied'
  AND ba.social_security_effective_date IS NOT NULL
  AND ba.social_security_effective_date < DATE_FORMAT(NOW(), '%Y-%m-01')
ORDER BY ba.social_security_effective_date DESC
LIMIT 1;

-- 如果上面没有记录，则使用员工表的当前基数
SELECT 
    name AS '员工',
    social_security_base AS '员工表的社保基数（兜底值）'
FROM employees
WHERE name = 'wrk';

