-- ============================================
-- 工资付款申请功能 - 数据库脚本
-- 执行时间：请在执行前备份数据库
-- ============================================

-- 1. 创建付款申请附件表
CREATE TABLE IF NOT EXISTS `payment_request_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_request_id` bigint(20) UNSIGNED NOT NULL COMMENT '付款申请ID',
  `filename` varchar(255) NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) NOT NULL COMMENT '文件存储路径',
  `file_size` bigint(20) NOT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(100) DEFAULT NULL COMMENT '文件MIME类型',
  `uploaded_by` bigint(20) UNSIGNED NOT NULL COMMENT '上传人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_request_id` (`payment_request_id`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款申请附件表';

-- 2. 添加 approval_instance_id 到 payment_requests 表（如果不存在）
SET @column_exists = (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'payment_requests'
    AND COLUMN_NAME = 'approval_instance_id'
);

SET @sql = IF(
  @column_exists = 0,
  'ALTER TABLE `payment_requests` ADD COLUMN `approval_instance_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT ''审批实例ID'' AFTER `salary_approval_id`',
  'SELECT ''Column approval_instance_id already exists in payment_requests'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. 创建 public/payment_requests 目录（通过应用层创建，此处仅作说明）
-- 该目录将用于存储付款申请的附件文件
-- 路径：public/payment_requests/{payment_request_id}/

-- ============================================
-- 执行完成提示
-- ============================================
SELECT '✅ 工资付款申请功能数据库脚本执行完成！' AS message;
SELECT '📁 请确保 public/payment_requests 目录存在并有写入权限' AS reminder;

