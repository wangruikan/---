-- 添加税率和速算扣除数字段到salaries表

-- 添加税率字段（百分比，如3表示3%）
ALTER TABLE `salaries` ADD COLUMN `tax_rate` DECIMAL(5, 2) DEFAULT 0 COMMENT '税率(%)' AFTER `cumulative_income`;

-- 添加速算扣除数字段
ALTER TABLE `salaries` ADD COLUMN `quick_deduction` DECIMAL(10, 2) DEFAULT 0 COMMENT '速算扣除数' AFTER `tax_rate`;

