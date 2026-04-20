-- 检查社保地区表的字段
DESC social_security_regions;

-- 查看数据
SELECT 
    id,
    name,
    min_base_amount AS '原下限',
    max_base_amount AS '原上限',
    old_min_base_amount AS '旧下限',
    old_max_base_amount AS '旧上限',
    old_limits_updated_at AS '旧修改时间',
    new_min_base_amount AS '新下限',
    new_max_base_amount AS '新上限',
    new_limits_updated_at AS '新修改时间'
FROM social_security_regions
LIMIT 3;

