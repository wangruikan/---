-- ========================================
-- 添加 salaries 表缺失的字段
-- ========================================

-- 1. 添加津贴字段
ALTER TABLE `salaries` 
ADD COLUMN `allowance` DECIMAL(10,2) DEFAULT 0.00 COMMENT '津贴' AFTER `basic_salary`;

-- 2. 添加应发工资字段
ALTER TABLE `salaries` 
ADD COLUMN `gross_salary` DECIMAL(10,2) DEFAULT 0.00 COMMENT '应发工资' AFTER `bonus`;

-- 3. 添加社保个人部分字段
ALTER TABLE `salaries` 
ADD COLUMN `social_security` DECIMAL(10,2) DEFAULT 0.00 COMMENT '社保个人部分' AFTER `gross_salary`;

-- 4. 添加公积金个人部分字段
ALTER TABLE `salaries` 
ADD COLUMN `housing_fund` DECIMAL(10,2) DEFAULT 0.00 COMMENT '公积金个人部分' AFTER `social_security`;

-- 5. 添加专项扣除字段
ALTER TABLE `salaries` 
ADD COLUMN `special_deduction` DECIMAL(10,2) DEFAULT 0.00 COMMENT '专项扣除' AFTER `housing_fund`;

-- 6. 添加应纳税所得额字段
ALTER TABLE `salaries` 
ADD COLUMN `taxable_income` DECIMAL(10,2) DEFAULT 0.00 COMMENT '应纳税所得额' AFTER `special_deduction`;

-- 7. 添加个人所得税字段
ALTER TABLE `salaries` 
ADD COLUMN `personal_tax` DECIMAL(10,2) DEFAULT 0.00 COMMENT '个人所得税' AFTER `taxable_income`;

-- 8. 添加实际个税字段
ALTER TABLE `salaries` 
ADD COLUMN `actual_tax` DECIMAL(10,2) DEFAULT 0.00 COMMENT '实际个税' AFTER `personal_tax`;

-- 9. 添加实际发放金额字段
ALTER TABLE `salaries` 
ADD COLUMN `paid_salary` DECIMAL(10,2) DEFAULT 0.00 COMMENT '实际发放金额' AFTER `net_salary`;

-- 10. 添加提交人字段
ALTER TABLE `salaries` 
ADD COLUMN `submitted_by` BIGINT UNSIGNED NULL COMMENT '提交人' AFTER `status`;

-- 11. 添加审批人字段
ALTER TABLE `salaries` 
ADD COLUMN `approved_by` BIGINT UNSIGNED NULL COMMENT '审批人' AFTER `submitted_by`;

-- 12. 添加提交时间字段
ALTER TABLE `salaries` 
ADD COLUMN `submitted_at` TIMESTAMP NULL COMMENT '提交时间' AFTER `approved_by`;

-- 13. 添加审批时间字段
ALTER TABLE `salaries` 
ADD COLUMN `approved_at` TIMESTAMP NULL COMMENT '审批时间' AFTER `submitted_at`;

-- 14. 添加发放时间字段
ALTER TABLE `salaries` 
ADD COLUMN `paid_at` TIMESTAMP NULL COMMENT '发放时间' AFTER `approved_at`;

-- 15. 添加拒绝原因字段
ALTER TABLE `salaries` 
ADD COLUMN `rejection_reason` TEXT NULL COMMENT '拒绝原因' AFTER `paid_at`;

-- ========================================
-- 验证字段是否添加成功
-- ========================================
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'salaries'
ORDER BY ORDINAL_POSITION;

