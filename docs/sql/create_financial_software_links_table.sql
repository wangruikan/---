-- 创建财务软件链接表
CREATE TABLE `financial_software_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) unsigned NOT NULL COMMENT '账套ID',
  `name` varchar(100) NOT NULL COMMENT '软件名称',
  `url` varchar(500) NOT NULL COMMENT '软件地址',
  `sort_order` int(11) NOT NULL DEFAULT '0' COMMENT '排序序号',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT '创建人ID',
  `updated_by` bigint(20) unsigned DEFAULT NULL COMMENT '更新人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='财务软件链接表';
