-- 每月考核申诉表（MySQL 5.6 兼容）
CREATE TABLE `assessment_appeals` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `assessment_record_id` int(10) unsigned NOT NULL COMMENT '考核记录ID',
  `account_set_id` int(10) unsigned NOT NULL COMMENT '账套ID',
  `appellant_id` int(10) unsigned NOT NULL COMMENT '申诉人ID',
  `appellant_name` varchar(100) NOT NULL COMMENT '申诉人姓名',
  `description` text NOT NULL COMMENT '申诉说明',
  `images` text NOT NULL COMMENT '申诉图片路径(JSON)',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态: pending/approved/rejected',
  `reviewed_by` int(10) unsigned DEFAULT NULL COMMENT '审核人ID',
  `reviewed_at` datetime DEFAULT NULL COMMENT '审核时间',
  `review_remark` varchar(1000) DEFAULT NULL COMMENT '审核备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_assessment_record_id` (`assessment_record_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_appellant_id` (`appellant_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='考核申诉记录';
