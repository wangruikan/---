-- 查看最新的补差记录，确认是社保还是公积金
SELECT 
    id,
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

-- 对比员工表的基数
SELECT 
    name AS '员工',
    social_security_base AS '社保基数',
    housing_fund_base AS '公积金基数'
FROM employees
WHERE name = 'wrk';

-- 查看公积金调基记录
SELECT 
    ba.id,
    e.name AS '员工',
    ba.old_housing_fund_base AS '旧公积金基数',
    ba.new_housing_fund_base AS '新公积金基数',
    ba.housing_fund_effective_date AS '生效日期',
    ba.status AS '状态',
    ba.created_at AS '创建时间'
FROM base_adjustments ba
JOIN employees e ON ba.employee_id = e.id
WHERE e.name = 'wrk'
  AND ba.housing_fund_effective_date IS NOT NULL
ORDER BY ba.housing_fund_effective_date DESC;

