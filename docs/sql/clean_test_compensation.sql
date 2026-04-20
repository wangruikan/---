-- ============================================
-- 清理测试的补差记录
-- ============================================

-- 查看现有的补差记录
SELECT 
    id,
    employee_name,
    compensation_type,
    compensation_start_month AS '起始',
    compensation_end_month AS '结束',
    compensation_months AS '月数',
    old_base AS '旧基数',
    new_base AS '新基数',
    total_amount AS '总额',
    created_at AS '创建时间'
FROM insurance_compensation_records
WHERE employee_name = 'wrk'
ORDER BY created_at DESC;

-- 如果需要清理，取消注释下面的SQL
-- DELETE FROM insurance_compensation_records WHERE employee_name = 'wrk';

-- 也可以清理所有补差记录
-- DELETE FROM insurance_compensation_records;

