-- ===================================================================
-- 项目考勤设置功能 - 数据库更新脚本
-- ===================================================================
-- 功能说明：
-- 1. 在项目表中添加"是否需要考勤"字段
-- 2. 默认值为1（需要考勤），保持向后兼容
-- 3. 可以在项目设置中修改此选项
-- 4. 工资表生成时会根据此设置判断是否需要考勤审批
-- ===================================================================

-- 检查字段是否已存在，如果不存在则添加
SET @col_exists = 0;
SELECT COUNT(*) INTO @col_exists 
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'projects' 
  AND COLUMN_NAME = 'require_attendance';

SET @sql = IF(@col_exists = 0,
    'ALTER TABLE `projects` ADD COLUMN `require_attendance` TINYINT(1) NOT NULL DEFAULT 1 COMMENT ''是否需要考勤：1-需要，0-不需要'' AFTER `status`',
    'SELECT ''字段 require_attendance 已存在，跳过添加'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 显示执行结果
SELECT 
    CASE 
        WHEN @col_exists = 0 THEN '✅ 字段添加成功！'
        ELSE '✅ 字段已存在，无需添加'
    END AS result,
    '项目考勤设置功能已部署完成' AS status;

-- ===================================================================
-- 使用说明：
-- ===================================================================
-- 1. 在Navicat中打开此SQL文件
-- 2. 选择正确的数据库（通常是 weiqing 或 hrms）
-- 3. 点击"运行"按钮执行
-- 4. 查看执行结果，确认字段添加成功
-- 
-- 功能使用：
-- - 在"项目管理"中可以设置项目是否需要考勤
-- - 需要考勤的项目：必须先审批考勤表才能生成工资表
-- - 无需考勤的项目：可以直接生成工资表
-- ===================================================================

