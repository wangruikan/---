-- 快速检查和测试

-- 1. 查看社保地区表结构（检查字段是否存在）
DESC social_security_regions;

-- 2. 查看wrk使用的社保地区
SELECT 
    r.id,
    r.name,
    r.min_base_amount AS '原下限',
    r.max_base_amount AS '原上限',
    r.old_min_base_amount AS '旧下限',
    r.old_max_base_amount AS '旧上限',
    r.new_min_base_amount AS '新下限',
    r.new_max_base_amount AS '新上限',
    r.new_limits_updated_at AS '新修改时间'
FROM social_security_regions r
JOIN employees e ON e.social_security_region_id = r.id
WHERE e.name = 'wrk';

-- 3. 查看补差记录
SELECT 
    employee_name,
    compensation_start_month,
    compensation_end_month,
    compensation_months,
    old_base,
    new_base,
    total_amount,
    created_at,
    updated_at
FROM insurance_compensation_records
WHERE employee_name = 'wrk'
ORDER BY created_at DESC;

