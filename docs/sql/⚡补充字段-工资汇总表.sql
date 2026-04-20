-- ========================================
-- 补充工资汇总表字段（增加详细统计项）
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '基本工资合计' AFTER `employee_count`,
ADD COLUMN `total_absent_deduction` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '缺勤扣款合计' AFTER `total_basic_salary`,
ADD COLUMN `total_social_security_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '社保单位合计' AFTER `total_gross_salary`,
ADD COLUMN `total_social_security_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '社保个人合计' AFTER `total_social_security_company`,
ADD COLUMN `total_housing_fund_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '公积金单位合计' AFTER `total_social_security_personal`,
ADD COLUMN `total_housing_fund_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '公积金个人合计' AFTER `total_housing_fund_company`,
ADD COLUMN `total_large_medical_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '大额医疗单位合计' AFTER `total_housing_fund_personal`,
ADD COLUMN `total_cumulative_income` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计收入合计' AFTER `total_tax`,
ADD COLUMN `total_cumulative_basic_deduction` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计减除费用合计' AFTER `total_cumulative_income`,
ADD COLUMN `total_special_deduction` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '专项附加扣除合计' AFTER `total_cumulative_basic_deduction`;

-- 验证字段是否添加成功
DESCRIBE salary_summaries;

-- 查看表结构
SHOW FULL COLUMNS FROM salary_summaries;

