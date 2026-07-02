-- 参保增减任务：补充任务月份、失败/终止状态
-- 执行前请先备份数据库

USE `weiqing`;
SET NAMES utf8mb4;
SET @db_name := DATABASE();

SELECT COUNT(*) INTO @task_month_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'insurance_changes'
  AND COLUMN_NAME = 'task_month';

SET @sql := IF(
  @task_month_exists = 0,
  'ALTER TABLE `insurance_changes` ADD COLUMN `task_month` VARCHAR(7) NULL COMMENT ''任务月份 YYYY-MM'' AFTER `account_set_id`',
  'SELECT ''task_month already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `insurance_changes`
SET `task_month` = DATE_FORMAT(`created_at`, '%Y-%m')
WHERE `task_month` IS NULL OR `task_month` = '';

ALTER TABLE `insurance_changes`
  MODIFY COLUMN `task_month` VARCHAR(7) NOT NULL COMMENT '任务月份 YYYY-MM';

SELECT COUNT(*) INTO @task_month_index_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'insurance_changes'
  AND INDEX_NAME = 'idx_insurance_changes_task_month';

SET @sql := IF(
  @task_month_index_exists = 0,
  'ALTER TABLE `insurance_changes` ADD INDEX `idx_insurance_changes_task_month` (`task_month`)',
  'SELECT ''idx_insurance_changes_task_month already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

ALTER TABLE `insurance_changes`
  MODIFY COLUMN `status` ENUM('pending','processing','submitted','completed','failed','terminated')
  NOT NULL DEFAULT 'pending'
  COMMENT '状态：待处理、处理中、待确认、成功、失败、终止';

ALTER TABLE `insurance_change_items`
  MODIFY COLUMN `status` ENUM('pending','submitted','completed','failed','terminated')
  NOT NULL DEFAULT 'pending'
  COMMENT '子任务状态';
