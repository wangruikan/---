-- 人员项目关系增加项目内工号
-- 用于支持员工调动后：旧项目保留旧工号，新项目生成新工号。

SET @column_exists := (
  SELECT COUNT(*)
  FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'employee_projects'
    AND COLUMN_NAME = 'employee_number'
);

SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `employee_projects` ADD COLUMN `employee_number` VARCHAR(50) NULL COMMENT ''项目内工号'' AFTER `project_id`',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `employee_projects` ep
JOIN `employees` e ON e.`id` = ep.`employee_id`
SET ep.`employee_number` = e.`employee_number`
WHERE ep.`status` = 'active'
  AND ep.`employee_number` IS NULL
  AND e.`employee_number` IS NOT NULL
  AND e.`employee_number` <> '';

SET @index_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'employee_projects'
    AND INDEX_NAME = 'idx_employee_projects_project_employee_number'
);

SET @sql := IF(
  @index_exists = 0,
  'CREATE INDEX `idx_employee_projects_project_employee_number` ON `employee_projects` (`project_id`, `employee_number`)',
  'SELECT 1'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
