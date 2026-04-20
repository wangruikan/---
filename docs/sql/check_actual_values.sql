-- 查看补差记录的实际值

SELECT 
    id,
    employee_name AS '员工',
    old_base AS '旧基数',
    new_base AS '新基数',
    old_base_constrained AS '旧约束后',
    new_base_constrained AS '新约束后',
    company_total AS '公司合计',
    personal_total AS '个人合计',
    total_amount AS '总额',
    compensation_months AS '月数'
FROM insurance_compensation_records
WHERE employee_name = 'wrk'
  AND compensation_type = 'social_security'
ORDER BY created_at DESC
LIMIT 1;

-- 验证计算
-- 如果公司合计 = 1800, 月数 = 9, 公司比例 = 100%
-- 则：基数差额 = 1800 / 1.0 / 9 = 200
-- 所以：新约束后基数 - 旧约束后基数 = 200

