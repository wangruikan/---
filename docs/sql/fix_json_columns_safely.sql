-- =============================================
-- 安全修复所有JSON字段为TEXT类型
-- 自动检查表字段是否存在，避免报错
-- 生成时间: 2024-03-12
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 创建临时存储过程来安全地修改字段
DELIMITER //
DROP PROCEDURE IF EXISTS safe_modify_column //
CREATE PROCEDURE safe_modify_column(IN db_name VARCHAR(100), IN table_name VARCHAR(100), IN column_name VARCHAR(100), 
                                  IN new_definition TEXT, IN after_column VARCHAR(100) DEFAULT '')
BEGIN
    DECLARE column_exists INT;
    DECLARE table_exists INT;
    DECLARE sql_text TEXT;
    
    -- 检查表是否存在
    SELECT COUNT(*) INTO table_exists 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = db_name AND TABLE_NAME = table_name;
    
    IF table_exists > 0 THEN
        -- 检查字段是否存在
        SELECT COUNT(*) INTO column_exists 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = db_name 
          AND TABLE_NAME = table_name 
          AND COLUMN_NAME = column_name;
        
        -- 如果字段存在，则修改它
        IF column_exists > 0 THEN
            SET @sql = CONCAT('ALTER TABLE `', table_name, '` MODIFY COLUMN `', column_name, '` ', new_definition);
            
            -- 添加AFTER子句（如果指定）
            IF after_column != '' THEN
                SET @sql = CONCAT(@sql, ' AFTER `', after_column, '`');
            END IF;
            
            SET @sql = CONCAT(@sql, ';');
            
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
            
            SELECT CONCAT('✓ 已修复: ', table_name, '.', column_name) AS message;
        ELSE
            SELECT CONCAT('ℹ️  跳过: ', table_name, '.', column_name, ' (字段不存在)') AS message;
        END IF;
    ELSE
        SELECT CONCAT('ℹ️  跳过: 表 ', table_name, ' 不存在') AS message;
    END IF;
END //

-- 修复所有表的JSON字段
DELIMITER ;

-- ===== 修复所有可能的JSON字段 =====

-- 1. 修复 employees 表的JSON字段
CALL safe_modify_column(DATABASE(), 'employees', 'learning_resume', 'TEXT NULL COMMENT ''学习简历''', 'education');
CALL safe_modify_column(DATABASE(), 'employees', 'work_experience', 'TEXT NULL COMMENT ''工作经历''', 'learning_resume');

-- 2. 修复 personnel_change_requests 表的JSON字段
CALL safe_modify_column(DATABASE(), 'personnel_change_requests', 'personnel_list', 'TEXT NOT NULL COMMENT ''人员列表 JSON格式''');

-- 3. 修复 approvals 表的JSON字段
CALL safe_modify_column(DATABASE(), 'approvals', 'attachments', 'TEXT NULL');

-- 4. 修复 insurance_changes 表的JSON字段
CALL safe_modify_column(DATABASE(), 'insurance_changes', 'social_security_types', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_changes', 'medical_insurance_types', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_changes', 'housing_fund_params', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_changes', 'large_medical_insurance_config', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_changes', 'other_insurance_policies', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_changes', 'change_details', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_changes', 'used_quotas', 'TEXT NULL');

-- 5. 修复 insurance_personnel 表的JSON字段
CALL safe_modify_column(DATABASE(), 'insurance_personnel', 'social_security_types', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_personnel', 'medical_insurance_types', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_personnel', 'housing_fund_params', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_personnel', 'large_medical_insurance_config', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_personnel', 'other_insurance_policy_versions', 'TEXT NULL');

-- 6. 修复 insurance_detail_records 表的JSON字段
CALL safe_modify_column(DATABASE(), 'insurance_detail_records', 'social_security_types', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_detail_records', 'medical_insurance_types', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_detail_records', 'housing_fund_params', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_detail_records', 'large_medical_insurance_config', 'TEXT NULL');
CALL safe_modify_column(DATABASE(), 'insurance_detail_records', 'other_insurance_policies', 'TEXT NULL');

-- ===== 修复索引长度问题 =====

-- 7. 修复 personnel_change_requests 表的索引
SET @table_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'personnel_change_requests');

SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'personnel_change_requests' 
                   AND INDEX_NAME = 'unique_project_month_type');

-- 重建索引
SET @sql = IF(@table_exists > 0 AND @index_exists > 0, 
    'DROP INDEX `unique_project_month_type` ON `personnel_change_requests`', 
    'SELECT ''personnel_change_requests 表或索引不存在，跳过删除索引'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 创建新的索引（限制month字段长度）
SET @sql = IF(@table_exists > 0, 
    'CREATE UNIQUE INDEX `unique_project_month_type` ON `personnel_change_requests` (`project_id`, `month`(50), `change_type`, `deleted_at`)', 
    'SELECT ''personnel_change_requests 表不存在，跳过创建索引'' as message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ===== 清理临时存储过程 =====
DROP PROCEDURE IF EXISTS safe_modify_column;

-- ===== 验证修复结果 =====

-- 检查是否还有JSON字段
SELECT '=== 剩余的JSON字段 ===' AS check_type, 
       TABLE_NAME, 
       COLUMN_NAME, 
       DATA_TYPE,
       CASE 
           WHEN DATA_TYPE = 'json' THEN '❌ 需要修复'
           ELSE '✅ 已修复'
       END AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'json'
ORDER BY TABLE_NAME, COLUMN_NAME;

-- 检查已修复的字段
SELECT '=== 已修复为TEXT的字段 ===' AS check_type,
       TABLE_NAME, 
       COLUMN_NAME, 
       DATA_TYPE,
       '✅ 已修复' AS status
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'text'
    AND (
        COLUMN_NAME IN ('learning_resume', 'work_experience', 'personnel_list', 'attachments', 
                       'social_security_types', 'medical_insurance_types', 'housing_fund_params', 
                       'large_medical_insurance_config', 'other_insurance_policies', 
                       'change_details', 'used_quotas', 'other_insurance_policy_versions')
    )
ORDER BY TABLE_NAME, COLUMN_NAME;

-- 统计结果
SELECT '=== 修复统计 ===' AS check_type,
       CONCAT('共修复了 ', COUNT(*), ' 个字段为TEXT类型') AS result
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'text'
    AND (
        COLUMN_NAME IN ('learning_resume', 'work_experience', 'personnel_list', 'attachments', 
                       'social_security_types', 'medical_insurance_types', 'housing_fund_params', 
                       'large_medical_insurance_config', 'other_insurance_policies', 
                       'change_details', 'used_quotas', 'other_insurance_policy_versions')
    )
UNION ALL
SELECT '=== 修复统计 ===' AS check_type,
       CONCAT('剩余 ', COUNT(*), ' 个JSON字段需要修复') AS result
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE()
    AND DATA_TYPE = 'json';

SET FOREIGN_KEY_CHECKS = 1;

SELECT '✅ 修复完成！请查看上面的统计信息。' AS final_result;
