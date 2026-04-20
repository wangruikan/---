-- ============================================
-- 添加category字段到payment_requests和payment_summaries表
-- 执行时间：请立即执行
-- ============================================

-- 1. 添加category字段到payment_requests表
SET @dbname = DATABASE();
SET @tablename = 'payment_requests';
SET @columnname = 'category';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` VARCHAR(50) DEFAULT NULL COMMENT ''类目：报销/差旅/采购/项目/其他'' AFTER `payment_type`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 2. 添加category字段到payment_summaries表
SET @tablename = 'payment_summaries';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` VARCHAR(50) DEFAULT NULL COMMENT ''类目：报销/差旅/采购/项目/其他'' AFTER `payment_type`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

