-- 审批流程配置表
-- 用于按账套、审批业务类型配置需要启用的审批节点。

CREATE TABLE IF NOT EXISTS `approval_flow_configs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` BIGINT UNSIGNED NOT NULL COMMENT '账套ID',
  `business_type` VARCHAR(100) NOT NULL COMMENT '审批业务类型',
  `enabled_levels` LONGTEXT NOT NULL COMMENT '启用的审批级别JSON数组，例如 [1,2,4]',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `approval_flow_configs_unique` (`account_set_id`, `business_type`),
  CONSTRAINT `approval_flow_configs_account_set_id_foreign`
    FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 权限：审批流程配置
INSERT INTO `permissions` (`module`, `action`, `name`, `sort_order`, `created_at`, `updated_at`)
VALUES
  ('approval_flow_configs', 'view', '审批流程配置-查看', 706, NOW(), NOW()),
  ('approval_flow_configs', 'update', '审批流程配置-编辑', 707, NOW(), NOW())
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `sort_order` = VALUES(`sort_order`),
  `updated_at` = NOW();
