-- 完整的SQL兼容性修复脚本
-- 修复所有JSON字段和字符集问题

-- 1. 批量替换规则（在文本编辑器中使用）
/*
需要在SQL文件中进行以下替换：

1. 替换字符集排序规则：
   查找: utf8mb4_0900_ai_ci
   替换为: utf8mb4_unicode_ci

2. 替换JSON字段类型：
   查找: json NOT NULL
   替换为: text NOT NULL
   
   查找: json NULL
   替换为: text NULL
   
   查找: ` json `
   替换为: ` text `

3. 修复特定的JSON字段（如果上面的通用替换没有覆盖到）：
   查找: `learning_resume` json NULL COMMENT '学习简历'
   替换为: `learning_resume` text NULL COMMENT '学习简历'
   
   查找: `work_experience` json NULL COMMENT '工作经历'
   替换为: `work_experience` text NULL COMMENT '工作经历'
   
   查找: `personnel_list` json NOT NULL COMMENT '人员列表'
   替换为: `personnel_list` text NOT NULL COMMENT '人员列表'

4. 修复可能的索引长度问题：
   查找: UNIQUE INDEX `unique_project_month_type`(`project_id`, `month`, `change_type`, `deleted_at`)
   替换为: UNIQUE INDEX `unique_project_month_type`(`project_id`, `month`(50), `change_type`, `deleted_at`)
*/

-- 2. 如果数据库已经创建，使用以下SQL修复现有表
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 只修复实际存在的表和字段

-- 1. 检查并修复 personnel_change_requests 表的JSON字段（如果表存在）
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'personnel_change_requests');

-- 如果表存在，修复JSON字段
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                      WHERE TABLE_SCHEMA = DATABASE() 
                      AND TABLE_NAME = 'personnel_change_requests' 
                      AND COLUMN_NAME = 'personnel_list' 
                      AND DATA_TYPE = 'json');

-- 修复 personnel_list 字段
SET @sql = IF(@column_exists > 0, 
    'ALTER TABLE `personnel_change_requests` MODIFY COLUMN `personnel_list` text NOT NULL COMMENT ''人员列表 JSON格式''', 
    'SELECT ''personnel_change_requests.personnel_list 字段不需要修复'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. 修复索引长度问题（如果表存在）
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = 'personnel_change_requests' 
                     AND INDEX_NAME = 'unique_project_month_type');

-- 重建索引
SET @sql = IF(@index_exists > 0, 
    'DROP INDEX `unique_project_month_type` ON `personnel_change_requests`', 
    'SELECT ''索引不存在，跳过删除'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 创建新的索引（限制month字段长度）
SET @sql = IF(@table_exists > 0, 
    'CREATE UNIQUE INDEX `unique_project_month_type` ON `personnel_change_requests` (`project_id`, `month`(50), `change_type`, `deleted_at`)', 
    'SELECT ''表不存在，跳过创建索引'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. 检查其他可能的JSON字段
-- 检查 approvals 表的 attachments 字段
SET @approvals_json = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                       WHERE TABLE_SCHEMA = DATABASE() 
                       AND TABLE_NAME = 'approvals' 
                       AND COLUMN_NAME = 'attachments' 
                       AND DATA_TYPE = 'json');

SET @sql = IF(@approvals_json > 0, 
    'ALTER TABLE `approvals` MODIFY COLUMN `attachments` text NULL', 
    'SELECT ''approvals.attachments 字段不需要修复'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET FOREIGN_KEY_CHECKS = 1;

-- 3. 验证修复结果
SELECT 
    '=== 检查所有可能的JSON字段 ===' as info,
    '' as table_name,
    '' as column_name,
    '' as data_type;

-- 查找所有JSON类型的字段
SELECT 
    TABLE_NAME as table_name,
    COLUMN_NAME as column_name,
    DATA_TYPE as data_type,
    COLUMN_COMMENT as comment
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'json'
ORDER BY TABLE_NAME, COLUMN_NAME;

-- 检查特定的字段是否已转换为TEXT
SELECT 
    TABLE_NAME as table_name,
    COLUMN_NAME as column_name,
    DATA_TYPE as data_type,
    COLUMN_COMMENT as comment
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND (
        (TABLE_NAME = 'employees' AND COLUMN_NAME IN ('learning_resume', 'work_experience'))
        OR (TABLE_NAME = 'personnel_change_requests' AND COLUMN_NAME = 'personnel_list')
        OR (TABLE_NAME = 'insurance_changes' AND COLUMN_NAME IN ('social_security_types', 'medical_insurance_types', 'housing_fund_params', 'large_medical_insurance_config', 'other_insurance_policies', 'change_details', 'used_quotas'))
        OR (TABLE_NAME = 'insurance_personnel' AND COLUMN_NAME IN ('social_security_types', 'medical_insurance_types', 'housing_fund_params', 'large_medical_insurance_config', 'other_insurance_policy_versions'))
        OR (TABLE_NAME = 'insurance_detail_records' AND COLUMN_NAME IN ('social_security_types', 'medical_insurance_types', 'housing_fund_params', 'large_medical_insurance_config', 'other_insurance_policies'))
    )
ORDER BY TABLE_NAME, COLUMN_NAME;

-- 统计结果
SELECT 
    CONCAT('总共找到 ', COUNT(*), ' 个JSON字段需要转换') as summary
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'json';
