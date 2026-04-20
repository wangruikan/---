-- 为工资表添加累计专项扣除（社保公积金个人部分累计）字段
-- 累计专项扣除 = 当年1月到本月的（个人社保 + 个人公积金）累计值

ALTER TABLE `salaries` 
ADD COLUMN `cumulative_special_deduction_insurance` DECIMAL(10, 2) DEFAULT 0 
COMMENT '累计专项扣除（社保公积金个人部分）' 
AFTER `cumulative_basic_deduction`;

