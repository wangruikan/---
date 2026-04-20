-- ============================================
-- 添加新旧上下限字段到保险地区表（正确版本）
-- 执行时间：立即执行
-- ============================================

-- 1. 社保地区表添加新旧上下限字段
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

-- ============================================

-- 2. 医保地区表添加新旧上下限字段
ALTER TABLE `medical_insurance_regions`
ADD COLUMN `old_min_base_amount` DECIMAL(10,2) NULL COMMENT '旧最低基数' AFTER `max_base_amount`,
ADD COLUMN `old_max_base_amount` DECIMAL(10,2) NULL COMMENT '旧最高基数' AFTER `old_min_base_amount`,
ADD COLUMN `old_limits_updated_at` TIMESTAMP NULL COMMENT '旧上下限修改时间' AFTER `old_max_base_amount`,
ADD COLUMN `new_min_base_amount` DECIMAL(10,2) NULL COMMENT '新最低基数' AFTER `old_limits_updated_at`,
ADD COLUMN `new_max_base_amount` DECIMAL(10,2) NULL COMMENT '新最高基数' AFTER `new_min_base_amount`,
ADD COLUMN `new_limits_updated_at` TIMESTAMP NULL COMMENT '新上下限修改时间' AFTER `new_max_base_amount`;

-- 迁移现有数据
UPDATE `medical_insurance_regions` 
SET `new_min_base_amount` = `min_base_amount`,
    `new_max_base_amount` = `max_base_amount`,
    `new_limits_updated_at` = `updated_at`
WHERE `min_base_amount` IS NOT NULL OR `max_base_amount` IS NOT NULL;

-- ============================================

-- 3. 公积金地区表（如果有的话，保持不变，因为公积金用的是config）
-- housing_fund_configs 已经在之前的SQL中添加了字段

-- ============================================
-- 验证数据
-- ============================================

-- 查看社保地区表的数据
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
LIMIT 5;

-- 查看医保地区表的数据
SELECT 
    id,
    name,
    min_base_amount AS '原下限',
    max_base_amount AS '原上限',
    old_min_base_amount AS '旧下限',
    old_max_base_amount AS '旧上限',
    new_min_base_amount AS '新下限',
    new_max_base_amount AS '新上限'
FROM medical_insurance_regions
LIMIT 5;

-- ============================================
-- 说明
-- ============================================

/*
正确的设计：
- 上下限绑定到地区（Region），而不是类型（Type）
- 所有细分类型（养老、医疗、失业等）共用同一个地区的上下限
- 每个地区只有一个上下限设置

字段说明：
1. old_min_base_amount / old_max_base_amount - 旧上下限（上一次的值）
2. old_limits_updated_at - 旧上下限的修改时间
3. new_min_base_amount / new_max_base_amount - 新上下限（当前的值）
4. new_limits_updated_at - 新上下限的修改时间

使用场景：
- 修改深圳社保的上下限 → 深圳的所有社保类型都用这个上下限
- 修改北京社保的上下限 → 北京的所有社保类型都用这个上下限
*/

