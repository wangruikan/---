-- ===========================================================================
-- 发票管理模块 - 数据库表结构
-- 创建时间: 2025-11-02
-- ===========================================================================

-- 1. 发票项目配置表
CREATE TABLE IF NOT EXISTS `invoice_projects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` BIGINT UNSIGNED NOT NULL COMMENT '账套ID',
  `project_name` VARCHAR(255) NOT NULL COMMENT '项目名称',
  `remark` TEXT NULL COMMENT '备注',
  `created_by` BIGINT UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `idx_account_set` (`account_set_id`),
  INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票项目配置表';

-- 2. 发票申请表
CREATE TABLE IF NOT EXISTS `invoice_applications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` BIGINT UNSIGNED NOT NULL COMMENT '账套ID',
  `application_no` VARCHAR(50) NOT NULL COMMENT '申请单号',
  `year` INT NOT NULL COMMENT '年度',
  `month` INT NOT NULL COMMENT '月份',
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT '状态：draft-草稿，pending-审批中，approved-已通过，rejected-已驳回，red_flushed-红冲',
  `approval_instance_id` BIGINT UNSIGNED NULL COMMENT '审批实例ID',
  `submitter_id` BIGINT UNSIGNED NOT NULL COMMENT '提交人ID（业务人员）',
  `submitted_at` TIMESTAMP NULL DEFAULT NULL COMMENT '提交时间',
  `rejection_reason` TEXT NULL COMMENT '驳回原因',
  `attachments` JSON NULL COMMENT '附件列表',
  `created_by` BIGINT UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_application_no` (`application_no`),
  INDEX `idx_account_set` (`account_set_id`),
  INDEX `idx_year_month` (`year`, `month`),
  INDEX `idx_status` (`status`),
  INDEX `idx_submitter` (`submitter_id`),
  INDEX `idx_approval` (`approval_instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票申请表';

-- 3. 发票申请明细表（扣除明细）
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `application_id` BIGINT UNSIGNED NOT NULL COMMENT '发票申请ID',
  `invoice_project_id` BIGINT UNSIGNED NOT NULL COMMENT '项目配置ID',
  `project_name` VARCHAR(255) NOT NULL COMMENT '项目名称（冗余，用于生成PDF）',
  `sequence` INT NOT NULL DEFAULT 1 COMMENT '序号',
  `item_name` VARCHAR(255) NOT NULL COMMENT '名称（应发工资/劳保费/商业险）',
  `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT '金额',
  `remark` VARCHAR(500) NULL COMMENT '备注',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `idx_application` (`application_id`),
  INDEX `idx_invoice_project` (`invoice_project_id`),
  CONSTRAINT `fk_invoice_items_application` FOREIGN KEY (`application_id`) REFERENCES `invoice_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票申请明细表';

-- ===========================================================================
-- 初始化示例数据（可选）
-- ===========================================================================

-- 示例：插入一些默认的扣除项目类型
-- INSERT INTO `invoice_projects` (`account_set_id`, `project_name`, `remark`, `created_by`, `created_at`, `updated_at`)
-- VALUES 
--   (1, '应发工资', '员工应发工资项目', 1, NOW(), NOW()),
--   (1, '劳保费', '劳保费用项目', 1, NOW(), NOW()),
--   (1, '商业险', '商业保险项目', 1, NOW(), NOW());

-- ===========================================================================
-- 查询统计示例
-- ===========================================================================

-- 查看所有发票申请
-- SELECT * FROM invoice_applications ORDER BY created_at DESC;

-- 查看某个申请的明细
-- SELECT ia.application_no, ii.* 
-- FROM invoice_applications ia
-- JOIN invoice_items ii ON ia.id = ii.application_id
-- WHERE ia.id = 1;

-- 按月份统计发票金额
-- SELECT 
--   year, 
--   month, 
--   COUNT(*) as application_count,
--   SUM((SELECT SUM(amount) FROM invoice_items WHERE application_id = ia.id)) as total_amount
-- FROM invoice_applications ia
-- GROUP BY year, month
-- ORDER BY year DESC, month DESC;

