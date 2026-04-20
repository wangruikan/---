-- 为工资表添加税务相关字段
-- 1. 累计其他应纳税项（合并扣税）
-- 2. 应补（退）税额
-- 3. 本人签字

ALTER TABLE `salaries` 
ADD COLUMN `cumulative_other_taxable` DECIMAL(10, 2) DEFAULT 0.00 
COMMENT '累计其他应纳税项（合并扣税）' 
AFTER `taxable_income`;

ALTER TABLE `salaries` 
ADD COLUMN `tax_payable_or_refundable` DECIMAL(10, 2) DEFAULT 0.00 
COMMENT '应补（退）税额' 
AFTER `cumulative_other_taxable`;

ALTER TABLE `salaries` 
ADD COLUMN `employee_signature` VARCHAR(255) DEFAULT NULL 
COMMENT '本人签字' 
AFTER `tax_payable_or_refundable`;

