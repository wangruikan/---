-- 查看补差记录的完整信息

SELECT 
    employee_name AS '员工',
    compensation_start_month AS '起始月',
    compensation_end_month AS '结束月',
    compensation_months AS '月数',
    old_base AS '旧基数',
    new_base AS '新基数',
    old_base_constrained AS '旧约束',
    new_base_constrained AS '新约束',
    company_total AS '公司合计',
    personal_total AS '个人合计',
    total_amount AS '总额',
    status AS '状态',
    created_at AS '创建时间',
    updated_at AS '更新时间'
FROM insurance_compensation_records
WHERE employee_name = 'wrk'
  AND compensation_type = 'social_security'
ORDER BY created_at DESC;

-- 查看补差详情（JSON）
SELECT 
    employee_name,
    compensation_details
FROM insurance_compensation_records
WHERE employee_name = 'wrk'
  AND compensation_type = 'social_security';

