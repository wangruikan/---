-- ========================================
-- 分析公积金为什么读取到1400
-- ========================================

-- 1. 当前时间和本月第一天
SELECT 
    NOW() AS '当前时间',
    DATE_FORMAT(NOW(), '%Y-%m-01') AS '本月第一天（用于比较）';

-- 2. 员工wrk的所有公积金调基记录
SELECT 
    ba.id,
    e.name AS '员工',
    ba.old_housing_fund_base AS '旧公积金基数',
    ba.new_housing_fund_base AS '新公积金基数',
    ba.housing_fund_effective_date AS '生效日期',
    ba.status AS '状态',
    CASE 
        WHEN ba.housing_fund_effective_date < DATE_FORMAT(NOW(), '%Y-%m-01') THEN '✅ 本月之前'
        WHEN ba.housing_fund_effective_date >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN '❌ 本月或之后'
        ELSE '❓ NULL'
    END AS '是否本月之前',
    ba.created_at AS '创建时间'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
ORDER BY ba.created_at DESC;

-- 3. 模拟代码：查找本月之前最近一次生效的公积金基数
SELECT 
    '3️⃣ 查找本月之前最近一次公积金调基' AS '步骤',
    ba.id,
    e.name AS '员工',
    ba.new_housing_fund_base AS '应该读取的旧基数',
    ba.housing_fund_effective_date AS '生效日期',
    ba.status AS '状态'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
  AND ba.status = 'applied'
  AND ba.housing_fund_effective_date IS NOT NULL
  AND ba.housing_fund_effective_date < DATE_FORMAT(NOW(), '%Y-%m-01')
ORDER BY ba.housing_fund_effective_date DESC
LIMIT 1;

-- 4. 如果上面查不到，则使用员工表的公积金基数
SELECT 
    '4️⃣ 兜底-员工表的公积金基数' AS '步骤',
    name AS '员工',
    housing_fund_base AS '公积金基数（从这里读取到1400？）'
FROM employees
WHERE name = 'wrk';

-- 5. 检查是否有公积金调基记录但状态不是applied
SELECT 
    '5️⃣ 检查非applied状态的记录' AS '步骤',
    ba.id,
    e.name AS '员工',
    ba.new_housing_fund_base AS '新公积金基数',
    ba.housing_fund_effective_date AS '生效日期',
    ba.status AS '状态（可能不是applied）'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
  AND ba.housing_fund_effective_date IS NOT NULL
ORDER BY ba.housing_fund_effective_date DESC;

-- 6. 检查是否housing_fund_effective_date是NULL
SELECT 
    '6️⃣ 检查生效日期为NULL的记录' AS '步骤',
    ba.id,
    e.name AS '员工',
    ba.old_social_security_base AS '旧社保基数',
    ba.new_social_security_base AS '新社保基数',
    ba.old_housing_fund_base AS '旧公积金基数',
    ba.new_housing_fund_base AS '新公积金基数',
    ba.social_security_effective_date AS '社保生效日期',
    ba.housing_fund_effective_date AS '公积金生效日期（是NULL？）',
    ba.status AS '状态'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
ORDER BY ba.created_at DESC;

