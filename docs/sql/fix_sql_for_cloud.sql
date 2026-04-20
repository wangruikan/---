-- 修复云服务器SQL兼容性问题
-- 使用前请先备份数据库！

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. 修复 personnel_change_requests 表的 JSON 字段
-- 如果表已存在，修改字段类型
ALTER TABLE `personnel_change_requests` 
MODIFY COLUMN `personnel_list` TEXT NOT NULL COMMENT '人员列表 JSON格式 [{"id_card":"xxx", "name":"xxx"}]';

-- 2. 修复可能的索引长度问题
-- 删除过长的索引并重新创建
DROP INDEX IF EXISTS `unique_project_month_type` ON `personnel_change_requests`;
CREATE UNIQUE INDEX `unique_project_month_type` ON `personnel_change_requests` (`project_id`, `month`(50), `change_type`, `deleted_at`);

-- 3. 修复 salary_summaries 表的索引问题（如果存在）
-- 这个表可能有过长的复合索引
-- DROP INDEX IF EXISTS `some_long_index_name` ON `salary_summaries`;

-- 4. 确保所有JSON字段都是TEXT类型
-- 检查其他可能的JSON字段
-- ALTER TABLE `approvals` MODIFY COLUMN `attachments` TEXT NULL;

-- 5. 修复字符集问题（如果需要）
-- ALTER DATABASE `weiqing_new1` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- 验证JSON转TEXT的修复结果
-- 查询所有应该是TEXT类型的字段

-- 1. 检查 insurance_changes 表的字段类型
SELECT 
    'insurance_changes' as table_name,
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'insurance_changes'
    AND COLUMN_NAME IN (
        'social_security_types', 
        'medical_insurance_types', 
        'housing_fund_params', 
        'large_medical_insurance_config', 
        'other_insurance_policies', 
        'change_details', 
        'used_quotas'
    )
ORDER BY COLUMN_NAME

UNION ALL

-- 2. 检查 insurance_personnel 表的字段类型
SELECT 
    'insurance_personnel' as table_name,
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'insurance_personnel'
    AND COLUMN_NAME IN (
        'social_security_types', 
        'medical_insurance_types', 
        'housing_fund_params', 
        'large_medical_insurance_config', 
        'other_insurance_policy_versions'
    )
ORDER BY COLUMN_NAME

UNION ALL

-- 3. 检查 insurance_detail_records 表的字段类型
SELECT 
    'insurance_detail_records' as table_name,
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'insurance_detail_records'
    AND COLUMN_NAME IN (
        'social_security_types', 
        'medical_insurance_types', 
        'housing_fund_params', 
        'large_medical_insurance_config', 
        'other_insurance_policies'
    )
ORDER BY COLUMN_NAME

UNION ALL

-- 4. 检查其他可能的JSON字段
SELECT 
    TABLE_NAME,
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'json'
ORDER BY TABLE_NAME, COLUMN_NAME;

-- 5. 统计结果
SELECT 
    '=== 统计结果 ===' as info,
    '' as table_name,
    '' as COLUMN_NAME,
    '' as DATA_TYPE,
    '' as IS_NULLABLE,
    '' as COLUMN_COMMENT

UNION ALL

SELECT 
    CONCAT('TEXT字段总数: ', COUNT(*)) as info,
    '' as table_name,
    '' as COLUMN_NAME,
    '' as DATA_TYPE,
    '' as IS_NULLABLE,
    '' as COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME IN ('insurance_changes', 'insurance_personnel', 'insurance_detail_records')
    AND COLUMN_NAME IN (
        'social_security_types', 'medical_insurance_types', 'housing_fund_params', 
        'large_medical_insurance_config', 'other_insurance_policies', 
        'change_details', 'used_quotas', 'other_insurance_policy_versions'
    )
    AND DATA_TYPE = 'text'

UNION ALL

SELECT 
    CONCAT('JSON字段剩余: ', COUNT(*)) as info,
    '' as table_name,
    '' as COLUMN_NAME,
    '' as DATA_TYPE,
    '' as IS_NULLABLE,
    '' as COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'json';
