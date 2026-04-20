-- ========================================
-- 完整修复工资汇总表：添加所有缺失字段
-- ========================================
-- 说明：
-- 1. 如果工资汇总显示 NaN，说明数据库中缺少相关字段
-- 2. 按顺序执行此SQL，如果某个字段已存在会报错，可忽略继续执行
-- 3. 执行完成后刷新页面，NaN应该显示为 ¥0.00
-- ========================================

-- 先查看当前表结构
SELECT '========== 当前表结构 ==========' AS info;
DESCRIBE salary_summaries;

-- ========================================
-- 第一步：添加考勤相关字段
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_work_days` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '应出勤天数合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_actual_work_days` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际出勤天数合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_absent_days` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '缺勤天数合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_absent_deduction` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '缺勤扣款合计';

-- ========================================
-- 第二步：添加工资相关字段
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '基本工资合计';

-- ========================================
-- 第三步：添加累计相关字段
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_cumulative_income` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计收入合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_cumulative_basic_deduction` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计减除费用合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_cumulative_special_deduction_insurance` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计专项扣除合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `avg_tax_rate` decimal(10,2) NULL COMMENT '平均税率';

ALTER TABLE `salary_summaries`
ADD COLUMN `avg_quick_deduction` decimal(12,2) NULL COMMENT '平均速算扣除数';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_cumulative_tax_payable` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计应扣缴税额合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_tax_already_withheld` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '已扣缴税额合计';

-- ========================================
-- 第四步：添加社保单位部分（按险种细分）
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_pension_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '养老保险单位合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_medical_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '医疗保险单位合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_unemployment_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '失业保险单位合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_work_injury_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '工伤保险单位合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_maternity_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '生育保险单位合计';

-- ========================================
-- 第五步：添加社保个人部分（按险种细分）⭐ 重点：修复NaN
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_pension_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '养老保险个人合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_medical_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '医疗保险个人合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_unemployment_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '失业保险个人合计';

-- ========================================
-- 第六步：添加公积金字段
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_housing_fund_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '公积金单位合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_housing_fund_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '公积金个人合计';

-- ========================================
-- 第七步：添加大额医疗字段 ⭐ 重点：修复NaN
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_large_medical_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '大额医疗单位合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_large_medical_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '大额医疗个人合计';

-- ========================================
-- 第八步：添加社保公积金补差字段
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_social_security_compensation` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '社保补差合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_housing_fund_compensation` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '公积金补差合计';

-- ========================================
-- 第九步：添加保险合计字段
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_company_insurance_total` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '单位保险合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_personal_insurance_total` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '个人保险合计';

-- ========================================
-- 第十步：添加专项扣除字段
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_special_deduction_monthly` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '当月专项附加扣除合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_special_deduction` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计专项附加扣除合计';

-- ========================================
-- 第十一步：添加税务相关字段
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_taxable_income` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '应纳税所得额合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_cumulative_other_taxable` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计其他应纳税项合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_tax_payable_or_refundable` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '应补退税额合计';

-- ========================================
-- 第十二步：添加其他扣款和实发工资字段
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `total_deductions` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '其他扣款合计';

ALTER TABLE `salary_summaries`
ADD COLUMN `total_paid_salary` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '实际发放合计';

-- ========================================
-- 验证字段是否添加成功
-- ========================================

SELECT '========== 添加完成，验证结果 ==========' AS info;

SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, IS_NULLABLE, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME LIKE 'total_%'
ORDER BY ORDINAL_POSITION;

-- 查看完整表结构
SELECT '========== 完整表结构 ==========' AS info;
DESCRIBE salary_summaries;

-- ========================================
-- 完成提示
-- ========================================
SELECT '========================================' AS '';
SELECT '✅ SQL执行完成！' AS '';
SELECT '如果看到某些字段已存在的错误，可以忽略' AS '';
SELECT '现在删除旧的工资汇总记录，重新审批工资表即可' AS '';
SELECT '========================================' AS '';

