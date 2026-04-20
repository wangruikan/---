-- ⚡⚡ 立即执行 - 投标项目管理模块数据库表
-- 请在 Navicat 中执行此脚本

-- 1. 投标项目表
CREATE TABLE IF NOT EXISTS `bid_projects` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '项目ID',
  `account_set_id` INT UNSIGNED NOT NULL COMMENT '账套ID',
  `project_code` VARCHAR(50) UNIQUE COMMENT '项目编号（自动生成）',
  `project_name` VARCHAR(200) NOT NULL COMMENT '项目名称',
  `project_category` VARCHAR(50) DEFAULT NULL COMMENT '项目类别（如：劳务派遣、物业管理、保洁服务等）',
  `client_name` VARCHAR(200) DEFAULT NULL COMMENT '招标单位名称',
  `client_contact` VARCHAR(100) DEFAULT NULL COMMENT '招标单位联系人',
  `client_phone` VARCHAR(20) DEFAULT NULL COMMENT '招标单位联系电话',
  `project_budget` DECIMAL(15,2) DEFAULT NULL COMMENT '项目预算金额',
  `bid_bond` DECIMAL(15,2) DEFAULT NULL COMMENT '投标保证金',
  `bond_paid_at` DATETIME DEFAULT NULL COMMENT '保证金缴纳时间',
  `bond_refunded_at` DATETIME DEFAULT NULL COMMENT '保证金退还时间',
  `project_location` VARCHAR(200) DEFAULT NULL COMMENT '项目地点',
  `project_scale` TEXT DEFAULT NULL COMMENT '项目规模描述',
  `service_period` VARCHAR(100) DEFAULT NULL COMMENT '服务期限（如：1年、3年）',
  `bid_deadline` DATETIME DEFAULT NULL COMMENT '投标截止时间',
  `bid_opening_time` DATETIME DEFAULT NULL COMMENT '开标时间',
  `bid_method` VARCHAR(50) DEFAULT '公开招标' COMMENT '招标方式（公开招标、邀请招标、竞争性谈判等）',
  `information_source` VARCHAR(100) DEFAULT NULL COMMENT '信息来源',
  `status` VARCHAR(20) DEFAULT 'preparing' COMMENT '项目状态',
  `bid_result` VARCHAR(20) DEFAULT NULL COMMENT '投标结果（won=中标, lost=未中标, abandoned=放弃）',
  `win_amount` DECIMAL(15,2) DEFAULT NULL COMMENT '中标金额',
  `win_date` DATE DEFAULT NULL COMMENT '中标日期',
  `contract_signed_at` DATETIME DEFAULT NULL COMMENT '合同签订时间',
  `contract_number` VARCHAR(100) DEFAULT NULL COMMENT '合同编号',
  `contract_amount` DECIMAL(15,2) DEFAULT NULL COMMENT '合同金额',
  `remarks` TEXT DEFAULT NULL COMMENT '备注说明',
  `responsible_person` VARCHAR(100) DEFAULT NULL COMMENT '负责人',
  `responsible_department` VARCHAR(100) DEFAULT NULL COMMENT '负责部门',
  `created_by` INT UNSIGNED DEFAULT NULL COMMENT '创建人ID',
  `updated_by` INT UNSIGNED DEFAULT NULL COMMENT '最后更新人ID',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_account_set` (`account_set_id`),
  INDEX `idx_status` (`status`),
  INDEX `idx_bid_deadline` (`bid_deadline`),
  INDEX `idx_project_category` (`project_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投标项目表';

-- 2. 投标文件表
CREATE TABLE IF NOT EXISTS `bid_documents` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '文件ID',
  `bid_project_id` INT UNSIGNED NOT NULL COMMENT '投标项目ID',
  `document_type` VARCHAR(50) NOT NULL COMMENT '文件类型（招标文件、投标文件、技术方案、报价单、资质证明等）',
  `document_name` VARCHAR(200) NOT NULL COMMENT '文件名称',
  `file_path` VARCHAR(500) NOT NULL COMMENT '文件路径',
  `file_size` BIGINT DEFAULT NULL COMMENT '文件大小（字节）',
  `file_type` VARCHAR(50) DEFAULT NULL COMMENT '文件格式（pdf、doc、xls等）',
  `upload_by` INT UNSIGNED DEFAULT NULL COMMENT '上传人ID',
  `upload_at` DATETIME DEFAULT NULL COMMENT '上传时间',
  `version` VARCHAR(20) DEFAULT '1.0' COMMENT '版本号',
  `remarks` TEXT DEFAULT NULL COMMENT '备注',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_bid_project` (`bid_project_id`),
  INDEX `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投标文件表';

-- 3. 投标进度记录表
CREATE TABLE IF NOT EXISTS `bid_progress_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '记录ID',
  `bid_project_id` INT UNSIGNED NOT NULL COMMENT '投标项目ID',
  `log_type` VARCHAR(50) NOT NULL COMMENT '记录类型（status_change、document_upload、payment、meeting、other）',
  `log_title` VARCHAR(200) NOT NULL COMMENT '记录标题',
  `log_content` TEXT DEFAULT NULL COMMENT '记录内容',
  `old_status` VARCHAR(20) DEFAULT NULL COMMENT '变更前状态（状态变更时记录）',
  `new_status` VARCHAR(20) DEFAULT NULL COMMENT '变更后状态（状态变更时记录）',
  `log_time` DATETIME NOT NULL COMMENT '记录时间',
  `operator_id` INT UNSIGNED DEFAULT NULL COMMENT '操作人ID',
  `operator_name` VARCHAR(100) DEFAULT NULL COMMENT '操作人姓名',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_bid_project` (`bid_project_id`),
  INDEX `idx_log_time` (`log_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投标进度记录表';

-- 4. 投标提醒设置表（可选）
CREATE TABLE IF NOT EXISTS `bid_reminders` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '提醒ID',
  `bid_project_id` INT UNSIGNED NOT NULL COMMENT '投标项目ID',
  `reminder_type` VARCHAR(50) NOT NULL COMMENT '提醒类型（deadline、bond、opening等）',
  `reminder_time` DATETIME NOT NULL COMMENT '提醒时间',
  `reminder_title` VARCHAR(200) NOT NULL COMMENT '提醒标题',
  `reminder_content` TEXT DEFAULT NULL COMMENT '提醒内容',
  `is_sent` TINYINT(1) DEFAULT 0 COMMENT '是否已发送',
  `sent_at` DATETIME DEFAULT NULL COMMENT '发送时间',
  `recipient_ids` TEXT DEFAULT NULL COMMENT '接收人ID列表（JSON数组）',
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  INDEX `idx_bid_project` (`bid_project_id`),
  INDEX `idx_reminder_time` (`reminder_time`),
  INDEX `idx_is_sent` (`is_sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投标提醒设置表';

-- 验证表创建成功
SELECT '✅ 投标项目管理模块数据库表创建完成！' AS message;
SHOW TABLES LIKE 'bid%';

