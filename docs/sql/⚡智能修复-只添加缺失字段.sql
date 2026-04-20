-- ========================================
-- 智能修复：只添加缺失的字段
-- ========================================
-- 说明：先检查哪些字段缺失，然后只添加缺失的字段
-- ========================================

-- 第一步：查看当前缺少哪些字段
SELECT '========== 检查缺失字段 ==========' AS info;

SELECT 
    '需要的字段' AS field_name,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ 已存在'
        ELSE '❌ 缺失'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_pension_company'

UNION ALL

SELECT 
    'total_medical_company' AS field_name,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ 已存在'
        ELSE '❌ 缺失'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_medical_company'

UNION ALL

SELECT 
    'total_unemployment_company' AS field_name,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ 已存在'
        ELSE '❌ 缺失'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_unemployment_company'

UNION ALL

SELECT 
    'total_work_injury_company' AS field_name,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ 已存在'
        ELSE '❌ 缺失'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_work_injury_company'

UNION ALL

SELECT 
    'total_maternity_company' AS field_name,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ 已存在'
        ELSE '❌ 缺失'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_maternity_company'

UNION ALL

SELECT 
    'total_pension_personal' AS field_name,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ 已存在'
        ELSE '❌ 缺失'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_pension_personal'

UNION ALL

SELECT 
    'total_medical_personal' AS field_name,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ 已存在'
        ELSE '❌ 缺失'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_medical_personal'

UNION ALL

SELECT 
    'total_unemployment_personal' AS field_name,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ 已存在'
        ELSE '❌ 缺失'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_unemployment_personal'

UNION ALL

SELECT 
    'total_large_medical_personal' AS field_name,
    CASE 
        WHEN COUNT(*) > 0 THEN '✅ 已存在'
        ELSE '❌ 缺失'
    END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_large_medical_personal';

-- ========================================
-- 第二步：根据上面的检查结果，手动复制需要的SQL执行
-- ========================================

-- 如果 total_work_injury_company 显示 ❌ 缺失，执行这条：
-- ALTER TABLE `salary_summaries` ADD COLUMN `total_work_injury_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '工伤保险单位合计';

-- 如果 total_maternity_company 显示 ❌ 缺失，执行这条：
-- ALTER TABLE `salary_summaries` ADD COLUMN `total_maternity_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '生育保险单位合计';

-- 如果 total_pension_personal 显示 ❌ 缺失，执行这条：
-- ALTER TABLE `salary_summaries` ADD COLUMN `total_pension_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '养老保险个人合计';

-- 如果 total_medical_personal 显示 ❌ 缺失，执行这条：
-- ALTER TABLE `salary_summaries` ADD COLUMN `total_medical_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '医疗保险个人合计';

-- 如果 total_unemployment_personal 显示 ❌ 缺失，执行这条：
-- ALTER TABLE `salary_summaries` ADD COLUMN `total_unemployment_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '失业保险个人合计';

-- 如果 total_large_medical_personal 显示 ❌ 缺失，执行这条：
-- ALTER TABLE `salary_summaries` ADD COLUMN `total_large_medical_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '大额医疗个人合计';

