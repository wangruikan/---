-- 添加当月专项附加扣除合计字段

-- 添加当月专项附加扣除合计字段（6项扣除的当月合计）
ALTER TABLE `salaries` ADD COLUMN `special_deduction_monthly` DECIMAL(10, 2) DEFAULT 0 COMMENT '当月专项附加扣除（6项扣除合计）' AFTER `special_deduction`;

-- 添加累计专项附加扣除字段（1月到当前月的累计）
-- 注意：special_deduction 字段已存在，这里只是添加说明
-- special_deduction 字段将用于存储累计专项附加扣除（从1月累计到当前月）

