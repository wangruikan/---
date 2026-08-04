-- 发票项目配置、开票内容配置排序
-- 执行前请确认当前数据库为目标业务库。

SET @schema_name = DATABASE();

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.tables
      WHERE table_schema = @schema_name AND table_name = 'invoice_projects'
    )
    AND NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @schema_name AND table_name = 'invoice_projects' AND column_name = 'sort_order'
    ),
    'ALTER TABLE `invoice_projects` ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''排序'' AFTER `project_name`',
    'SELECT ''invoice_projects.sort_order already exists'' AS message'
  )
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.tables
      WHERE table_schema = @schema_name AND table_name = 'invoice_projects'
    )
    AND NOT EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema = @schema_name AND table_name = 'invoice_projects' AND index_name = 'idx_invoice_projects_account_sort'
    ),
    'ALTER TABLE `invoice_projects` ADD INDEX `idx_invoice_projects_account_sort` (`account_set_id`, `sort_order`)',
    'SELECT ''idx_invoice_projects_account_sort already exists'' AS message'
  )
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.tables
      WHERE table_schema = @schema_name AND table_name = 'invoice_content_configs'
    )
    AND NOT EXISTS (
      SELECT 1 FROM information_schema.columns
      WHERE table_schema = @schema_name AND table_name = 'invoice_content_configs' AND column_name = 'sort_order'
    ),
    'ALTER TABLE `invoice_content_configs` ADD COLUMN `sort_order` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT ''排序'' AFTER `project_name`',
    'SELECT ''invoice_content_configs.sort_order already exists'' AS message'
  )
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @sql = (
  SELECT IF(
    EXISTS (
      SELECT 1 FROM information_schema.tables
      WHERE table_schema = @schema_name AND table_name = 'invoice_content_configs'
    )
    AND NOT EXISTS (
      SELECT 1 FROM information_schema.statistics
      WHERE table_schema = @schema_name AND table_name = 'invoice_content_configs' AND index_name = 'idx_invoice_content_configs_account_sort'
    ),
    'ALTER TABLE `invoice_content_configs` ADD INDEX `idx_invoice_content_configs_account_sort` (`account_set_id`, `sort_order`)',
    'SELECT ''idx_invoice_content_configs_account_sort already exists'' AS message'
  )
);
PREPARE statement FROM @sql;
EXECUTE statement;
DEALLOCATE PREPARE statement;
