-- ========================================
-- 快速修复：只添加导致NaN的关键字段
-- ========================================
-- 说明：逐条执行，如果某条报错"字段已存在"，跳过继续执行下一条即可
-- ========================================

-- ⭐ 修复 "个人社保-社保" 显示NaN
ALTER TABLE `salary_summaries` ADD COLUMN `total_pension_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '养老保险个人合计';

ALTER TABLE `salary_summaries` ADD COLUMN `total_medical_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '医疗保险个人合计';

ALTER TABLE `salary_summaries` ADD COLUMN `total_unemployment_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '失业保险个人合计';

-- ⭐ 修复 "个人社保-大额" 显示NaN  
ALTER TABLE `salary_summaries` ADD COLUMN `total_large_medical_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '大额医疗个人合计';

-- ⭐ 修复 "其他扣除" 显示NaN
ALTER TABLE `salary_summaries` ADD COLUMN `total_work_injury_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '工伤保险单位合计';

ALTER TABLE `salary_summaries` ADD COLUMN `total_maternity_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '生育保险单位合计';

-- ========================================
-- 验证修复结果
-- ========================================
SELECT '========== 验证字段是否添加成功 ==========' AS info;

SELECT COLUMN_NAME, DATA_TYPE, COLUMN_COMMENT 
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

-- ========================================
-- 完成提示
-- ========================================
SELECT '✅ 关键字段添加完成！' AS '';
SELECT '现在执行下面的SQL删除旧记录：' AS '';
SELECT 'DELETE FROM salary_summaries;' AS '';
SELECT '然后重新审批工资表，刷新页面即可' AS '';

