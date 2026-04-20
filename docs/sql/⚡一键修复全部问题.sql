-- ========================================
-- 一键修复工资汇总NaN问题（完整版）
-- ========================================
-- 使用说明：
-- 1. 全选这个文件的所有SQL
-- 2. 在数据库工具中执行
-- 3. 如果遇到 "Duplicate column name" 错误，忽略继续
-- 4. 执行完成后按照最后的提示操作
-- ========================================

-- 步骤1：查看当前表结构
SELECT '========== 第一步：检查当前表结构 ==========' AS info;
SHOW COLUMNS FROM salary_summaries;

-- 步骤2：添加所有可能缺失的字段（如果报错字段已存在，可以忽略）
SELECT '========== 第二步：添加缺失字段 ==========' AS info;

-- 考勤相关
SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_work_days decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT "应出勤天数合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_work_days');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_work_days already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_actual_work_days decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT "实际出勤天数合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_actual_work_days');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_actual_work_days already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_absent_days decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT "缺勤天数合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_absent_days');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_absent_days already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_absent_deduction decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "缺勤扣款合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_absent_deduction');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_absent_deduction already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 工资相关
SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_basic_salary decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "基本工资合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_basic_salary');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_basic_salary already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 社保单位
SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_pension_company decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "养老保险单位合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_pension_company');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_pension_company already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_medical_company decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "医疗保险单位合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_medical_company');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_medical_company already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_unemployment_company decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "失业保险单位合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_unemployment_company');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_unemployment_company already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_work_injury_company decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "工伤保险单位合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_work_injury_company');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_work_injury_company already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_maternity_company decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "生育保险单位合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_maternity_company');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_maternity_company already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ⭐⭐⭐ 社保个人（最关键，修复NaN）⭐⭐⭐
SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_pension_personal decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "养老保险个人合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_pension_personal');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_pension_personal already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_medical_personal decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "医疗保险个人合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_medical_personal');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_medical_personal already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_unemployment_personal decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "失业保险个人合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_unemployment_personal');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_unemployment_personal already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 公积金
SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_housing_fund_company decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "公积金单位合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_housing_fund_company');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_housing_fund_company already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_housing_fund_personal decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "公积金个人合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_housing_fund_personal');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_housing_fund_personal already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 大额医疗
SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_large_medical_company decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "大额医疗单位合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_large_medical_company');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_large_medical_company already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_large_medical_personal decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "大额医疗个人合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_large_medical_personal');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_large_medical_personal already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 其他字段
SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_cumulative_income decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "累计收入合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_cumulative_income');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_cumulative_income already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_cumulative_basic_deduction decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "累计减除费用合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_cumulative_basic_deduction');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_cumulative_basic_deduction already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_cumulative_special_deduction_insurance decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "累计专项扣除合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_cumulative_special_deduction_insurance');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_cumulative_special_deduction_insurance already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN avg_tax_rate decimal(10,2) NULL COMMENT "平均税率"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'avg_tax_rate');
SET @sql = IF(@check = 0, @sql, 'SELECT "avg_tax_rate already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN avg_quick_deduction decimal(12,2) NULL COMMENT "平均速算扣除数"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'avg_quick_deduction');
SET @sql = IF(@check = 0, @sql, 'SELECT "avg_quick_deduction already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_cumulative_tax_payable decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "累计应扣缴税额合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_cumulative_tax_payable');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_cumulative_tax_payable already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_tax_already_withheld decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "已扣缴税额合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_tax_already_withheld');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_tax_already_withheld already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_social_security_compensation decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "社保补差合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_social_security_compensation');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_social_security_compensation already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_housing_fund_compensation decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "公积金补差合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_housing_fund_compensation');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_housing_fund_compensation already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_company_insurance_total decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "单位保险合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_company_insurance_total');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_company_insurance_total already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_personal_insurance_total decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "个人保险合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_personal_insurance_total');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_personal_insurance_total already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_special_deduction_monthly decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "当月专项附加扣除合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_special_deduction_monthly');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_special_deduction_monthly already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_special_deduction decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "累计专项附加扣除合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_special_deduction');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_special_deduction already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_taxable_income decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "应纳税所得额合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_taxable_income');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_taxable_income already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_cumulative_other_taxable decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "累计其他应纳税项合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_cumulative_other_taxable');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_cumulative_other_taxable already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_tax_payable_or_refundable decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "应补退税额合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_tax_payable_or_refundable');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_tax_payable_or_refundable already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_deductions decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "其他扣款合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_deductions');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_deductions already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = 'ALTER TABLE salary_summaries ADD COLUMN total_paid_salary decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT "实际发放合计"';
SET @check = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_summaries' AND COLUMN_NAME = 'total_paid_salary');
SET @sql = IF(@check = 0, @sql, 'SELECT "total_paid_salary already exists" AS msg');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 步骤3：验证关键字段是否存在
SELECT '========== 第三步：验证关键字段 ==========' AS info;
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE,
    COLUMN_DEFAULT,
    COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME IN (
    'total_pension_personal',
    'total_medical_personal',
    'total_unemployment_personal',
    'total_large_medical_personal',
    'total_work_injury_company',
    'total_maternity_company'
)
ORDER BY COLUMN_NAME;

-- 步骤4：删除旧的汇总记录
SELECT '========== 第四步：删除旧记录 ==========' AS info;
DELETE FROM salary_summaries;
SELECT '✅ 旧记录已删除' AS result;

-- 步骤5：完成提示
SELECT '========================================' AS '';
SELECT '✅ 数据库修复完成！' AS '';
SELECT '' AS '';
SELECT '接下来请按以下步骤操作：' AS '';
SELECT '1. 回到系统【审批管理】' AS '';
SELECT '2. 找一个状态为【已完成】的工资表审批' AS '';
SELECT '3. 重新走一遍审批流程（或审批一个新的工资表）' AS '';
SELECT '4. 系统会自动生成新的工资汇总记录' AS '';
SELECT '5. 刷新【工资汇总】页面查看' AS '';
SELECT '6. 所有NaN应该都显示为正常金额了' AS '';
SELECT '========================================' AS '';

