-- ============================================
-- 修正上下限字段位置（最终版）
-- 从类型表移到地区表
-- 医保不需要补差，所以不添加新旧上下限字段
-- ============================================

-- ============================================
-- 步骤1：删除类型表的错误字段
-- ============================================

-- 1.1 删除社保类型表的新旧上下限字段
ALTER TABLE `social_security_types`
DROP COLUMN `old_min_base_amount`,
DROP COLUMN `old_max_base_amount`,
DROP COLUMN `old_limits_updated_at`,
DROP COLUMN `new_min_base_amount`,
DROP COLUMN `new_max_base_amount`,
DROP COLUMN `new_limits_updated_at`;

-- 1.2 删除医保类型表的新旧上下限字段（如果有的话）
ALTER TABLE `medical_insurance_types`
DROP COLUMN `old_min_base_amount`,
DROP COLUMN `old_max_base_amount`,
DROP COLUMN `old_limits_updated_at`,
DROP COLUMN `new_min_base_amount`,
DROP COLUMN `new_max_base_amount`,
DROP COLUMN `new_limits_updated_at`;

-- ============================================
-- 步骤2：只给社保地区表添加新旧上下限字段
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

-- ============================================
-- 验证修正结果
-- ============================================

-- 验证社保地区表
SELECT 
    id,
    name,
    min_base_amount AS '原下限',
    max_base_amount AS '原上限',
    new_min_base_amount AS '新下限',
    new_max_base_amount AS '新上限'
FROM social_security_regions
LIMIT 3;

-- ============================================
-- 说明
-- ============================================

/*
修正内容：
1. ✅ 从社保类型表删除新旧上下限字段
2. ✅ 从医保类型表删除新旧上下限字段（如果有）
3. ✅ 只给社保地区表添加新旧上下限字段
4. ❌ 医保地区表不添加新旧上下限（医保不需要补差）
5. ✅ 迁移现有数据到new_*字段

正确的设计：
- 社保上下限绑定到地区（Region），不是类型（Type）
- 所有细分类型（养老、医疗、失业等）共用同一个地区的上下限
- 医保不需要补差，所以医保地区表不需要新旧上下限字段
- 公积金的上下限仍然在 housing_fund_configs 表

使用场景：
- 修改深圳社保上下限 → 触发补差 → 影响所有使用深圳社保的员工
- 修改深圳医保上下限 → 不触发补差
- 修改公积金上下限 → 触发补差（仍然使用之前的逻辑）

注意：
- 如果步骤1删除字段时报错（字段不存在），可以忽略
- 执行完成后记得清理缓存：php artisan config:clear
*/

