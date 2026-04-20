-- ========================================
-- 工资表税务字段 + 审批功能 - 完整SQL脚本
-- 执行时间：2025-10-28
-- ========================================

-- 1. 添加累计应扣缴税额字段
-- ========================================
ALTER TABLE `salaries` 
ADD COLUMN `cumulative_tax_payable` DECIMAL(10, 2) DEFAULT 0 
COMMENT '累计应扣缴税额（累计应纳税所得额×税率-速算扣除数）' 
AFTER `quick_deduction`;

-- 2. 添加已扣缴税额字段
-- ========================================
ALTER TABLE `salaries` 
ADD COLUMN `tax_already_withheld` DECIMAL(10, 2) DEFAULT 0 
COMMENT '已扣缴税额（1月到上月的累计应扣缴税额之和）' 
AFTER `cumulative_tax_payable`;

-- 3. 创建工资表审批表
-- ========================================
CREATE TABLE `salary_approvals` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '项目ID',
  `month` varchar(7) NOT NULL COMMENT '工资期间（YYYY-MM）',
  `approval_type` enum('online', 'offline') NOT NULL DEFAULT 'online' COMMENT '审批方式：线上/线下',
  `status` enum('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' COMMENT '审批状态',
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '提交人ID',
  `submitted_at` datetime DEFAULT NULL COMMENT '提交时间',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '审批人ID',
  `approved_at` datetime DEFAULT NULL COMMENT '审批时间',
  `rejection_reason` text DEFAULT NULL COMMENT '拒绝原因',
  `remarks` text DEFAULT NULL COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_month` (`month`),
  KEY `idx_status` (`status`),
  UNIQUE KEY `unique_project_month` (`project_id`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资表审批记录';

-- 4. 为工资表添加审批ID字段
-- ========================================
ALTER TABLE `salaries` 
ADD COLUMN `salary_approval_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '工资表审批ID' AFTER `insurance_import_setting`,
ADD KEY `idx_salary_approval_id` (`salary_approval_id`);

-- 5. 创建付款申请表
-- ========================================
CREATE TABLE `payment_requests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_type` enum('salary', 'insurance') NOT NULL DEFAULT 'insurance' COMMENT '付款类型：工资/保险',
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `insurance_summary_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '保险汇总ID（当payment_type=insurance时使用）',
  `salary_approval_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '工资表审批ID（当payment_type=salary时使用）',
  `amount` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '付款金额',
  `status` enum('pending', 'approved', 'rejected', 'paid') NOT NULL DEFAULT 'pending' COMMENT '状态：待审批/已通过/已拒绝/已付款',
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '提交人ID',
  `submitted_at` datetime DEFAULT NULL COMMENT '提交时间',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '审批人ID',
  `approved_at` datetime DEFAULT NULL COMMENT '审批时间',
  `paid_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '付款人ID',
  `paid_at` datetime DEFAULT NULL COMMENT '付款时间',
  `rejection_reason` text DEFAULT NULL COMMENT '拒绝原因',
  `remarks` text DEFAULT NULL COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_insurance_summary_id` (`insurance_summary_id`),
  KEY `idx_salary_approval_id` (`salary_approval_id`),
  KEY `idx_status` (`status`),
  KEY `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款申请表';

-- ========================================
-- 执行完成！
-- ========================================
-- 
-- 新增功能：
-- 1. ✅ 累计应扣缴税额
-- 2. ✅ 已扣缴税额
-- 3. ✅ 应补（退）税额（自动计算）
-- 4. ✅ 工资表审批流程
-- 5. ✅ 付款申请管理
-- 
-- 新增表：
-- - salary_approvals（工资表审批记录）
-- - payment_requests（付款申请）
-- 
-- 修改表：
-- - salaries（新增3个字段）
-- 
-- ========================================

