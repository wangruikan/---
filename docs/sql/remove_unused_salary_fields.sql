-- 删除工资表中不需要的字段

-- 删除加班费字段
ALTER TABLE `salaries` DROP COLUMN IF EXISTS `overtime_pay`;

-- 删除津贴字段
ALTER TABLE `salaries` DROP COLUMN IF EXISTS `allowance`;

-- 删除奖金字段
ALTER TABLE `salaries` DROP COLUMN IF EXISTS `bonus`;

-- 删除补差合计字段
ALTER TABLE `salaries` DROP COLUMN IF EXISTS `compensation_total`;

-- 删除个税字段
ALTER TABLE `salaries` DROP COLUMN IF EXISTS `personal_tax`;

-- 删除实际个税字段（如果有的话）
ALTER TABLE `salaries` DROP COLUMN IF EXISTS `actual_tax`;

