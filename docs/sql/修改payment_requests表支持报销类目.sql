-- ============================================
-- 修改 payment_requests 表，支持报销类目作为付款类型
-- 执行时间：请立即执行
-- ============================================

-- 将 payment_type 从 enum 改为 VARCHAR，以支持报销类目（报销、差旅、采购、项目、其他等）
ALTER TABLE `payment_requests` 
MODIFY COLUMN `payment_type` VARCHAR(50) NOT NULL DEFAULT 'reimbursement' COMMENT '付款类型：salary/insurance/reimbursement/报销/差旅/采购/项目/其他';

-- 检查并添加 reimbursement_id 字段（如果不存在）
-- 注意：如果字段已存在，执行此语句会报错，可以忽略
-- 如果字段不存在，则添加字段和索引

-- 方法1：直接添加（如果字段已存在会报错，可以忽略）
-- ALTER TABLE `payment_requests` 
-- ADD COLUMN `reimbursement_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '报销申请ID（当payment_type为报销相关类型时使用）' AFTER `salary_approval_id`;

-- 方法2：使用存储过程检查（推荐）
-- 如果 reimbursement_id 字段不存在，则添加
SET @dbname = DATABASE();
SET @tablename = 'payment_requests';
SET @columnname = 'reimbursement_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1', -- 字段已存在，不执行任何操作
  CONCAT('ALTER TABLE `', @tablename, '` ADD COLUMN `', @columnname, '` bigint(20) UNSIGNED DEFAULT NULL COMMENT ''报销申请ID（当payment_type为报销相关类型时使用）'' AFTER `salary_approval_id`')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- 添加索引（如果不存在）
-- 注意：如果索引已存在，执行此语句会报错，可以忽略
SET @indexname = 'idx_reimbursement_id';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (index_name = @indexname)
  ) > 0,
  'SELECT 1', -- 索引已存在，不执行任何操作
  CONCAT('ALTER TABLE `', @tablename, '` ADD KEY `', @indexname, '` (`reimbursement_id`)')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================
-- 执行完成！
-- ============================================
-- 
-- 说明：
-- 1. payment_type 字段已改为 VARCHAR(50)，可以存储任意字符串
-- 2. 支持的值包括：salary, insurance, reimbursement, 报销, 差旅, 采购, 项目, 其他
-- 3. 如果报销的类目是"报销"，则 payment_type 也是"报销"
-- 4. 如果报销的类目是"差旅"，则 payment_type 也是"差旅"
-- 以此类推

