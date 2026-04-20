-- 修复数据库中所有JSON字段为TEXT类型的SQL脚本
-- 适用于已经导入但有JSON字段错误的数据库

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ===== 修复所有可能的JSON字段 =====

-- 1. 修复 employees 表的JSON字段（如果存在）
ALTER TABLE `employees` 
MODIFY COLUMN `learning_resume` text NULL COMMENT '学习简历' AFTER `education`,
MODIFY COLUMN `work_experience` text NULL COMMENT '工作经历' AFTER `learning_resume`;

-- 2. 修复 personnel_change_requests 表的JSON字段
ALTER TABLE `personnel_change_requests` 
MODIFY COLUMN `personnel_list` text NOT NULL COMMENT '人员列表 JSON格式';

-- 3. 修复 approvals 表的JSON字段
ALTER TABLE `approvals` 
MODIFY COLUMN `attachments` text NULL;

-- 4. 修复 insurance_changes 表的JSON字段（我们之前已经转换过，但以防万一）
ALTER TABLE `insurance_changes` 
MODIFY COLUMN `social_security_types` text NULL,
MODIFY COLUMN `medical_insurance_types` text NULL,
MODIFY COLUMN `housing_fund_params` text NULL,
MODIFY COLUMN `large_medical_insurance_config` text NULL,
MODIFY COLUMN `other_insurance_policies` text NULL,
MODIFY COLUMN `change_details` text NULL,
MODIFY COLUMN `used_quotas` text NULL;

-- 5. 修复 insurance_personnel 表的JSON字段
ALTER TABLE `insurance_personnel` 
MODIFY COLUMN `social_security_types` text NULL,
MODIFY COLUMN `medical_insurance_types` text NULL,
MODIFY COLUMN `housing_fund_params` text NULL,
MODIFY COLUMN `large_medical_insurance_config` text NULL,
MODIFY COLUMN `other_insurance_policy_versions` text NULL;

-- 6. 修复 insurance_detail_records 表的JSON字段
ALTER TABLE `insurance_detail_records` 
MODIFY COLUMN `social_security_types` text NULL,
MODIFY COLUMN `medical_insurance_types` text NULL,
MODIFY COLUMN `housing_fund_params` text NULL,
MODIFY COLUMN `large_medical_insurance_config` text NULL,
MODIFY COLUMN `other_insurance_policies` text NULL;

-- ===== 修复索引长度问题 =====

-- 7. 修复 personnel_change_requests 表的索引
DROP INDEX IF EXISTS `unique_project_month_type` ON `personnel_change_requests`;
CREATE UNIQUE INDEX `unique_project_month_type` ON `personnel_change_requests` (`project_id`, `month`(50), `change_type`, `deleted_at`);

-- 8. 修复其他可能过长的索引（如果存在）
-- DROP INDEX IF EXISTS `some_other_long_index` ON `some_table`;
-- CREATE INDEX `some_other_long_index` ON `some_table` (`field1`, `field2`(50));

SET FOREIGN_KEY_CHECKS = 1;

-- ===== 验证修复结果 =====

-- 检查是否还有JSON字段
SELECT 
    '=== 剩余的JSON字段 ===' as info,
    '' as table_name,
    '' as column_name,
    '' as data_type
UNION ALL
SELECT 
    'JSON字段检查' as info,
    TABLE_NAME as table_name,
    COLUMN_NAME as column_name,
    DATA_TYPE as data_type
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'json'
ORDER BY table_name, column_name;

-- 检查已修复的字段
SELECT 
    '=== 已修复为TEXT的字段 ===' as info,
    '' as table_name,
    '' as column_name,
    '' as data_type
UNION ALL
SELECT 
    '已修复字段' as info,
    TABLE_NAME as table_name,
    COLUMN_NAME as column_name,
    DATA_TYPE as data_type
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'text'
    AND (
        (TABLE_NAME = 'employees' AND COLUMN_NAME IN ('learning_resume', 'work_experience'))
        OR (TABLE_NAME = 'personnel_change_requests' AND COLUMN_NAME = 'personnel_list')
        OR (TABLE_NAME = 'approvals' AND COLUMN_NAME = 'attachments')
        OR (TABLE_NAME = 'insurance_changes' AND COLUMN_NAME IN ('social_security_types', 'medical_insurance_types', 'housing_fund_params', 'large_medical_insurance_config', 'other_insurance_policies', 'change_details', 'used_quotas'))
        OR (TABLE_NAME = 'insurance_personnel' AND COLUMN_NAME IN ('social_security_types', 'medical_insurance_types', 'housing_fund_params', 'large_medical_insurance_config', 'other_insurance_policy_versions'))
        OR (TABLE_NAME = 'insurance_detail_records' AND COLUMN_NAME IN ('social_security_types', 'medical_insurance_types', 'housing_fund_params', 'large_medical_insurance_config', 'other_insurance_policies'))
    )
ORDER BY table_name, column_name;

-- 统计结果
SELECT 
    '=== 修复统计 ===' as info,
    '' as message
UNION ALL
SELECT 
    '统计结果' as info,
    CONCAT('共修复了 ', COUNT(*), ' 个字段为TEXT类型') as message
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'text'
    AND (
        COLUMN_NAME IN ('learning_resume', 'work_experience', 'personnel_list', 'attachments', 'social_security_types', 'medical_insurance_types', 'housing_fund_params', 'large_medical_insurance_config', 'other_insurance_policies', 'change_details', 'used_quotas', 'other_insurance_policy_versions')
    );

SELECT '修复完成！如果上面显示0个JSON字段，说明修复成功！' as result;
