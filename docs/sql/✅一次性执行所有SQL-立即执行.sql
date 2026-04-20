-- ========================================
-- 工资表字段完整添加 - 一次性执行所有SQL
-- 执行时间：2025-10-28
-- ========================================

-- 1. 保险导入设置（记录生成时的保险导入设置）
ALTER TABLE `salaries` 
ADD COLUMN `insurance_import_setting` ENUM('current', 'next', 'none') DEFAULT 'current' 
COMMENT '保险导入设置（生成时的设置）：current-当月，next-次月，none-不导入' 
AFTER `position`;

-- 更新现有记录
UPDATE `salaries` SET `insurance_import_setting` = 'current' WHERE `insurance_import_setting` IS NULL;

-- ========================================

-- 2. 累计减除费用（5000×月数）
ALTER TABLE `salaries` 
ADD COLUMN `cumulative_basic_deduction` DECIMAL(10, 2) DEFAULT 0 
COMMENT '累计减除费用（5000×月数）' 
AFTER `cumulative_income`;

-- ========================================

-- 3. 累计专项扣除（社保公积金个人部分）
ALTER TABLE `salaries` 
ADD COLUMN `cumulative_special_deduction_insurance` DECIMAL(10, 2) DEFAULT 0 
COMMENT '累计专项扣除（社保公积金个人部分）' 
AFTER `cumulative_basic_deduction`;

-- ========================================

-- 4. 税务相关字段（3个）

-- 4.1 累计其他应纳税项（合并扣税）
ALTER TABLE `salaries` 
ADD COLUMN `cumulative_other_taxable` DECIMAL(10, 2) DEFAULT 0.00 
COMMENT '累计其他应纳税项（合并扣税）' 
AFTER `taxable_income`;

-- 4.2 应补（退）税额
ALTER TABLE `salaries` 
ADD COLUMN `tax_payable_or_refundable` DECIMAL(10, 2) DEFAULT 0.00 
COMMENT '应补（退）税额' 
AFTER `cumulative_other_taxable`;

-- 4.3 本人签字
ALTER TABLE `salaries` 
ADD COLUMN `employee_signature` VARCHAR(255) DEFAULT NULL 
COMMENT '本人签字' 
AFTER `tax_payable_or_refundable`;

-- ========================================

-- 执行完成！
-- 请检查以下命令确认字段已添加：
-- DESCRIBE salaries;

