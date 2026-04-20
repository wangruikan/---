-- 创建地区网页入口管理表
CREATE TABLE `region_portals` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `region_name` varchar(100) NOT NULL COMMENT '地区名称',
  `business_type` varchar(100) NOT NULL COMMENT '业务类型（如：社保、公积金、税务等）',
  `portal_name` varchar(200) NOT NULL COMMENT '网站名称',
  `portal_url` varchar(500) NOT NULL COMMENT '网站地址',
  `remarks` text DEFAULT NULL COMMENT '备注说明',
  `sort_order` int(11) DEFAULT 0 COMMENT '排序序号',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '是否启用：1-启用，0-禁用',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '创建人ID',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '更新人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_region_name` (`region_name`),
  KEY `idx_business_type` (`business_type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='地区网页入口管理表';

