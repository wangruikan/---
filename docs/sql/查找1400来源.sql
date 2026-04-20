-- ========================================
-- 查找1400的来源 - 员工wrk
-- ========================================

-- 1. 员工表的基数
SELECT '1️⃣ 员工表' AS '表名', 
    name AS '员工',
    social_security_base AS '社保基数',
    medical_insurance_base AS '医保基数',
    housing_fund_base AS '公积金基数',
    large_medical_base AS '大额医疗基数'
FROM employees
WHERE name = 'wrk';

-- 2. 调基记录表
SELECT '2️⃣ 调基记录表' AS '表名',
    ba.id,
    e.name AS '员工',
    ba.old_social_security_base AS '旧社保基数',
    ba.new_social_security_base AS '新社保基数',
    ba.old_medical_insurance_base AS '旧医保基数',
    ba.new_medical_insurance_base AS '新医保基数',
    ba.old_housing_fund_base AS '旧公积金基数',
    ba.new_housing_fund_base AS '新公积金基数',
    ba.social_security_effective_date AS '社保生效日期',
    ba.medical_insurance_effective_date AS '医保生效日期',
    ba.housing_fund_effective_date AS '公积金生效日期',
    ba.status AS '状态',
    ba.created_at AS '创建时间'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
ORDER BY ba.created_at DESC;

-- 3. 参保人员表
SELECT '3️⃣ 参保人员表' AS '表名',
    employee_name AS '员工',
    employee_social_security_base AS '社保基数',
    employee_medical_insurance_base AS '医保基数',
    employee_housing_fund_base AS '公积金基数',
    employee_large_medical_base AS '大额医疗基数',
    social_security_base AS '社保基数2',
    medical_insurance_base AS '医保基数2',
    status AS '状态',
    created_at AS '创建时间',
    updated_at AS '更新时间'
FROM insurance_personnel
WHERE employee_name = 'wrk';

-- 4. 保险变更表
SELECT '4️⃣ 保险变更表' AS '表名',
    ic.id,
    ic.employee_name AS '员工',
    ic.employee_social_security_base AS '社保基数',
    ic.employee_medical_insurance_base AS '医保基数',
    ic.employee_housing_fund_base AS '公积金基数',
    ic.employee_large_medical_base AS '大额医疗基数',
    ic.change_type AS '变更类型',
    ic.change_status AS '变更状态',
    ic.effective_date AS '生效日期',
    ic.created_at AS '创建时间'
FROM insurance_changes ic
WHERE ic.employee_name = 'wrk'
ORDER BY ic.created_at DESC
LIMIT 5;

-- 5. 补差记录表
SELECT '5️⃣ 补差记录表' AS '表名',
    employee_name AS '员工',
    compensation_type AS '补差类型',
    old_base AS '旧基数',
    new_base AS '新基数',
    start_month AS '开始月份',
    end_month AS '结束月份',
    company_total AS '公司合计',
    personal_total AS '个人合计',
    total_amount AS '总额',
    created_at AS '创建时间'
FROM insurance_compensation_records
WHERE employee_name = 'wrk'
ORDER BY created_at DESC;

-- 6. 社保地区的上下限
SELECT '6️⃣ 社保地区上下限' AS '表名',
    ssr.id,
    ssr.name AS '地区',
    ssr.old_min_base_amount AS '旧下限',
    ssr.old_max_base_amount AS '旧上限',
    ssr.new_min_base_amount AS '新下限',
    ssr.new_max_base_amount AS '新上限',
    ssr.min_base_amount AS '下限',
    ssr.max_base_amount AS '上限'
FROM social_security_regions ssr
WHERE ssr.id = (SELECT social_security_region_id FROM employees WHERE name = 'wrk');

-- 7. 公积金配置的上下限
SELECT '7️⃣ 公积金配置上下限' AS '表名',
    hfc.id,
    hfc.config_name AS '配置名',
    hfc.old_min_base_amount AS '旧下限',
    hfc.old_max_base_amount AS '旧上限',
    hfc.new_min_base_amount AS '新下限',
    hfc.new_max_base_amount AS '新上限',
    hfc.min_base_amount AS '下限',
    hfc.max_base_amount AS '上限'
FROM housing_fund_configs hfc
WHERE hfc.id = (SELECT housing_fund_config_id FROM employees WHERE name = 'wrk');

-- ========================================
-- 模拟代码逻辑
-- ========================================

-- 8. 模拟：查找本月之前最近一次生效的社保基数（旧基数）
SELECT '8️⃣ 代码逻辑-旧基数' AS '来源',
    ba.id,
    e.name AS '员工',
    ba.new_social_security_base AS '读取到的旧基数（new_social_security_base）',
    ba.social_security_effective_date AS '生效日期'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
  AND ba.status = 'applied'
  AND ba.social_security_effective_date IS NOT NULL
  AND ba.social_security_effective_date < DATE_FORMAT(NOW(), '%Y-%m-01')
ORDER BY ba.social_security_effective_date DESC
LIMIT 1;

-- 9. 如果上面没有记录，使用员工表的基数
SELECT '9️⃣ 兜底-员工表基数' AS '来源',
    name AS '员工',
    social_security_base AS '旧基数（兜底）'
FROM employees
WHERE name = 'wrk';

-- 10. 模拟：查找本月生效的社保基数（新基数）
SELECT '🔟 代码逻辑-新基数' AS '来源',
    ba.id,
    e.name AS '员工',
    ba.new_social_security_base AS '读取到的新基数',
    ba.social_security_effective_date AS '生效日期'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
  AND ba.status = 'applied'
  AND ba.social_security_effective_date IS NOT NULL
  AND YEAR(ba.social_security_effective_date) = YEAR(NOW())
  AND MONTH(ba.social_security_effective_date) = MONTH(NOW())
ORDER BY ba.social_security_effective_date DESC
LIMIT 1;

