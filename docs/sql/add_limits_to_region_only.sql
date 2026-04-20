-- ============================================
-- 只执行步骤2：给社保地区表添加新旧上下限字段
-- ============================================

-- 社保地区表添加新旧上下限字段
ALTER TABLE `social_security_regions`
ADD COLUMN `old_min_base_amount` DECIMAL(10,2) NULL COMMENT '旧最低基数' AFTER `max_base_amount`,
ADD COLUMN `old_max_base_amount` DECIMAL(10,2) NULL COMMENT '旧最高基数' AFTER `old_min_base_amount`,
ADD COLUMN `old_limits_updated_at` TIMESTAMP NULL COMMENT '旧上下限修改时间' AFTER `old_max_base_amount`,
ADD COLUMN `new_min_base_amount` DECIMAL(10,2) NULL COMMENT '新最低基数' AFTER `old_limits_updated_at`,
ADD COLUMN `new_max_base_amount` DECIMAL(10,2) NULL COMMENT '新最高基数' AFTER `new_min_base_amount`,
ADD COLUMN `new_limits_updated_at` TIMESTAMP NULL COMMENT '新上下限修改时间' AFTER `new_max_base_amount`;

-- 迁移现有数据：将 min_base_amount 和 max_base_amount 复制到 new_* 字段
UPDATE `social_security_regions` 
SET `new_min_base_amount` = `min_base_amount`,
    `new_max_base_amount` = `max_base_amount`,
    `new_limits_updated_at` = `updated_at`
WHERE `min_base_amount` IS NOT NULL OR `max_base_amount` IS NOT NULL;

-- 验证结果
SELECT 
    id,
    name,
    min_base_amount AS '原下限',
    max_base_amount AS '原上限',
    new_min_base_amount AS '新下限',
    new_max_base_amount AS '新上限',
    new_limits_updated_at AS '新修改时间'
FROM social_security_regions
WHERE new_min_base_amount IS NOT NULL
LIMIT 5;

