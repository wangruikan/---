-- ========================================
-- 修复工资汇总表：添加险种细分字段
-- ========================================
-- 说明：如果工资汇总显示 NaN，说明数据库中缺少这些字段
-- 执行此SQL即可修复（如果字段已存在会报错，可忽略）
-- ========================================

-- 先查看当前表结构
SELECT '当前表结构' AS info;
DESCRIBE salary_summaries;

-- ========================================
-- 添加社保单位部分（按险种细分）
-- ========================================

-- 养老保险单位
ALTER TABLE `salary_summaries`
ADD COLUMN `total_pension_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '养老保险单位合计';

-- 医疗保险单位
ALTER TABLE `salary_summaries`
ADD COLUMN `total_medical_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '医疗保险单位合计';

-- 失业保险单位
ALTER TABLE `salary_summaries`
ADD COLUMN `total_unemployment_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '失业保险单位合计';

-- 工伤保险单位
ALTER TABLE `salary_summaries`
ADD COLUMN `total_work_injury_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '工伤保险单位合计';

-- 生育保险单位
ALTER TABLE `salary_summaries`
ADD COLUMN `total_maternity_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '生育保险单位合计';

-- ========================================
-- 添加社保个人部分（按险种细分）
-- ========================================

-- 养老保险个人
ALTER TABLE `salary_summaries`
ADD COLUMN `total_pension_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '养老保险个人合计';

-- 医疗保险个人
ALTER TABLE `salary_summaries`
ADD COLUMN `total_medical_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '医疗保险个人合计';

-- 失业保险个人
ALTER TABLE `salary_summaries`
ADD COLUMN `total_unemployment_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '失业保险个人合计';

-- ========================================
-- 添加大额医疗个人部分
-- ========================================

-- 大额医疗个人（如果不存在）
ALTER TABLE `salary_summaries`
ADD COLUMN `total_large_medical_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '大额医疗个人合计';

-- ========================================
-- 验证字段是否添加成功
-- ========================================

SELECT '添加完成，验证结果' AS info;

SELECT COLUMN_NAME, DATA_TYPE, COLUMN_DEFAULT, COLUMN_COMMENT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND (COLUMN_NAME LIKE 'total_%personal' OR COLUMN_NAME LIKE 'total_%company')
ORDER BY ORDINAL_POSITION;

-- 查看完整表结构
SELECT '完整表结构' AS info;
DESCRIBE salary_summaries;

