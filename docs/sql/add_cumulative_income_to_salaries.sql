-- 添加累计收入字段到salaries表
-- 执行日期：2024-10

-- 添加累计收入字段（用于存储今年1月到当前月的应发工资总和）
ALTER TABLE `salaries` ADD COLUMN `cumulative_income` DECIMAL(10, 2) DEFAULT 0 COMMENT '累计收入（今年1月至当前月应发工资总和）' AFTER `gross_salary`;

-- 添加工作天数字段（用于计算缺勤扣款）
ALTER TABLE `salaries` ADD COLUMN `work_days` INT DEFAULT 0 COMMENT '应出勤天数' AFTER `month`;

-- 添加实际出勤天数字段
ALTER TABLE `salaries` ADD COLUMN `actual_work_days` DECIMAL(5, 1) DEFAULT 0 COMMENT '实际出勤天数' AFTER `work_days`;

-- 添加缺勤天数字段
ALTER TABLE `salaries` ADD COLUMN `absent_days` DECIMAL(5, 1) DEFAULT 0 COMMENT '缺勤天数' AFTER `actual_work_days`;

-- 添加缺勤扣款字段
ALTER TABLE `salaries` ADD COLUMN `absent_deduction` DECIMAL(10, 2) DEFAULT 0 COMMENT '缺勤扣款' AFTER `absent_days`;

-- 如果缺少其他字段，也一并添加
ALTER TABLE `salaries` ADD COLUMN IF NOT EXISTS `department` VARCHAR(100) DEFAULT '' COMMENT '部门（项目名称）' AFTER `month`;
ALTER TABLE `salaries` ADD COLUMN IF NOT EXISTS `position` VARCHAR(100) DEFAULT '' COMMENT '岗位' AFTER `department`;
ALTER TABLE `salaries` ADD COLUMN IF NOT EXISTS `company_insurance_total` DECIMAL(10, 2) DEFAULT 0 COMMENT '单位保险合计' AFTER `housing_fund`;
ALTER TABLE `salaries` ADD COLUMN IF NOT EXISTS `personal_insurance_total` DECIMAL(10, 2) DEFAULT 0 COMMENT '个人保险合计' AFTER `company_insurance_total`;
ALTER TABLE `salaries` ADD COLUMN IF NOT EXISTS `compensation_total` DECIMAL(10, 2) DEFAULT 0 COMMENT '补差合计' AFTER `personal_insurance_total`;

-- 添加索引以提高查询性能
CREATE INDEX IF NOT EXISTS `idx_month` ON `salaries` (`month`);
CREATE INDEX IF NOT EXISTS `idx_employee_month` ON `salaries` (`employee_id`, `month`);

