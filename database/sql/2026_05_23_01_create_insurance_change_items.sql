-- 参保增减按模块拆分：新增子任务表 + 附件关联字段
-- 执行前建议先备份数据库。

USE `weiqing`;
SET NAMES utf8mb4;
SET @db_name := DATABASE();

CREATE TABLE IF NOT EXISTS `insurance_change_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `insurance_change_id` BIGINT UNSIGNED NOT NULL COMMENT '参保增减主记录ID',
  `category` VARCHAR(50) NOT NULL COMMENT '模块: social_security/medical_insurance/housing_fund/large_medical_insurance/other_insurance',
  `change_type` ENUM('increase','decrease') NOT NULL DEFAULT 'increase' COMMENT '增减类型',
  `status` ENUM('pending','submitted','completed') NOT NULL DEFAULT 'pending' COMMENT '子任务状态',
  `category_snapshot` LONGTEXT NULL COMMENT '当前模块快照JSON',
  `change_details` LONGTEXT NULL COMMENT '当前模块变更详情JSON',
  `processed_by` BIGINT UNSIGNED NULL COMMENT '处理人',
  `processed_at` TIMESTAMP NULL COMMENT '处理时间',
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_insurance_change_item_category` (`insurance_change_id`, `category`),
  KEY `idx_insurance_change_items_category_status` (`category`, `status`),
  KEY `idx_insurance_change_items_status` (`status`),
  KEY `idx_insurance_change_items_processed_by` (`processed_by`),
  CONSTRAINT `fk_insurance_change_items_change`
    FOREIGN KEY (`insurance_change_id`) REFERENCES `insurance_changes` (`id`)
    ON DELETE CASCADE,
  CONSTRAINT `fk_insurance_change_items_processed_by`
    FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`)
    ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='参保增减模块子任务表';

-- insurance_change_attachments 增加 insurance_change_item_id（幂等）
SELECT COUNT(*) INTO @column_exists
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'insurance_change_attachments'
  AND COLUMN_NAME = 'insurance_change_item_id';

SET @sql := IF(
  @column_exists = 0,
  'ALTER TABLE `insurance_change_attachments` ADD COLUMN `insurance_change_item_id` BIGINT UNSIGNED NULL COMMENT ''参保增减子任务ID'' AFTER `insurance_change_id`',
  'SELECT ''insurance_change_item_id already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @index_exists
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = @db_name
  AND TABLE_NAME = 'insurance_change_attachments'
  AND INDEX_NAME = 'idx_insurance_change_attachments_item_id';

SET @sql := IF(
  @index_exists = 0,
  'ALTER TABLE `insurance_change_attachments` ADD KEY `idx_insurance_change_attachments_item_id` (`insurance_change_item_id`)',
  'SELECT ''idx_insurance_change_attachments_item_id already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @fk_exists
FROM information_schema.TABLE_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = @db_name
  AND TABLE_NAME = 'insurance_change_attachments'
  AND CONSTRAINT_NAME = 'fk_insurance_change_attachments_item';

SET @sql := IF(
  @fk_exists = 0,
  'ALTER TABLE `insurance_change_attachments` ADD CONSTRAINT `fk_insurance_change_attachments_item` FOREIGN KEY (`insurance_change_item_id`) REFERENCES `insurance_change_items` (`id`) ON DELETE CASCADE',
  'SELECT ''fk_insurance_change_attachments_item already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SHOW TABLES LIKE 'insurance_change_items';
SHOW COLUMNS FROM `insurance_change_attachments` LIKE 'insurance_change_item_id';
