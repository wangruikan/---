-- ============================================
-- 立即执行此SQL创建表
-- 数据库: weiqing
-- ============================================

-- 1. 先删除旧表（如果存在）
DROP TABLE IF EXISTS `payment_form_history`;

-- 2. 创建新表
CREATE TABLE `payment_form_history` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_account` varchar(100) NOT NULL COMMENT '银行账号（唯一标识）',
  `department` varchar(100) DEFAULT NULL COMMENT '所在部门',
  `payee` varchar(100) DEFAULT NULL COMMENT '支付对象',
  `amount_small` varchar(50) DEFAULT NULL COMMENT '小写金额',
  `amount_large` varchar(100) DEFAULT NULL COMMENT '大写金额',
  `payment_method` text DEFAULT NULL COMMENT '付款方式（JSON数组）',
  `bank` varchar(100) DEFAULT NULL COMMENT '开户行',
  `purpose` text DEFAULT NULL COMMENT '付款用途',
  `invoice_status` text DEFAULT NULL COMMENT '开票情况（JSON数组）',
  `user_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '创建用户ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bank_account` (`bank_account`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='付款申请单历史记忆表';

-- 3. 验证表是否创建成功
SELECT '✅ 表创建成功！' as message;

-- 4. 查看表结构
DESC payment_form_history;

-- 5. 查看当前数据
SELECT COUNT(*) as total_records FROM payment_form_history;

