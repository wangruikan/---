/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `account_set_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_set_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` enum('owner','admin','viewer') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'viewer' COMMENT '在此套账中的角色',
  `approval_level` tinyint DEFAULT NULL COMMENT '审批级别：1=第1级,2=第2级,3=第3级,4=第4级,NULL=不参与审批',
  `approval_level_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '审批级别名称：经办、复核、审核、终审',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为该用户的默认账套',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_set_users_unique` (`account_set_id`,`user_id`),
  KEY `account_set_users_user_id_foreign` (`user_id`),
  CONSTRAINT `account_set_users_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `account_set_users_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户套账关联表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `account_sets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `account_sets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '套账名称',
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '套账代码',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '描述',
  `company_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '公司名称',
  `tax_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '税号',
  `contact_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人',
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `address` text COLLATE utf8mb4_unicode_ci COMMENT '地址',
  `base_adjustment_months` text COLLATE utf8mb4_unicode_ci,
  `status` enum('active','inactive','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '状态',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认套账',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_sets_code_unique` (`code`),
  KEY `account_sets_created_by_foreign` (`created_by`),
  CONSTRAINT `account_sets_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='套账管理表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approval_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` bigint unsigned NOT NULL COMMENT '审批实例ID',
  `file_path` varchar(500) NOT NULL COMMENT '文件路径',
  `file_name` varchar(255) NOT NULL COMMENT '文件原始名称',
  `file_size` bigint DEFAULT NULL COMMENT '文件大小（字节）',
  `file_type` varchar(100) DEFAULT NULL COMMENT '文件类型（MIME）',
  `uploaded_by` bigint unsigned DEFAULT NULL COMMENT '上传人ID（NULL=系统自动）',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_instance` (`instance_id`),
  CONSTRAINT `approval_attachments_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `approval_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='审批附件表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approval_cc_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_cc_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` bigint unsigned NOT NULL COMMENT '审批实例ID',
  `user_id` bigint unsigned NOT NULL COMMENT '抄送人ID',
  `user_name` varchar(100) NOT NULL COMMENT '抄送人姓名',
  `added_by` bigint unsigned NOT NULL COMMENT '添加人ID',
  `added_at_step` tinyint NOT NULL COMMENT '在哪个步骤添加的',
  `has_read` tinyint(1) DEFAULT '0' COMMENT '是否已读',
  `read_at` timestamp NULL DEFAULT NULL COMMENT '阅读时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_instance` (`instance_id`),
  KEY `idx_user` (`user_id`,`has_read`),
  CONSTRAINT `approval_cc_users_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `approval_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='审批抄送表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approval_instances`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_instances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `business_type` varchar(50) NOT NULL COMMENT '业务类型：employee_contract, salary, insurance等',
  `business_id` bigint unsigned NOT NULL COMMENT '业务数据ID',
  `current_step` tinyint NOT NULL DEFAULT '1' COMMENT '当前审批步骤',
  `total_steps` tinyint NOT NULL DEFAULT '4' COMMENT '总审批步骤数',
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending' COMMENT '整体状态',
  `created_by` bigint unsigned NOT NULL COMMENT '发起人ID',
  `attachment_path` varchar(500) DEFAULT NULL COMMENT '附件路径（合同文件等）',
  `attachment_name` varchar(255) DEFAULT NULL COMMENT '附件原始文件名',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `completed_at` timestamp NULL DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`),
  KEY `idx_account_business` (`account_set_id`,`business_type`,`business_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='审批流程实例表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approval_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approval_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `instance_id` bigint unsigned NOT NULL COMMENT '审批实例ID',
  `step_order` tinyint NOT NULL COMMENT '审批步骤：1,2,3,4',
  `step_name` varchar(50) NOT NULL COMMENT '步骤名称：经办、复核、审核、终审',
  `approver_id` bigint unsigned NOT NULL COMMENT '审批人ID',
  `approver_name` varchar(100) NOT NULL COMMENT '审批人姓名',
  `status` enum('waiting','pending','approved','rejected','returned') NOT NULL DEFAULT 'waiting' COMMENT '状态：waiting=等待中, pending=审批中, approved=已通过, rejected=已驳回, returned=已退回',
  `comment` text COMMENT '审批意见',
  `signature_image` varchar(500) DEFAULT NULL COMMENT '签名图片路径',
  `seal_image` varchar(500) DEFAULT NULL COMMENT '印章图片路径',
  `returned_to_step` tinyint DEFAULT NULL COMMENT '退回到的步骤',
  `returned_at` timestamp NULL DEFAULT NULL COMMENT '退回时间',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '审批时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_instance` (`instance_id`),
  KEY `idx_approver` (`approver_id`,`status`),
  KEY `idx_step` (`instance_id`,`step_order`),
  CONSTRAINT `approval_records_ibfk_1` FOREIGN KEY (`instance_id`) REFERENCES `approval_instances` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='审批记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `user_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `type` enum('leave','overtime','business_trip','expense','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT '0.00',
  `status` enum('pending','approved','rejected','returned') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '审批意见',
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `approver_id` bigint DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `actual_completion_date` date DEFAULT NULL,
  `related_id` bigint DEFAULT NULL COMMENT '业务关联id',
  `applicant_id` bigint DEFAULT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expected_completion_date` datetime DEFAULT NULL,
  `approval_comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `seal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stamp_method` enum('online','offline') COLLATE utf8mb4_unicode_ci DEFAULT 'online' COMMENT '盖章方式：online=线上盖章，offline=线下盖章',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE,
  KEY `project_id` (`project_id`) USING BTREE,
  KEY `idx_approvals_status` (`status`) USING BTREE,
  KEY `idx_approvals_account_set_id` (`account_set_id`),
  CONSTRAINT `approvals_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `approvals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `approvals_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `assessment_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `assessment_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `business_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '业务类型：insurance_enrollment-参保入职, contract_signing-合同签署, salary_payment-工资发放等',
  `business_id` bigint unsigned DEFAULT NULL COMMENT '业务记录ID（可选）',
  `business_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '业务描述（如：张三-社保参保）',
  `handler_id` bigint unsigned NOT NULL COMMENT '责任人ID（经办人）',
  `handler_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '责任人姓名',
  `deadline_date` date NOT NULL COMMENT '应完成时间',
  `actual_complete_date` datetime DEFAULT NULL COMMENT '实际完成时间',
  `overdue_days` int NOT NULL DEFAULT '0' COMMENT '超期天数',
  `status` enum('pending','overdue','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：pending-待处理, overdue-已超期, completed-已完成',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注说明',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_business_type` (`business_type`),
  KEY `idx_handler` (`handler_id`),
  KEY `idx_status` (`status`),
  KEY `idx_deadline` (`deadline_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='考核记录表（通用）';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `employee_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `work_hours` decimal(4,2) DEFAULT '0.00' COMMENT '工作时长',
  `overtime_hours` decimal(4,2) DEFAULT '0.00' COMMENT '加班时长',
  `status` enum('normal','late','early','absent','leave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'normal',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `project_id` (`project_id`) USING BTREE,
  KEY `idx_attendance_date` (`date`) USING BTREE,
  KEY `idx_attendance_employee_date` (`employee_id`,`date`) USING BTREE,
  KEY `idx_employee_id` (`employee_id`) USING BTREE,
  KEY `idx_project_id` (`project_id`) USING BTREE,
  KEY `idx_date` (`date`) USING BTREE,
  KEY `idx_employee_date` (`employee_id`,`date`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_attendance_account_set_id` (`account_set_id`),
  CONSTRAINT `attendance_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `attendance_sheet_id` bigint unsigned NOT NULL COMMENT '考勤表ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `date` date NOT NULL COMMENT '考勤日期',
  `day_of_month` int NOT NULL COMMENT '月份中的第几天',
  `status` enum('normal','late','early','absent','leave','off') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '考勤状态',
  `check_in_time` time DEFAULT NULL COMMENT '上班时间',
  `check_out_time` time DEFAULT NULL COMMENT '下班时间',
  `work_hours` decimal(4,2) DEFAULT '8.00' COMMENT '工作时长',
  `overtime_hours` decimal(4,2) DEFAULT '0.00' COMMENT '加班时长',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sheet_employee_day` (`attendance_sheet_id`,`employee_id`,`day_of_month`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_attendance_sheet_id` (`attendance_sheet_id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_date` (`date`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='考勤记录';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_rules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_rules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `rule_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '规则名称',
  `work_start_time` time NOT NULL DEFAULT '09:00:00' COMMENT '上班时间',
  `work_end_time` time NOT NULL DEFAULT '18:00:00' COMMENT '下班时间',
  `late_threshold` int NOT NULL DEFAULT '15' COMMENT '迟到阈值(分钟)',
  `early_threshold` int NOT NULL DEFAULT '15' COMMENT '早退阈值(分钟)',
  `work_days_per_week` int NOT NULL DEFAULT '5' COMMENT '每周工作天数',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='考勤规则配置';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_sheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_sheets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `project_id` bigint unsigned NOT NULL,
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '月份 YYYY-MM',
  `work_days` int NOT NULL DEFAULT '22' COMMENT '工作日天数',
  `status` enum('draft','submitted','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `total_employees` int DEFAULT '0',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `attachments` text COLLATE utf8mb4_unicode_ci COMMENT '提交的附件信息(JSON格式)',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `submitted_by` bigint unsigned DEFAULT NULL COMMENT '提交人ID',
  `approved_by` bigint unsigned DEFAULT NULL COMMENT '审批人ID',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `project_id` (`project_id`) USING BTREE,
  KEY `idx_project_id` (`project_id`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_month` (`month`) USING BTREE,
  KEY `idx_created_at` (`created_at`) USING BTREE,
  KEY `idx_attendance_sheets_account_set_id` (`account_set_id`),
  CONSTRAINT `attendance_sheets_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `attendance_sheets_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `attendance_statistics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `attendance_statistics` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `attendance_sheet_id` bigint unsigned NOT NULL COMMENT '考勤表ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `work_days` int NOT NULL DEFAULT '0' COMMENT '应出勤天数',
  `actual_work_days` int NOT NULL DEFAULT '0' COMMENT '实际出勤天数',
  `absent_days` int NOT NULL DEFAULT '0' COMMENT '缺勤天数',
  `late_count` int NOT NULL DEFAULT '0' COMMENT '迟到次数',
  `early_count` int NOT NULL DEFAULT '0' COMMENT '早退次数',
  `leave_days` int NOT NULL DEFAULT '0' COMMENT '请假天数',
  `off_days` int NOT NULL DEFAULT '0' COMMENT '调休天数',
  `attendance_rate` decimal(5,4) NOT NULL DEFAULT '0.0000' COMMENT '出勤率',
  `total_work_hours` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '总工作时长',
  `total_overtime_hours` decimal(6,2) NOT NULL DEFAULT '0.00' COMMENT '总加班时长',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_sheet_employee` (`attendance_sheet_id`,`employee_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_attendance_sheet_id` (`attendance_sheet_id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='考勤统计';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `base_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `base_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `project_id` bigint unsigned DEFAULT NULL COMMENT '项目ID（旧字段，已废弃）',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `old_social_security_base` decimal(10,2) DEFAULT NULL COMMENT '调整前社保基数',
  `old_medical_insurance_base` decimal(10,2) DEFAULT NULL COMMENT '调整前医保基数',
  `old_housing_fund_base` decimal(10,2) DEFAULT NULL COMMENT '调整前公积金基数',
  `old_large_medical_base` decimal(10,2) DEFAULT NULL COMMENT '调整前大额医疗基数',
  `new_social_security_base` decimal(10,2) DEFAULT NULL COMMENT '调整后社保基数',
  `new_medical_insurance_base` decimal(10,2) DEFAULT NULL COMMENT '调整后医保基数',
  `new_housing_fund_base` decimal(10,2) DEFAULT NULL COMMENT '调整后公积金基数',
  `new_large_medical_base` decimal(10,2) DEFAULT NULL COMMENT '调整后大额医疗基数（选填）',
  `effective_date` date DEFAULT NULL COMMENT '统一生效时间（旧字段，已废弃）',
  `status` enum('pending','approved','applied','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：待审批、已审批、已生效、已取消',
  `applied_at` timestamp NULL DEFAULT NULL COMMENT '生效时间（实际应用到档案的时间）',
  `adjustment_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '调整原因',
  `reason` text COLLATE utf8mb4_unicode_ci COMMENT '调整原因',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `approved_by` bigint unsigned DEFAULT NULL COMMENT '审批人ID',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '审批时间',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `social_security_effective_date` date DEFAULT NULL COMMENT '社保基数生效时间',
  `medical_insurance_effective_date` date DEFAULT NULL COMMENT '医保基数生效时间',
  `housing_fund_effective_date` date DEFAULT NULL COMMENT '公积金基数生效时间',
  `large_medical_effective_date` date DEFAULT NULL COMMENT '大额医疗基数生效时间',
  `social_security_min_base` decimal(10,2) DEFAULT NULL COMMENT '社保下限（调基时）',
  `social_security_max_base` decimal(10,2) DEFAULT NULL COMMENT '社保上限（调基时）',
  `medical_insurance_min_base` decimal(10,2) DEFAULT NULL COMMENT '医保下限（调基时）',
  `medical_insurance_max_base` decimal(10,2) DEFAULT NULL COMMENT '医保上限（调基时）',
  `housing_fund_min_base` decimal(10,2) DEFAULT NULL COMMENT '公积金下限（调基时）',
  `housing_fund_max_base` decimal(10,2) DEFAULT NULL COMMENT '公积金上限（调基时）',
  PRIMARY KEY (`id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_effective_date` (`effective_date`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `base_adjustments_approved_by_foreign` (`approved_by`),
  KEY `idx_employee_effective` (`employee_id`,`effective_date`,`status`),
  KEY `idx_account_status` (`account_set_id`,`status`,`effective_date`),
  KEY `idx_social_effective_date` (`social_security_effective_date`),
  CONSTRAINT `base_adjustments_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `base_adjustments_approved_by_foreign` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `base_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `base_adjustments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `base_adjustments_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='基数调差记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `basis_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `basis_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `basis_record_id` bigint unsigned NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件类型：image/document/other',
  `file_size` bigint NOT NULL COMMENT '文件大小（字节）',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `basis_attachments_basis_record_id_foreign` (`basis_record_id`),
  CONSTRAINT `basis_attachments_basis_record_id_foreign` FOREIGN KEY (`basis_record_id`) REFERENCES `basis_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `basis_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `basis_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `type` enum('attendance','salary') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '依据类型：attendance-考勤依据，salary-工资依据',
  `month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '月份，格式：YYYY-MM',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '文字说明',
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_month_type` (`project_id`,`month`,`type`,`deleted_at`),
  KEY `basis_records_account_set_id_foreign` (`account_set_id`),
  KEY `basis_records_created_by_foreign` (`created_by`),
  CONSTRAINT `basis_records_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `basis_records_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `basis_records_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bid_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bid_documents` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '文件ID',
  `bid_project_id` int unsigned NOT NULL COMMENT '投标项目ID',
  `document_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件类型（招标文件、投标文件、技术方案、报价单、资质证明等）',
  `document_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名称',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_size` bigint DEFAULT NULL COMMENT '文件大小（字节）',
  `file_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件格式（pdf、doc、xls等）',
  `upload_by` int unsigned DEFAULT NULL COMMENT '上传人ID',
  `upload_at` datetime DEFAULT NULL COMMENT '上传时间',
  `version` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '1.0' COMMENT '版本号',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bid_project` (`bid_project_id`),
  KEY `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投标文件表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bid_progress_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bid_progress_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `bid_project_id` int unsigned NOT NULL COMMENT '投标项目ID',
  `log_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '记录类型（status_change、document_upload、payment、meeting、other）',
  `log_title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '记录标题',
  `log_content` text COLLATE utf8mb4_unicode_ci COMMENT '记录内容',
  `old_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '变更前状态（状态变更时记录）',
  `new_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '变更后状态（状态变更时记录）',
  `log_time` datetime NOT NULL COMMENT '记录时间',
  `operator_id` int unsigned DEFAULT NULL COMMENT '操作人ID',
  `operator_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '操作人姓名',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bid_project` (`bid_project_id`),
  KEY `idx_log_time` (`log_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投标进度记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bid_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bid_projects` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '项目ID',
  `account_set_id` int unsigned NOT NULL COMMENT '账套ID',
  `project_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目编号（自动生成）',
  `project_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称',
  `project_category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目类别（如：劳务派遣、物业管理、保洁服务等）',
  `client_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '招标单位名称',
  `client_contact` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '招标单位联系人',
  `client_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '招标单位联系电话',
  `project_budget` decimal(15,2) DEFAULT NULL COMMENT '项目预算金额',
  `bid_bond` decimal(15,2) DEFAULT NULL COMMENT '投标保证金',
  `bond_paid_at` datetime DEFAULT NULL COMMENT '保证金缴纳时间',
  `bond_refunded_at` datetime DEFAULT NULL COMMENT '保证金退还时间',
  `project_location` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目地点',
  `project_scale` text COLLATE utf8mb4_unicode_ci COMMENT '项目规模描述',
  `service_period` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '服务期限（如：1年、3年）',
  `bid_deadline` datetime DEFAULT NULL COMMENT '投标截止时间',
  `bid_opening_time` datetime DEFAULT NULL COMMENT '开标时间',
  `bid_method` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '公开招标' COMMENT '招标方式（公开招标、邀请招标、竞争性谈判等）',
  `information_source` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '信息来源',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'preparing' COMMENT '项目状态',
  `bid_result` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '投标结果（won=中标, lost=未中标, abandoned=放弃）',
  `win_amount` decimal(15,2) DEFAULT NULL COMMENT '中标金额',
  `win_date` date DEFAULT NULL COMMENT '中标日期',
  `contract_signed_at` datetime DEFAULT NULL COMMENT '合同签订时间',
  `contract_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '合同编号',
  `contract_amount` decimal(15,2) DEFAULT NULL COMMENT '合同金额',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT '备注说明',
  `responsible_person` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '负责人',
  `responsible_department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '负责部门',
  `created_by` int unsigned DEFAULT NULL COMMENT '创建人ID',
  `updated_by` int unsigned DEFAULT NULL COMMENT '最后更新人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `project_code` (`project_code`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_status` (`status`),
  KEY `idx_bid_deadline` (`bid_deadline`),
  KEY `idx_project_category` (`project_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投标项目表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bid_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bid_reminders` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '提醒ID',
  `bid_project_id` int unsigned NOT NULL COMMENT '投标项目ID',
  `reminder_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '提醒类型（deadline、bond、opening等）',
  `reminder_time` datetime NOT NULL COMMENT '提醒时间',
  `reminder_title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '提醒标题',
  `reminder_content` text COLLATE utf8mb4_unicode_ci COMMENT '提醒内容',
  `is_sent` tinyint(1) DEFAULT '0' COMMENT '是否已发送',
  `sent_at` datetime DEFAULT NULL COMMENT '发送时间',
  `recipient_ids` text COLLATE utf8mb4_unicode_ci COMMENT '接收人ID列表（JSON数组）',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bid_project` (`bid_project_id`),
  KEY `idx_reminder_time` (`reminder_time`),
  KEY `idx_is_sent` (`is_sent`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='投标提醒设置表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contract_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL,
  `employee_id` bigint unsigned NOT NULL,
  `reminder_type` enum('labor_contract','termination_agreement','retirement_agreement') COLLATE utf8mb4_unicode_ci NOT NULL,
  `employee_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `termination_date` date DEFAULT NULL,
  `retirement_date` date DEFAULT NULL,
  `status` enum('pending','resolved','escalated') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `handler_id` bigint unsigned NOT NULL DEFAULT '0',
  `handler_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '业务人员',
  `reminder_date` date NOT NULL,
  `escalation_date` date DEFAULT NULL,
  `is_escalated` tinyint(1) NOT NULL DEFAULT '0',
  `assessment_record_id` bigint unsigned DEFAULT NULL,
  `remark` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `contract_reminders_assessment_record_id_foreign` (`assessment_record_id`),
  KEY `contract_reminders_account_set_id_reminder_date_index` (`account_set_id`,`reminder_date`),
  KEY `contract_reminders_status_is_escalated_index` (`status`,`is_escalated`),
  KEY `contract_reminders_employee_id_reminder_type_index` (`employee_id`,`reminder_type`),
  CONSTRAINT `contract_reminders_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contract_reminders_assessment_record_id_foreign` FOREIGN KEY (`assessment_record_id`) REFERENCES `assessment_records` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contract_reminders_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contract_templates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contract_templates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `contract_type` enum('labor','termination','retirement') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '合同类型：labor-劳动合同，termination-解除协议合同，retirement-退休解除协议合同',
  `shared_file_id` bigint unsigned NOT NULL COMMENT '共享文件ID',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为默认模板',
  `placeholder_positions` text COLLATE utf8mb4_unicode_ci COMMENT '占位符位置坐标',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_type` (`project_id`,`contract_type`),
  KEY `idx_shared_file` (`shared_file_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_contract_templates_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contract_templates_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_contract_templates_shared_file` FOREIGN KEY (`shared_file_id`) REFERENCES `shared_files` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='合同模板表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `config_id` bigint unsigned DEFAULT NULL COMMENT '配置ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `delivery_cycle` enum('monthly','quarterly') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交付周期',
  `delivery_method` enum('express','electronic') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交付方式',
  `delivery_period` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交付期间（月份：YYYY-MM，季度：YYYY-Q1/Q2/Q3/Q4）',
  `status` enum('pending','submitted','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：pending-待交付，submitted-已提交，completed-已完成',
  `handler_id` bigint unsigned DEFAULT NULL COMMENT '经办人ID（负责交付的人）',
  `required_documents` text COLLATE utf8mb4_unicode_ci COMMENT '应交付资料清单（JSON格式）',
  `submitted_documents` text COLLATE utf8mb4_unicode_ci COMMENT '已提交资料说明',
  `express_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '快递单号',
  `express_date` date DEFAULT NULL COMMENT '快递寄出日期',
  `submitted_by` bigint unsigned DEFAULT NULL COMMENT '提交人ID（经办）',
  `submitted_at` datetime DEFAULT NULL COMMENT '提交时间',
  `completed_by` bigint unsigned DEFAULT NULL COMMENT '完成人ID',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_period` (`project_id`,`delivery_period`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_delivery_period` (`delivery_period`),
  KEY `idx_status` (`status`),
  KEY `idx_submitted_at` (`submitted_at`),
  KEY `idx_config_id` (`config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资料交付记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_delivery_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_delivery_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `delivery_id` bigint unsigned NOT NULL COMMENT '交付记录ID',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_size` bigint DEFAULT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件类型',
  `uploaded_by` bigint unsigned DEFAULT NULL COMMENT '上传人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_delivery_id` (`delivery_id`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资料交付附件表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `document_delivery_reminders`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `document_delivery_reminders` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `delivery_id` bigint unsigned NOT NULL COMMENT '交付记录ID',
  `reminder_type` enum('new_period','not_submitted') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '提醒类型：new_period-新周期提醒，not_submitted-未交付提醒',
  `recipient_id` bigint unsigned NOT NULL COMMENT '接收人ID（经办）',
  `is_read` tinyint(1) DEFAULT '0' COMMENT '是否已读：0-未读，1-已读',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_delivery_id` (`delivery_id`),
  KEY `idx_recipient_id` (`recipient_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资料交付提醒记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_base_adjustments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_base_adjustments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `current_social_security_base` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '当前社保基数',
  `current_medical_insurance_base` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '当前医保基数',
  `current_housing_fund_base` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '当前公积金基数',
  `current_large_medical_base` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '当前大额医疗基数',
  `adjusted_social_security_base` decimal(10,2) DEFAULT NULL COMMENT '调整后社保基数',
  `adjusted_medical_insurance_base` decimal(10,2) DEFAULT NULL COMMENT '调整后医保基数',
  `adjusted_housing_fund_base` decimal(10,2) DEFAULT NULL COMMENT '调整后公积金基数',
  `adjusted_large_medical_base` decimal(10,2) DEFAULT NULL COMMENT '调整后大额医疗基数（选填）',
  `effective_date` date DEFAULT NULL COMMENT '生效时间（年-月-日）',
  `status` enum('pending','applied') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：待生效、已生效',
  `applied_at` timestamp NULL DEFAULT NULL COMMENT '生效时间（实际应用到档案的时间）',
  `adjustment_reason` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '调整原因',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_employee_adjustment` (`employee_id`,`account_set_id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_effective_date` (`effective_date`),
  KEY `idx_status` (`status`),
  KEY `employee_base_adjustments_created_by_foreign` (`created_by`),
  CONSTRAINT `employee_base_adjustments_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_base_adjustments_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `employee_base_adjustments_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工调差数据表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_contracts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_contracts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '账套ID',
  `contract_type` enum('labor','termination','retirement') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议',
  `contract_file` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '合同文件路径',
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原始文件名',
  `status` enum('draft','pending_sign','employee_signed','in_approval','completed','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT '合同状态：draft=草稿, pending_sign=签署中, employee_signed=乙方已签署, in_approval=审批中, completed=已完成, rejected=已驳回',
  `approval_instance_id` bigint unsigned DEFAULT NULL COMMENT '审批实例ID',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建人ID',
  `uploaded_at` timestamp NULL DEFAULT NULL COMMENT '上传时间',
  `employee_signed_at` timestamp NULL DEFAULT NULL COMMENT '员工签署时间',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT '完成时间',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `signature_image` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '员工签名图片',
  `sign_x_percent` decimal(8,4) DEFAULT NULL COMMENT '签名X坐标百分比(0-100)',
  `sign_y_percent` decimal(8,4) DEFAULT NULL COMMENT '签名Y坐标百分比(0-100)',
  `sign_page_index` int DEFAULT NULL COMMENT '签名页码(从0开始)',
  `sign_ip` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '签署IP地址',
  `sign_device` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '签署设备信息',
  `employee_reject_reason` text COLLATE utf8mb4_unicode_ci COMMENT '员工拒绝原因',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_contract_type` (`contract_type`),
  KEY `idx_status` (`status`),
  KEY `employee_contracts_created_by_foreign` (`created_by`),
  KEY `idx_approval` (`approval_instance_id`),
  CONSTRAINT `employee_contracts_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_contracts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employee_contracts_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工合同表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_deduction_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_deduction_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `project_id` bigint unsigned DEFAULT NULL COMMENT '项目ID',
  `deduction_items` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '专项扣除项目（格式：项目ID:金额|项目ID:金额）',
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总扣除金额',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_project` (`employee_id`,`project_id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_employee_deduction_details_account_set` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_deduction_details_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_deduction_details_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工专项扣除明细表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_documents`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_documents` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `document_config_id` bigint unsigned NOT NULL COMMENT '资料配置ID',
  `document_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '资料名称',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
  `file_size` bigint unsigned DEFAULT '0' COMMENT '文件大小（字节）',
  `file_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件MIME类型',
  `upload_source` enum('miniapp','pc') COLLATE utf8mb4_unicode_ci DEFAULT 'miniapp' COMMENT '上传来源：miniapp=小程序，pc=PC端',
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_unique_employee_document` (`employee_id`,`document_config_id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_document_config_id` (`document_config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工资料上传记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_insurance_enrollment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_insurance_enrollment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `policy_id` bigint unsigned NOT NULL COMMENT '保单ID',
  `policy_version` int NOT NULL DEFAULT '1' COMMENT '保单版本号',
  `enrollment_date` date NOT NULL COMMENT '参保日期',
  `payment_amount` decimal(10,2) DEFAULT NULL COMMENT '支付金额',
  `status` enum('active','expired') DEFAULT 'active' COMMENT '状态：active=生效，expired=过期',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_enrollment` (`employee_id`,`policy_id`,`policy_version`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_policy` (`policy_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_enrollment_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_enrollment_policy` FOREIGN KEY (`policy_id`) REFERENCES `other_insurance_policies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='员工其他保险参保记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_large_medical_insurance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_large_medical_insurance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `config_id` bigint unsigned DEFAULT NULL COMMENT '大额医疗保险配置ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '所属账套ID',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用：1=是，0=否',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_employee` (`employee_id`),
  KEY `idx_config` (`config_id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_enabled` (`is_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工大额医疗保险配置表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','inactive','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `unique_employee_project` (`employee_id`,`project_id`) USING BTREE,
  KEY `project_id` (`project_id`) USING BTREE,
  KEY `idx_employee_id` (`employee_id`) USING BTREE,
  KEY `idx_project_id` (`project_id`) USING BTREE,
  KEY `idx_employee_project` (`employee_id`,`project_id`) USING BTREE,
  CONSTRAINT `employee_projects_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `employee_projects_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_salaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_salaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `salary_sheet_id` bigint unsigned NOT NULL COMMENT '工资表ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `basic_salary` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '基本工资',
  `overtime_pay` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '加班费',
  `bonus` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '奖金',
  `deductions` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '扣款',
  `net_salary` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '实发工资',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_salary_employee` (`salary_sheet_id`,`employee_id`),
  KEY `idx_salary_sheet_id` (`salary_sheet_id`),
  KEY `idx_employee_id` (`employee_id`),
  CONSTRAINT `fk_employee_salaries_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_salaries_sheet` FOREIGN KEY (`salary_sheet_id`) REFERENCES `salary_sheets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工工资详情';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employee_special_deductions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employee_special_deductions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `project_id` bigint unsigned DEFAULT NULL COMMENT '项目ID',
  `deduction_item_id` bigint unsigned NOT NULL COMMENT '专项扣除项目ID',
  `custom_amount` decimal(10,2) DEFAULT NULL COMMENT '自定义金额（为空则使用项目默认金额）',
  `expiry_date` date DEFAULT NULL COMMENT '失效日期',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_deduction` (`employee_id`,`deduction_item_id`,`project_id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_deduction_item` (`deduction_item_id`),
  KEY `idx_active` (`is_active`),
  CONSTRAINT `fk_employee_special_deductions_account_set` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_special_deductions_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employee_special_deductions_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='员工专项扣除表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `employees`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `employees` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '岗位',
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '部门',
  `id_number` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '身份证号',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gender` enum('male','female') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nationality` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '中国' COMMENT '国籍',
  `marital_status` enum('single','married','divorced','widowed') COLLATE utf8mb4_unicode_ci DEFAULT 'single' COMMENT '婚姻状态',
  `education` enum('primary','middle','high_school','college','bachelor','master','doctor') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '学历',
  `birth_date` date NOT NULL,
  `hire_date` date NOT NULL COMMENT '入职日期',
  `contract_start_date` date NOT NULL COMMENT '合同开始日期',
  `contract_end_date` date DEFAULT NULL COMMENT '合同结束日期',
  `contract_status` enum('unsigned','in_approval','active','expired','terminated','rejected') COLLATE utf8mb4_unicode_ci DEFAULT 'unsigned' COMMENT '合同状态：unsigned=未签署, in_approval=审批中, active=在职, expired=已过期, terminated=已终止, rejected=已驳回',
  `termination_date` date DEFAULT NULL,
  `termination_reason` text COLLATE utf8mb4_unicode_ci,
  `is_retired` tinyint(1) NOT NULL DEFAULT '0',
  `retirement_date` date DEFAULT NULL,
  `social_security_region_id` bigint unsigned DEFAULT NULL COMMENT '社保参保地区ID',
  `housing_fund_region_id` bigint unsigned DEFAULT NULL COMMENT '公积金参保地区ID',
  `housing_fund_config_id` bigint unsigned DEFAULT NULL COMMENT '公积金配置ID',
  `large_medical_insurance_config_id` bigint unsigned DEFAULT NULL COMMENT '大额医疗保险配置ID',
  `password` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '自定义密码（修改后）',
  `password_changed_at` timestamp NULL DEFAULT NULL COMMENT '密码修改时间',
  `login_failed_count` int DEFAULT '0' COMMENT '登录失败次数',
  `locked_until` timestamp NULL DEFAULT NULL COMMENT '锁定到什么时间',
  `last_login_at` timestamp NULL DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '最后登录IP',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `emergency_contact` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '紧急联系人',
  `emergency_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '紧急联系电话',
  `bank_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '开户银行',
  `bank_account` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '银行账号',
  `bank_branch` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '开户支行',
  `basic_salary` decimal(10,2) DEFAULT NULL COMMENT '基础工资',
  `social_security_base` decimal(10,2) DEFAULT '0.00' COMMENT '社保缴费基数',
  `medical_insurance_base` decimal(10,2) DEFAULT NULL COMMENT '医保基数',
  `large_medical_base` decimal(10,2) DEFAULT NULL COMMENT '大额基数',
  `housing_fund_base` decimal(10,2) DEFAULT '0.00' COMMENT '公积金缴费基数',
  `large_medical_payment_cycle` enum('month','year') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '大额医疗保险付款周期',
  `large_medical_calculation_method` enum('base','fixed') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '大额医疗保险计算方式',
  `large_medical_company_ratio` decimal(5,4) DEFAULT NULL COMMENT '大额医疗保险公司缴纳比例',
  `large_medical_employee_ratio` decimal(5,4) DEFAULT NULL COMMENT '大额医疗保险员工缴纳比例',
  `large_medical_company_amount` decimal(10,2) DEFAULT NULL COMMENT '大额医疗保险公司缴纳金额',
  `large_medical_employee_amount` decimal(10,2) DEFAULT NULL COMMENT '大额医疗保险员工缴纳金额',
  `special_deduction` decimal(10,2) DEFAULT '0.00' COMMENT '专项扣除金额',
  `is_annual_deduction` tinyint(1) DEFAULT '0' COMMENT '是否年度扣除',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `project_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '所属项目',
  `medical_insurance_region_id` bigint unsigned DEFAULT NULL,
  `insurance_completed_at` timestamp NULL DEFAULT NULL COMMENT '参保完成时间',
  `employee_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_face_verified` tinyint(1) DEFAULT '0' COMMENT '是否已完成人脸识别',
  `face_verified_at` timestamp NULL DEFAULT NULL COMMENT '人脸识别时间',
  `tencent_biz_token` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '腾讯云核身流水号',
  `verification_result` text COLLATE utf8mb4_unicode_ci COMMENT '核身结果数据(JSON)',
  `face_similarity` decimal(5,2) DEFAULT NULL COMMENT '人脸相似度',
  `login_password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '登录密码',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `id_number` (`id_number`) USING BTREE,
  KEY `idx_employees_id_number` (`id_number`) USING BTREE,
  KEY `idx_employees_phone` (`phone`) USING BTREE,
  KEY `idx_name` (`name`(191)) USING BTREE,
  KEY `idx_id_number` (`id_number`) USING BTREE,
  KEY `idx_phone` (`phone`) USING BTREE,
  KEY `idx_contract_status` (`contract_status`) USING BTREE,
  KEY `idx_created_at` (`created_at`) USING BTREE,
  KEY `idx_name_phone` (`name`(191),`phone`) USING BTREE,
  KEY `idx_employees_account_set_id` (`account_set_id`),
  KEY `idx_employees_social_security_region` (`social_security_region_id`),
  KEY `idx_employees_housing_fund_region` (`housing_fund_region_id`),
  KEY `employees_medical_insurance_region_id_foreign` (`medical_insurance_region_id`),
  KEY `employees_housing_fund_config_id_foreign` (`housing_fund_config_id`),
  CONSTRAINT `employees_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_housing_fund_config_id_foreign` FOREIGN KEY (`housing_fund_config_id`) REFERENCES `housing_fund_configs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_housing_fund_region_foreign` FOREIGN KEY (`housing_fund_region_id`) REFERENCES `housing_funds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_medical_insurance_region_id_foreign` FOREIGN KEY (`medical_insurance_region_id`) REFERENCES `medical_insurance_regions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `employees_social_security_region_foreign` FOREIGN KEY (`social_security_region_id`) REFERENCES `social_security_regions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `housing_fund_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `housing_fund_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `region_id` bigint unsigned NOT NULL COMMENT '地区ID',
  `config_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置名称',
  `base_amount` decimal(10,2) NOT NULL COMMENT '基数',
  `min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '下限基数',
  `max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '上限基数',
  `old_min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '旧最低基数',
  `old_max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '旧最高基数',
  `old_limits_updated_at` timestamp NULL DEFAULT NULL COMMENT '旧上下限修改时间',
  `new_min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '新最低基数',
  `new_max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '新最高基数',
  `new_limits_updated_at` timestamp NULL DEFAULT NULL COMMENT '新上下限修改时间',
  `employee_ratio` decimal(5,4) NOT NULL COMMENT '员工缴纳比例',
  `company_ratio` decimal(5,4) NOT NULL COMMENT '公司缴纳比例',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为默认配置',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `housing_fund_configs_region_id_foreign` (`region_id`),
  KEY `housing_fund_configs_created_by_foreign` (`created_by`),
  KEY `housing_fund_configs_account_set_id_foreign` (`account_set_id`),
  CONSTRAINT `housing_fund_configs_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`),
  CONSTRAINT `housing_fund_configs_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `housing_fund_configs_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `housing_fund_regions` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公积金配置表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `housing_fund_regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `housing_fund_regions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `region_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `account_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '公积金账号',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `housing_fund_regions_region_account_unique` (`region_name`,`account_set_id`),
  KEY `housing_fund_regions_created_by_foreign` (`created_by`),
  KEY `housing_fund_regions_account_set_id_foreign` (`account_set_id`),
  CONSTRAINT `housing_fund_regions_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`),
  CONSTRAINT `housing_fund_regions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公积金地区表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `housing_funds`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `housing_funds` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `config_id` bigint unsigned DEFAULT NULL COMMENT '公积金配置ID',
  `region_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `base_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '基数',
  `employee_ratio` decimal(5,4) NOT NULL DEFAULT '0.0000' COMMENT '员工缴纳比例',
  `company_ratio` decimal(5,4) NOT NULL DEFAULT '0.0000' COMMENT '公司缴纳比例',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `adjustment_base` decimal(10,2) DEFAULT NULL COMMENT '调整基数（预设值）',
  `effective_date` date DEFAULT NULL COMMENT '生效时间',
  PRIMARY KEY (`id`),
  KEY `housing_funds_account_set_id_foreign` (`account_set_id`),
  KEY `housing_funds_created_by_foreign` (`created_by`),
  CONSTRAINT `housing_funds_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `housing_funds_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公积金管理表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insurance_change_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_change_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `insurance_change_id` bigint unsigned NOT NULL COMMENT '增减记录ID',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
  `file_size` int unsigned NOT NULL COMMENT '文件大小（字节）',
  `file_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件类型',
  `uploaded_by` bigint unsigned DEFAULT NULL COMMENT '上传人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_insurance_change_id` (`insurance_change_id`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='保险变更附件表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insurance_change_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_change_details` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `insurance_change_id` bigint unsigned NOT NULL COMMENT '参保增减ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `insurance_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险类型：社保、医保、公积金、其他保险',
  `insurance_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险名称',
  `region_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '地区名称',
  `base_amount` decimal(10,2) DEFAULT '0.00' COMMENT '基数',
  `employee_ratio` decimal(8,4) DEFAULT '0.0000' COMMENT '员工比例',
  `company_ratio` decimal(8,4) DEFAULT '0.0000' COMMENT '公司比例',
  `employee_amount` decimal(10,2) DEFAULT '0.00' COMMENT '员工缴纳金额',
  `company_amount` decimal(10,2) DEFAULT '0.00' COMMENT '公司缴纳金额',
  `total_amount` decimal(10,2) DEFAULT '0.00' COMMENT '总缴纳金额',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci DEFAULT 'active' COMMENT '状态',
  `effective_date` timestamp NULL DEFAULT NULL COMMENT '生效日期',
  `expiry_date` timestamp NULL DEFAULT NULL COMMENT '失效日期',
  `payment_cycle` enum('month','year') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '付款周期：month=按月，year=按年',
  `payment_month` tinyint unsigned DEFAULT NULL COMMENT '付款月份（1-12），用于按年付款',
  `medical_base` decimal(10,2) DEFAULT NULL COMMENT '医疗基数',
  `pension_base` decimal(10,2) DEFAULT NULL COMMENT '养老、失业、工伤基数',
  `employee_medical_insurance_base` decimal(10,2) DEFAULT NULL COMMENT '员工医保基数',
  `employee_social_security_base` decimal(10,2) DEFAULT NULL COMMENT '员工社保基数',
  `employee_housing_fund_base` decimal(10,2) DEFAULT NULL COMMENT '员工公积金基数',
  `dynamic_insurance_details` text COLLATE utf8mb4_unicode_ci,
  `detail_type` enum('summary','detail') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detail' COMMENT '明细类型：summary=汇总明细，detail=细分明细',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `insurance_change_details_insurance_change_id_index` (`insurance_change_id`),
  KEY `insurance_change_details_employee_id_project_id_index` (`employee_id`,`project_id`),
  KEY `insurance_change_details_insurance_type_index` (`insurance_type`),
  KEY `insurance_change_details_account_set_id_index` (`account_set_id`),
  KEY `insurance_change_details_status_index` (`status`),
  KEY `insurance_change_details_effective_date_index` (`effective_date`),
  KEY `insurance_change_details_project_id_foreign` (`project_id`),
  CONSTRAINT `insurance_change_details_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insurance_change_details_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insurance_change_details_insurance_change_id_foreign` FOREIGN KEY (`insurance_change_id`) REFERENCES `insurance_changes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insurance_change_details_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='参保明细表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insurance_change_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_change_summaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `region_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `insurance_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险类型：社保、医保、公积金、其他保险',
  `insurance_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险名称',
  `employee_count` int DEFAULT '0' COMMENT '参保人数',
  `total_base_amount` decimal(15,2) DEFAULT '0.00' COMMENT '总基数',
  `total_employee_amount` decimal(15,2) DEFAULT '0.00' COMMENT '员工总缴纳金额',
  `total_company_amount` decimal(15,2) DEFAULT '0.00' COMMENT '公司总缴纳金额',
  `total_amount` decimal(15,2) DEFAULT '0.00' COMMENT '总缴纳金额',
  `summary_date` date NOT NULL COMMENT '汇总日期',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `insurance_change_summaries_account_set_id_summary_date_index` (`account_set_id`,`summary_date`),
  KEY `insurance_change_summaries_region_name_insurance_type_index` (`region_name`,`insurance_type`),
  KEY `insurance_change_summaries_insurance_type_index` (`insurance_type`),
  KEY `insurance_change_summaries_summary_date_index` (`summary_date`),
  KEY `insurance_change_summaries_created_by_index` (`created_by`),
  CONSTRAINT `insurance_change_summaries_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insurance_change_summaries_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='参保汇总表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insurance_changes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_changes` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `employee_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '员工姓名',
  `employee_id_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '员工身份证号',
  `employee_gender` tinyint DEFAULT NULL COMMENT '员工性别 1男2女',
  `employee_birth_date` date DEFAULT NULL COMMENT '员工出生日期',
  `employee_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '员工联系电话',
  `employee_status` tinyint DEFAULT NULL COMMENT '员工状态 1在职2离职',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `social_security_region_id` bigint unsigned DEFAULT NULL COMMENT '社保地区ID',
  `medical_insurance_region_id` bigint unsigned DEFAULT NULL COMMENT '医保地区ID',
  `housing_fund_region_id` bigint unsigned DEFAULT NULL COMMENT '公积金地区ID',
  `housing_fund_config_id` bigint unsigned DEFAULT NULL COMMENT '公积金配置ID',
  `large_medical_insurance_config_id` bigint unsigned DEFAULT NULL COMMENT '大额医疗保险配置ID',
  `large_medical_insurance_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用大额医疗保险：1=是，0=否',
  `employee_social_security_base` decimal(10,2) DEFAULT NULL COMMENT '员工社保基数',
  `employee_medical_insurance_base` decimal(10,2) DEFAULT NULL COMMENT '员工医保基数',
  `employee_housing_fund_base` decimal(10,2) DEFAULT NULL COMMENT '员工公积金基数',
  `employee_large_medical_base` decimal(10,2) DEFAULT NULL COMMENT '员工大额医疗保险基数',
  `social_security_types` text COLLATE utf8mb4_unicode_ci COMMENT '社保类型参数',
  `medical_insurance_types` text COLLATE utf8mb4_unicode_ci COMMENT '医保类型参数',
  `housing_fund_params` text COLLATE utf8mb4_unicode_ci COMMENT '公积金参数',
  `other_insurance_policies` text COLLATE utf8mb4_unicode_ci COMMENT '其他保险保单',
  `used_quotas` text COLLATE utf8mb4_unicode_ci COMMENT '已使用的保险名额ID列表（JSON格式）',
  `last_snapshot` text COLLATE utf8mb4_unicode_ci COMMENT '上次保险配置快照',
  `change_summary` text COLLATE utf8mb4_unicode_ci COMMENT '变更摘要',
  `change_details` text COLLATE utf8mb4_unicode_ci COMMENT '详细变更记录（每行一条，格式：类型|操作|项目）',
  `status` enum('pending','processing','submitted','completed') COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT '状态：待处理、处理中、待提交汇总审批、已完成',
  `fully_confirmed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已完整确认处理（1=是，0=否）',
  `other_insurance_processed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已单独处理其他保险（1=是，0=否）',
  `attachment_path` text COLLATE utf8mb4_unicode_ci COMMENT '附件路径',
  `attachment_uploaded_at` timestamp NULL DEFAULT NULL COMMENT '附件上传时间',
  `processed_at` timestamp NULL DEFAULT NULL COMMENT '处理完成时间',
  `submitted_at` timestamp NULL DEFAULT NULL COMMENT '提交汇总审批时间',
  `completed_at` timestamp NULL DEFAULT NULL COMMENT '完成时间',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人',
  `processed_by` bigint unsigned DEFAULT NULL COMMENT '处理人',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `large_medical_insurance_config` text COLLATE utf8mb4_unicode_ci COMMENT '大额医疗保险配置快照数据（JSON格式）',
  `personnel_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '人员ID，用于与人员档案绑定关联',
  PRIMARY KEY (`id`),
  KEY `insurance_changes_employee_id_project_id_index` (`employee_id`,`project_id`),
  KEY `insurance_changes_status_index` (`status`),
  KEY `insurance_changes_account_set_id_index` (`account_set_id`),
  KEY `insurance_changes_social_security_region_id_index` (`social_security_region_id`),
  KEY `insurance_changes_medical_insurance_region_id_index` (`medical_insurance_region_id`),
  KEY `insurance_changes_housing_fund_id_index` (`housing_fund_region_id`),
  KEY `insurance_changes_created_by_index` (`created_by`),
  KEY `insurance_changes_processed_by_index` (`processed_by`),
  KEY `insurance_changes_project_id_foreign` (`project_id`),
  KEY `insurance_changes_housing_fund_region_id_index` (`housing_fund_region_id`),
  KEY `insurance_changes_housing_fund_config_id_index` (`housing_fund_config_id`),
  CONSTRAINT `insurance_changes_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insurance_changes_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insurance_changes_employee_id_foreign` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insurance_changes_housing_fund_config_id_foreign` FOREIGN KEY (`housing_fund_config_id`) REFERENCES `housing_fund_configs` (`id`) ON DELETE SET NULL,
  CONSTRAINT `insurance_changes_housing_fund_id_foreign` FOREIGN KEY (`housing_fund_region_id`) REFERENCES `housing_funds` (`id`) ON DELETE SET NULL,
  CONSTRAINT `insurance_changes_housing_fund_region_id_foreign` FOREIGN KEY (`housing_fund_region_id`) REFERENCES `housing_fund_regions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `insurance_changes_medical_insurance_region_id_foreign` FOREIGN KEY (`medical_insurance_region_id`) REFERENCES `medical_insurance_regions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `insurance_changes_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `insurance_changes_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `insurance_changes_social_security_region_id_foreign` FOREIGN KEY (`social_security_region_id`) REFERENCES `social_security_regions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='参保增减主表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insurance_compensation_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_compensation_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `employee_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '员工姓名',
  `employee_id_number` varchar(18) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '员工身份证号',
  `compensation_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '补差类型',
  `old_base` decimal(10,2) NOT NULL COMMENT '旧基数',
  `old_base_constrained` decimal(10,2) DEFAULT NULL COMMENT '旧基数（约束后）',
  `new_base` decimal(10,2) NOT NULL COMMENT '新基数',
  `new_base_constrained` decimal(10,2) DEFAULT NULL COMMENT '新基数（约束后）',
  `compensation_start_month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '补差开始月份 YYYY-MM',
  `compensation_end_month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '补差结束月份 YYYY-MM',
  `compensation_months` int NOT NULL COMMENT '补差月数',
  `compensation_details` json DEFAULT NULL COMMENT '补差明细JSON',
  `company_total` decimal(10,2) DEFAULT '0.00' COMMENT '单位补差合计',
  `personal_total` decimal(10,2) DEFAULT '0.00' COMMENT '个人补差合计',
  `total_amount` decimal(10,2) DEFAULT '0.00' COMMENT '补差总计',
  `base_adjustment_id` bigint unsigned DEFAULT NULL COMMENT '关联的基数调整记录ID',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' COMMENT '状态',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_compensation` (`employee_id`,`project_id`,`compensation_type`,`compensation_start_month`,`compensation_end_month`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_compensation_month` (`compensation_start_month`,`compensation_end_month`),
  KEY `idx_base_adjustment` (`base_adjustment_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='保险补差记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insurance_detail_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_detail_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `insurance_personnel_id` bigint unsigned NOT NULL COMMENT '参保人员信息ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `employee_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '员工姓名',
  `employee_id_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '员工身份证号',
  `employee_gender` tinyint DEFAULT NULL COMMENT '员工性别',
  `employee_birth_date` date DEFAULT NULL COMMENT '员工出生日期',
  `employee_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '员工电话',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `project_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目名称',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `record_year` int NOT NULL COMMENT '记录年份',
  `record_month` int NOT NULL COMMENT '记录月份',
  `employee_social_security_base` decimal(10,2) DEFAULT NULL COMMENT '员工社保基数',
  `employee_medical_insurance_base` decimal(10,2) DEFAULT NULL COMMENT '员工医保基数',
  `employee_housing_fund_base` decimal(10,2) DEFAULT NULL COMMENT '员工公积金基数',
  `employee_large_medical_base` decimal(10,2) DEFAULT NULL COMMENT '员工大额医疗保险基数',
  `social_security_types` text COLLATE utf8mb4_unicode_ci COMMENT '社保配置快照',
  `medical_insurance_types` text COLLATE utf8mb4_unicode_ci COMMENT '医保配置快照',
  `housing_fund_params` text COLLATE utf8mb4_unicode_ci COMMENT '公积金配置快照',
  `other_insurance_policies` text COLLATE utf8mb4_unicode_ci COMMENT '其他保险配置快照',
  `large_medical_insurance_config` text COLLATE utf8mb4_unicode_ci COMMENT '大额医疗保险配置快照',
  `social_security_company_amount` decimal(10,2) DEFAULT NULL COMMENT '社保公司缴纳金额',
  `social_security_employee_amount` decimal(10,2) DEFAULT NULL COMMENT '社保员工缴纳金额',
  `medical_insurance_company_amount` decimal(10,2) DEFAULT NULL COMMENT '医保公司缴纳金额',
  `medical_insurance_employee_amount` decimal(10,2) DEFAULT NULL COMMENT '医保员工缴纳金额',
  `housing_fund_company_amount` decimal(10,2) DEFAULT NULL COMMENT '公积金公司缴纳金额',
  `housing_fund_employee_amount` decimal(10,2) DEFAULT NULL COMMENT '公积金员工缴纳金额',
  `large_medical_company_amount` decimal(10,2) DEFAULT NULL COMMENT '大额医疗保险公司缴纳金额',
  `large_medical_employee_amount` decimal(10,2) DEFAULT NULL COMMENT '大额医疗保险员工缴纳金额',
  `other_insurance_total_amount` decimal(10,2) DEFAULT NULL COMMENT '其他保险总金额',
  `status` enum('generated','confirmed','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generated' COMMENT '状态',
  `generated_at` timestamp NULL DEFAULT NULL COMMENT '生成时间',
  `confirmed_at` timestamp NULL DEFAULT NULL COMMENT '确认时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_project_month` (`employee_id`,`project_id`,`account_set_id`,`record_year`,`record_month`),
  KEY `idx_insurance_personnel_id` (`insurance_personnel_id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_record_date` (`record_year`,`record_month`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='参保明细记录表（按月存储）';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `insurance_personnel`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_personnel` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `employee_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '员工姓名',
  `employee_id_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '员工身份证号',
  `employee_gender` tinyint DEFAULT NULL COMMENT '员工性别',
  `employee_birth_date` date DEFAULT NULL COMMENT '员工出生日期',
  `employee_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '员工电话',
  `employee_status` tinyint DEFAULT NULL COMMENT '员工状态',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `social_security_region_id` bigint unsigned DEFAULT NULL COMMENT '社保地区ID',
  `social_security_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '社保编号',
  `medical_insurance_region_id` bigint unsigned DEFAULT NULL COMMENT '医保地区ID',
  `medical_insurance_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '医保编号',
  `housing_fund_region_id` bigint unsigned DEFAULT NULL COMMENT '公积金地区ID',
  `housing_fund_account_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '公积金账号',
  `housing_fund_config_id` bigint unsigned DEFAULT NULL COMMENT '公积金配置ID',
  `large_medical_insurance_config_id` bigint unsigned DEFAULT NULL COMMENT '大额医疗保险配置ID',
  `large_medical_insurance_enabled` tinyint(1) NOT NULL DEFAULT '0' COMMENT '大额医疗保险是否启用',
  `employee_social_security_base` decimal(10,2) DEFAULT NULL COMMENT '员工社保基数',
  `employee_medical_insurance_base` decimal(10,2) DEFAULT NULL COMMENT '员工医保基数',
  `employee_housing_fund_base` decimal(10,2) DEFAULT NULL COMMENT '员工公积金基数',
  `employee_large_medical_base` decimal(10,2) DEFAULT NULL COMMENT '员工大额医疗保险基数',
  `social_security_types` text COLLATE utf8mb4_unicode_ci COMMENT '社保配置快照',
  `medical_insurance_types` text COLLATE utf8mb4_unicode_ci COMMENT '医保配置快照',
  `housing_fund_params` text COLLATE utf8mb4_unicode_ci COMMENT '公积金配置快照',
  `other_insurance_policies` text COLLATE utf8mb4_unicode_ci COMMENT '其他保险配置快照',
  `is_compensation` tinyint(1) DEFAULT '0' COMMENT '是否为补差记录：0-正常，1-补差',
  `compensation_months` int DEFAULT NULL COMMENT '补差月数',
  `compensation_start_month` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '补差开始月份 YYYY-MM',
  `compensation_end_month` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '补差结束月份 YYYY-MM',
  `old_base` decimal(10,2) DEFAULT NULL COMMENT '旧基数',
  `new_base` decimal(10,2) DEFAULT NULL COMMENT '新基数',
  `other_insurance_enrolled_at` timestamp NULL DEFAULT NULL COMMENT '其他保险参保时间（首次生成明细的时间）',
  `other_insurance_policy_versions` text COLLATE utf8mb4_unicode_ci COMMENT '已参保的保单版本信息（JSON格式）',
  `large_medical_insurance_config` text COLLATE utf8mb4_unicode_ci COMMENT '大额医疗保险配置快照',
  `used_quotas` text COLLATE utf8mb4_unicode_ci COMMENT '已用名额信息',
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '状态',
  `first_confirmation_date` date DEFAULT NULL COMMENT '首次确认处理日期',
  `payment_period` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '费款所属期（如：202507）',
  `enrollment_period` varchar(6) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '参保日期（入职日期）',
  `employee_type` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '正常' COMMENT '员工类型：正常/补交',
  `last_updated_at` timestamp NULL DEFAULT NULL COMMENT '最后更新时间',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `large_medical_payment_start_month` int DEFAULT NULL COMMENT '大额医疗保险支付起始月份(1-12)，年付模式下使用',
  `large_medical_payment_start_year` int DEFAULT NULL COMMENT '大额医疗保险支付起始年份，年付模式下使用',
  `large_medical_last_payment_month` int DEFAULT NULL COMMENT '上次支付月份(1-12)，用于跟踪支付历史',
  `large_medical_last_payment_year` int DEFAULT NULL COMMENT '上次支付年份，用于跟踪支付历史',
  `position` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_project_compensation` (`employee_id`,`project_id`,`is_compensation`,`compensation_start_month`,`compensation_end_month`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_status` (`status`),
  KEY `idx_large_medical_payment` (`large_medical_insurance_enabled`,`large_medical_payment_start_month`,`large_medical_payment_start_year`),
  KEY `idx_employee_type` (`employee_type`),
  KEY `idx_first_confirmation_date` (`first_confirmation_date`),
  KEY `idx_payment_period` (`payment_period`),
  KEY `idx_enrollment_period` (`enrollment_period`),
  KEY `idx_is_compensation` (`is_compensation`),
  KEY `idx_compensation_month` (`compensation_start_month`,`compensation_end_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='参保人员信息表（确认后的参保信息）';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `tr_insurance_personnel_update_payment_history` AFTER UPDATE ON `insurance_personnel` FOR EACH ROW BEGIN
    -- 如果大额医疗保险状态从关闭变为开启，更新支付起始时间
    IF OLD.large_medical_insurance_enabled = 0 AND NEW.large_medical_insurance_enabled = 1 THEN
        UPDATE insurance_personnel 
        SET 
            large_medical_payment_start_month = MONTH(NOW()),
            large_medical_payment_start_year = YEAR(NOW()),
            large_medical_last_payment_month = MONTH(NOW()),
            large_medical_last_payment_year = YEAR(NOW())
        WHERE id = NEW.id;
    END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
DROP TABLE IF EXISTS `insurance_records`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `insurance_records` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `employee_id` bigint unsigned NOT NULL,
  `project_id` bigint unsigned NOT NULL,
  `insurance_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险类型',
  `base_amount` decimal(10,2) NOT NULL COMMENT '缴费基数',
  `company_rate` decimal(6,4) NOT NULL COMMENT '公司缴费比例',
  `personal_rate` decimal(6,4) NOT NULL COMMENT '个人缴费比例',
  `company_amount` decimal(10,2) NOT NULL COMMENT '公司缴费',
  `personal_amount` decimal(10,2) NOT NULL COMMENT '个人缴费',
  `total_amount` decimal(10,2) NOT NULL COMMENT '总缴费',
  `payment_date` date DEFAULT NULL COMMENT '缴费日期',
  `due_date` date DEFAULT NULL COMMENT '到期日期',
  `status` enum('pending','paid','overdue') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `employee_id` (`employee_id`) USING BTREE,
  KEY `project_id` (`project_id`) USING BTREE,
  KEY `idx_insurance_records_type` (`insurance_type`) USING BTREE,
  KEY `idx_employee_id` (`employee_id`) USING BTREE,
  KEY `idx_project_id` (`project_id`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_due_date` (`due_date`) USING BTREE,
  KEY `idx_insurance_records_account_set_id` (`account_set_id`),
  CONSTRAINT `insurance_records_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `insurance_records_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `insurance_records_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `application_no` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '申请单号',
  `task_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '任务名称/描述',
  `year` int NOT NULL COMMENT '年度',
  `month` int NOT NULL COMMENT '月份',
  `project_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目名称',
  `period_year` int DEFAULT NULL COMMENT '所属期-年份',
  `period_month` int DEFAULT NULL COMMENT '所属期-月份',
  `company_name` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '单位名称',
  `application_date` date DEFAULT NULL COMMENT '申请日期',
  `invoice_method` enum('full','partial','none') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '开票方式：full-全额，partial-缺额，none-无',
  `invoice_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '普票' COMMENT '开票种类：普票、专票等',
  `deduction_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '扣除额',
  `tax_rate` decimal(5,4) NOT NULL DEFAULT '0.0000' COMMENT '税率（如0.06表示6%）',
  `amount_excluding_tax` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '不含税金额',
  `invoice_tax_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '开票税额',
  `tax_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '税金',
  `invoice_date` date DEFAULT NULL COMMENT '开票日期',
  `is_completed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否完成',
  `invoicer` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '开票人',
  `invoice_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '发票号码',
  `invoice_remark` text COLLATE utf8mb4_unicode_ci COMMENT '开票备注',
  `status` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '状态：draft-草稿，normal-正常，pending-审批中，approved-已通过，rejected-已驳回，red_flushed-红冲',
  `approval_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '审批状态：pending-审批中，approved-已通过，rejected-已驳回',
  `has_resubmitted` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已重新发起（红冲后）',
  `new_application_id` bigint DEFAULT NULL COMMENT '重新发起后的新申请ID',
  `approval_instance_id` bigint unsigned DEFAULT NULL COMMENT '审批实例ID',
  `submitter_id` bigint unsigned NOT NULL COMMENT '提交人ID（业务人员）',
  `submitted_at` timestamp NULL DEFAULT NULL COMMENT '提交时间',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci COMMENT '驳回原因',
  `attachments` json DEFAULT NULL COMMENT '附件列表',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_application_no` (`application_no`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_year_month` (`year`,`month`),
  KEY `idx_status` (`status`),
  KEY `idx_submitter` (`submitter_id`),
  KEY `idx_approval` (`approval_instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票申请表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `application_id` bigint unsigned NOT NULL COMMENT '发票申请ID',
  `invoice_project_id` bigint unsigned NOT NULL COMMENT '项目配置ID',
  `project_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称（冗余，用于生成PDF）',
  `sequence` int NOT NULL DEFAULT '1' COMMENT '序号',
  `item_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '名称（应发工资/劳保费/商业险）',
  `amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '金额',
  `remark` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_application` (`application_id`),
  KEY `idx_invoice_project` (`invoice_project_id`),
  CONSTRAINT `fk_invoice_items_application` FOREIGN KEY (`application_id`) REFERENCES `invoice_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票申请明细表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoice_projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoice_projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `project_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='发票项目配置表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `invoices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `invoices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `project_id` bigint unsigned NOT NULL,
  `invoice_number` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `tax_amount` decimal(12,2) NOT NULL DEFAULT '0.00',
  `tax_rate` decimal(4,4) NOT NULL DEFAULT '0.0000',
  `total_amount` decimal(12,2) NOT NULL,
  `type` enum('vat_special','vat_ordinary','ordinary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_date` date DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `deduction_details` varchar(0) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('draft','submitted','approved','issued','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `applicant_id` bigint unsigned NOT NULL,
  `approver_id` bigint unsigned DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `invoices_invoice_number_unique` (`invoice_number`) USING BTREE,
  KEY `invoices_project_id_foreign` (`project_id`) USING BTREE,
  KEY `invoices_applicant_id_foreign` (`applicant_id`) USING BTREE,
  KEY `invoices_approver_id_foreign` (`approver_id`) USING BTREE,
  KEY `idx_invoices_account_set_id` (`account_set_id`),
  CONSTRAINT `invoices_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `invoices_applicant_id_foreign` FOREIGN KEY (`applicant_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `invoices_approver_id_foreign` FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `invoices_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `large_medical_insurance_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `large_medical_insurance_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `region_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `account_set_id` bigint unsigned NOT NULL COMMENT '所属账套ID',
  `calculation_type` enum('base','fixed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'base' COMMENT '计算方式：base=按基数，fixed=按固定金额',
  `base_amount` decimal(10,2) DEFAULT NULL COMMENT '基数金额',
  `company_ratio` decimal(5,2) DEFAULT NULL COMMENT '公司承担比例(%)',
  `employee_ratio` decimal(5,2) DEFAULT NULL COMMENT '员工承担比例(%)',
  `company_amount` decimal(10,2) DEFAULT NULL COMMENT '公司固定金额',
  `employee_amount` decimal(10,2) DEFAULT NULL COMMENT '员工固定金额',
  `payment_cycle` enum('month','year') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'month' COMMENT '付款周期：month=按月，year=按年',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态：1=启用，0=禁用',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_region_account` (`region_name`,`account_set_id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_region` (`region_name`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='大额医疗保险地区配置表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_insurance_regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medical_insurance_regions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '医保编号',
  `min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '最低基数',
  `max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '最高基数',
  `old_min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '旧最低基数',
  `old_max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '旧最高基数',
  `old_limits_updated_at` timestamp NULL DEFAULT NULL COMMENT '旧上下限修改时间',
  `new_min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '新最低基数',
  `new_max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '新最高基数',
  `new_limits_updated_at` timestamp NULL DEFAULT NULL COMMENT '新上下限修改时间',
  `account_set_id` bigint unsigned NOT NULL COMMENT '所属账套ID',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_region_per_account` (`name`,`account_set_id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `medical_insurance_regions_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_insurance_regions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='医保地区表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `medical_insurance_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `medical_insurance_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `region_id` bigint unsigned NOT NULL COMMENT '所属地区ID',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险名称（如：医疗保险）',
  `min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '下限基数',
  `max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '上限基数',
  `employee_ratio` decimal(6,4) NOT NULL COMMENT '员工缴纳比例',
  `company_ratio` decimal(6,4) NOT NULL COMMENT '公司缴纳比例',
  `account_set_id` bigint unsigned NOT NULL COMMENT '所属账套ID',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_region_id` (`region_id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `medical_insurance_types_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_insurance_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `medical_insurance_types_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `medical_insurance_regions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='医保类型表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `user_id` (`user_id`) USING BTREE,
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `onboarding_forms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `onboarding_forms` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `employee_id` bigint unsigned NOT NULL COMMENT '员工ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `registration_date` date NOT NULL COMMENT '登记日期',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '姓名',
  `gender` enum('male','female') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '性别',
  `ethnicity` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '民族',
  `political_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '政治面貌',
  `place_of_origin` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '籍贯',
  `birth_date` date DEFAULT NULL COMMENT '出生年月',
  `graduated_school` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '毕业学校',
  `graduation_date` date DEFAULT NULL COMMENT '毕业时间',
  `education_level` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文化程度',
  `major` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '所学专业',
  `degree` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '学位',
  `technical_title` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '技术职称',
  `health_status` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '健康状况',
  `height` int DEFAULT NULL COMMENT '身高(cm)',
  `weight` decimal(5,2) DEFAULT NULL COMMENT '体重(kg)',
  `marital_status` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '婚姻状况',
  `id_number` varchar(18) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '身份证号码',
  `current_residence` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '现居住地',
  `household_registration` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '户口所在地',
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '岗位',
  `desired_location` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '求职地区',
  `accept_assignment` tinyint(1) DEFAULT NULL COMMENT '是否服从调配',
  `contact_address` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系地址',
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系电话',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `declaration_agreed` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否同意声明',
  `signature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '本人签名图片路径',
  `education_background` json DEFAULT NULL COMMENT '学习简历',
  `work_experience` json DEFAULT NULL COMMENT '工作经历',
  `family_info` json DEFAULT NULL COMMENT '家庭情况',
  `created_at` timestamp NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_employee_id` (`employee_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_id_number` (`id_number`),
  CONSTRAINT `fk_onboarding_forms_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='入职登记表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `other_insurance_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `other_insurance_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type_id` bigint unsigned NOT NULL COMMENT '保险种类ID',
  `policy_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保单号',
  `policy_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保单名称',
  `insurance_company` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险公司',
  `coverage_amount` decimal(15,2) NOT NULL COMMENT '保险金额',
  `employee_per_capita_cost` decimal(10,2) DEFAULT NULL COMMENT '员工人均参保费用',
  `quota` int unsigned NOT NULL DEFAULT '0' COMMENT '名额',
  `contact_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人姓名',
  `contact_phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '联系人电话',
  `personnel_name_list` text COLLATE utf8mb4_unicode_ci,
  `endorsement_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '批单号',
  `policy_end_date` date DEFAULT NULL COMMENT '保单结束时间',
  `premium_amount` decimal(10,2) DEFAULT NULL COMMENT '保费金额',
  `start_date` date NOT NULL COMMENT '保险开始日期',
  `end_date` date NOT NULL COMMENT '保险结束日期',
  `status` enum('active','expired','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '保单状态',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '保单描述',
  `account_set_id` bigint unsigned NOT NULL COMMENT '所属账套ID',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_policy_number` (`policy_number`,`account_set_id`),
  KEY `idx_type_id` (`type_id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`,`end_date`),
  CONSTRAINT `other_insurance_policies_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `other_insurance_policies_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `other_insurance_policies_type_id_foreign` FOREIGN KEY (`type_id`) REFERENCES `other_insurance_types` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='其他保险保单表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `other_insurance_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `other_insurance_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险种类名称（如：安责险）',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '保险种类描述',
  `account_set_id` bigint unsigned NOT NULL COMMENT '所属账套ID',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_type_per_account` (`name`,`account_set_id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `other_insurance_types_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `other_insurance_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='其他保险种类表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL,
  `process_approval_id` bigint unsigned NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_ids` json NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `initiator_id` bigint unsigned NOT NULL,
  `approval_instance_id` bigint unsigned DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_process_approval_id` (`process_approval_id`),
  KEY `idx_initiator_id` (`initiator_id`),
  KEY `idx_approval_instance_id` (`approval_instance_id`),
  KEY `idx_status` (`status`),
  KEY `idx_month` (`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_application_id` bigint unsigned NOT NULL,
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_application_id` (`payment_application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_form_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_form_history` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bank_account` varchar(100) NOT NULL COMMENT '银行账号（唯一标识）',
  `department` varchar(100) DEFAULT NULL COMMENT '所在部门',
  `payee` varchar(100) DEFAULT NULL COMMENT '支付对象',
  `amount_small` varchar(50) DEFAULT NULL COMMENT '小写金额',
  `amount_large` varchar(100) DEFAULT NULL COMMENT '大写金额',
  `payment_method` text COMMENT '付款方式（JSON数组）',
  `bank` varchar(100) DEFAULT NULL COMMENT '开户行',
  `purpose` text COMMENT '付款用途',
  `invoice_status` text COMMENT '开票情况（JSON数组）',
  `user_id` bigint unsigned DEFAULT NULL COMMENT '创建用户ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_bank_account` (`bank_account`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='付款申请单历史记忆表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_request_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_request_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_request_id` bigint unsigned NOT NULL COMMENT '付款申请ID',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件存储路径',
  `file_size` bigint NOT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件MIME类型',
  `uploaded_by` bigint unsigned NOT NULL COMMENT '上传人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_request_id` (`payment_request_id`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款申请附件表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reimbursement' COMMENT '付款类型：salary/insurance/reimbursement/报销/差旅/采购/项目/其他',
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '类目：报销/差旅/采购/项目/其他',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `insurance_summary_id` bigint unsigned DEFAULT NULL COMMENT '保险汇总ID（当payment_type=insurance时使用）',
  `salary_approval_id` bigint unsigned DEFAULT NULL COMMENT '工资表审批ID（当payment_type=salary时使用）',
  `reimbursement_id` bigint unsigned DEFAULT NULL COMMENT '报销申请ID（当payment_type=reimbursement时使用）',
  `approval_instance_id` bigint unsigned DEFAULT NULL COMMENT '审批实例ID',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '付款金额',
  `status` enum('pending','approved','rejected','paid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态',
  `submitted_by` bigint unsigned NOT NULL COMMENT '提交人ID',
  `submitted_at` timestamp NULL DEFAULT NULL COMMENT '提交时间',
  `approved_by` bigint unsigned DEFAULT NULL COMMENT '审批人ID',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '审批时间',
  `paid_by` bigint unsigned DEFAULT NULL COMMENT '付款人ID',
  `paid_at` timestamp NULL DEFAULT NULL COMMENT '付款时间',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci COMMENT '驳回原因',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `project` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目',
  `apply_date` date DEFAULT NULL COMMENT '申请日期',
  `unit_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '单位名称',
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '发票号码',
  `verified` tinyint(1) DEFAULT '1' COMMENT '查验（1=已查验）',
  `payment_date` date DEFAULT NULL COMMENT '打款日期',
  `expenditure_amount` decimal(15,2) DEFAULT NULL COMMENT '支出金额',
  `project_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目名称',
  `summary` text COLLATE utf8mb4_unicode_ci COMMENT '摘要',
  `invoice_received` tinyint(1) DEFAULT '0' COMMENT '收到发票（1=已收到）',
  `invoice_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '发票类型',
  `invoice_amount` decimal(15,2) DEFAULT NULL COMMENT '开票金额',
  `tax_rate` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '税率',
  `deduction_amount` decimal(15,2) DEFAULT NULL COMMENT '扣除额',
  `amount_excluding_tax` decimal(15,2) DEFAULT NULL COMMENT '不含税金额',
  `tax_amount` decimal(15,2) DEFAULT NULL COMMENT '税金',
  `is_consistent` tinyint(1) DEFAULT '0' COMMENT '是否一致（1=一致）',
  `status_checked` tinyint(1) DEFAULT '1' COMMENT '状态（1=已确认）',
  `selected_month` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '勾选月份（YYYY-MM）',
  `reimburser` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '报销人',
  `invoice_date` date DEFAULT NULL COMMENT '开票日期',
  `accounted` tinyint(1) DEFAULT '1' COMMENT '入账（1=已入账）',
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '公司',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_insurance_summary_id` (`insurance_summary_id`),
  KEY `idx_salary_approval_id` (`salary_approval_id`),
  KEY `idx_status` (`status`),
  KEY `idx_submitted_by` (`submitted_by`),
  KEY `idx_reimbursement_id` (`reimbursement_id`),
  CONSTRAINT `fk_payment_requests_reimbursement_id` FOREIGN KEY (`reimbursement_id`) REFERENCES `reimbursements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款申请表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payment_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payment_summaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `payment_request_id` bigint unsigned NOT NULL COMMENT '付款申请ID',
  `payment_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '付款类型：salary/insurance/reimbursement/报销/差旅/采购/项目/其他',
  `category` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '类目：报销/差旅/采购/项目/其他',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '月份（YYYY-MM）',
  `project` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目',
  `apply_date` date DEFAULT NULL COMMENT '申请日期',
  `unit_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '单位名称',
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '发票号码',
  `verified` tinyint(1) DEFAULT '1' COMMENT '查验（1=已查验）',
  `payment_date` date DEFAULT NULL COMMENT '打款日期',
  `expenditure_amount` decimal(15,2) DEFAULT NULL COMMENT '支出金额',
  `project_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目名称',
  `summary` text COLLATE utf8mb4_unicode_ci COMMENT '摘要',
  `invoice_received` tinyint(1) DEFAULT '0' COMMENT '收到发票（1=已收到）',
  `invoice_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '发票类型',
  `invoice_amount` decimal(15,2) DEFAULT NULL COMMENT '开票金额',
  `tax_rate` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '税率',
  `deduction_amount` decimal(15,2) DEFAULT NULL COMMENT '扣除额',
  `amount_excluding_tax` decimal(15,2) DEFAULT NULL COMMENT '不含税金额',
  `tax_amount` decimal(15,2) DEFAULT NULL COMMENT '税金',
  `is_consistent` tinyint(1) DEFAULT '0' COMMENT '是否一致（1=一致）',
  `status_checked` tinyint(1) DEFAULT '1' COMMENT '状态（1=已确认）',
  `selected_month` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '勾选月份（YYYY-MM）',
  `reimburser` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '报销人',
  `invoice_date` date DEFAULT NULL COMMENT '开票日期',
  `accounted` tinyint(1) DEFAULT '1' COMMENT '入账（1=已入账）',
  `company` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '公司',
  `amount` decimal(15,2) NOT NULL COMMENT '付款金额',
  `approved_at` datetime DEFAULT NULL COMMENT '审批通过时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_request_id` (`payment_request_id`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_month` (`month`),
  KEY `idx_approved_at` (`approved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='出款汇总表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `project_id` bigint unsigned NOT NULL,
  `type` enum('salary','social_security','commercial_insurance','reimbursement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `payment_date` date DEFAULT NULL,
  `status` enum('draft','pending','approved','paid','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attachments` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `applicant_id` bigint DEFAULT NULL,
  `submitted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `project_id` (`project_id`) USING BTREE,
  KEY `idx_payments_status` (`status`) USING BTREE,
  KEY `idx_payments_account_set_id` (`account_set_id`),
  CONSTRAINT `payments_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `payroll_remarks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `payroll_remarks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `project_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称',
  `year` int NOT NULL COMMENT '年份',
  `month` int NOT NULL COMMENT '月份',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '工资表备注',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建人',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新人',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_payroll_remark` (`account_set_id`,`project_name`,`year`,`month`),
  KEY `idx_payroll_remark` (`account_set_id`,`project_name`,`year`,`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`) USING BTREE,
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`(191),`tokenable_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personnel_change_request_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personnel_change_request_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `personnel_change_request_id` bigint unsigned NOT NULL COMMENT '人员变动申请ID',
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名',
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件类型',
  `file_size` bigint DEFAULT NULL COMMENT '文件大小（字节）',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `personnel_change_request_attachments_request_id_foreign` (`personnel_change_request_id`),
  CONSTRAINT `personnel_change_request_attachments_request_id_foreign` FOREIGN KEY (`personnel_change_request_id`) REFERENCES `personnel_change_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='人员变动申请附件表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personnel_change_requests`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personnel_change_requests` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `month` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '工资期间（YYYY-MM）',
  `change_type` enum('add','remove') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '变动类型：add新增，remove减少',
  `personnel_list` json NOT NULL COMMENT '人员列表 [{"id_card":"xxx", "name":"xxx"}]',
  `remark` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：pending待审批, in_approval审批中, approved已通过, rejected已拒绝',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人',
  `approval_flow_id` bigint unsigned DEFAULT NULL COMMENT '审批流程ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_month_type` (`project_id`,`month`,`change_type`,`deleted_at`),
  KEY `personnel_change_requests_account_set_id_foreign` (`account_set_id`),
  KEY `personnel_change_requests_project_id_foreign` (`project_id`),
  KEY `personnel_change_requests_created_by_foreign` (`created_by`),
  KEY `personnel_change_requests_approval_flow_id_foreign` (`approval_flow_id`),
  CONSTRAINT `personnel_change_requests_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personnel_change_requests_approval_flow_id_foreign` FOREIGN KEY (`approval_flow_id`) REFERENCES `approval_instances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `personnel_change_requests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personnel_change_requests_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='人员变动申请表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `process_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `process_approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `approval_instance_id` int unsigned DEFAULT NULL COMMENT '关联的审批实例ID',
  `initiator_id` bigint unsigned NOT NULL COMMENT '发起人ID',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '流程标题',
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '审批月份 (YYYY-MM)',
  `project_ids` json DEFAULT NULL COMMENT '关联项目IDs (JSON数组)',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '流程描述',
  `status` enum('draft','pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT '流程状态: draft-草稿, pending-待审批, approved-已通过, rejected-已驳回',
  `current_approver_id` bigint unsigned DEFAULT NULL COMMENT '当前审批人ID',
  `approved_at` datetime DEFAULT NULL COMMENT '审批通过时间',
  `rejected_at` datetime DEFAULT NULL COMMENT '审批驳回时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `process_approvals_account_set_id_foreign` (`account_set_id`),
  KEY `process_approvals_initiator_id_foreign` (`initiator_id`),
  KEY `process_approvals_current_approver_id_foreign` (`current_approver_id`),
  KEY `idx_status` (`status`),
  KEY `idx_month` (`month`),
  KEY `idx_approval_instance_id` (`approval_instance_id`),
  CONSTRAINT `process_approvals_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `process_approvals_current_approver_id_foreign` FOREIGN KEY (`current_approver_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `process_approvals_initiator_id_foreign` FOREIGN KEY (`initiator_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='流程审批表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `process_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `process_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `process_approval_id` bigint unsigned NOT NULL COMMENT '流程审批ID',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名',
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件存储路径',
  `file_size` bigint NOT NULL COMMENT '文件大小 (bytes)',
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MIME类型',
  `uploaded_by` bigint unsigned NOT NULL COMMENT '上传人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `process_attachments_process_approval_id_foreign` (`process_approval_id`),
  KEY `process_attachments_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `process_attachments_process_approval_id_foreign` FOREIGN KEY (`process_approval_id`) REFERENCES `process_approvals` (`id`) ON DELETE CASCADE,
  CONSTRAINT `process_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='流程附件表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `project_delivery_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_delivery_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `delivery_cycle` enum('monthly','quarterly') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly' COMMENT '交付周期：monthly-按月，quarterly-按季度',
  `delivery_method` enum('express','electronic') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'electronic' COMMENT '交付方式：express-快递，electronic-电子推送',
  `required_documents` text COLLATE utf8mb4_unicode_ci COMMENT '需交付的资料清单（JSON格式存储）',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '是否启用：1-启用，0-禁用',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建人ID',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project` (`project_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_delivery_cycle` (`delivery_cycle`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目资料交付配置表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `project_document_configs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_document_configs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `document_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '资料名称（如：身份证照片、驾驶证等）',
  `document_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all' COMMENT '文件类型限制(image/pdf/document/all)',
  `is_required` tinyint(1) DEFAULT '1' COMMENT '是否必填：1=必填，0=选填',
  `sort_order` int DEFAULT '0' COMMENT '排序',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目资料配置表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `project_large_medical_insurance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_large_medical_insurance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `config_id` bigint unsigned NOT NULL COMMENT '大额医疗保险配置ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '所属账套ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_config` (`project_id`,`config_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_config` (`config_id`),
  KEY `idx_account_set` (`account_set_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目-大额医疗保险关联表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `project_medical_insurance`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_medical_insurance` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `region_id` bigint unsigned NOT NULL COMMENT '医保地区ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '所属账套ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_region` (`project_id`,`region_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_region_id` (`region_id`),
  KEY `idx_account_set` (`account_set_id`),
  CONSTRAINT `project_medical_insurance_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_medical_insurance_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_medical_insurance_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `medical_insurance_regions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目医保关联表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `project_other_insurance_policies`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `project_other_insurance_policies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `policy_id` bigint unsigned NOT NULL COMMENT '保单ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '所属账套ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_policy` (`project_id`,`policy_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_policy_id` (`policy_id`),
  KEY `idx_account_set` (`account_set_id`),
  CONSTRAINT `project_other_insurance_policies_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_other_insurance_policies_policy_id_foreign` FOREIGN KEY (`policy_id`) REFERENCES `other_insurance_policies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `project_other_insurance_policies_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目其他保险保单关联表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `projects`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `projects` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `salary_payment_month` enum('current','next') COLLATE utf8mb4_unicode_ci DEFAULT 'current' COMMENT '工资发放月份：current-本月，next-次月',
  `insurance_import_month` enum('current','next','none') COLLATE utf8mb4_unicode_ci DEFAULT 'current' COMMENT '保险导入设置：current-当月，next-次月，none-不导入',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `require_attendance` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否需要考勤：1-需要，0-不需要',
  `contract_notice_file_id` bigint unsigned DEFAULT NULL COMMENT '劳动合同须知文件ID（单个文件）',
  `social_security_regions` text COLLATE utf8mb4_unicode_ci COMMENT '社保地区ID列表，JSON格式',
  `housing_fund_regions` text COLLATE utf8mb4_unicode_ci COMMENT '公积金地区ID列表，JSON格式',
  `contract_notice_files` text COLLATE utf8mb4_unicode_ci COMMENT '劳动合同须知文件ID列表，逗号分隔格式: 1,2,3',
  `social_security_location` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '参保地',
  `insurance_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '参保项目',
  `salary_payment_date` int DEFAULT NULL COMMENT '工资发放日期（每月几号，1-31）',
  `requires_attendance` tinyint(1) DEFAULT '1' COMMENT '是否需要考勤表',
  `requires_salary_basis` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要上传工资依据',
  `requires_attendance_basis` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要上传考勤依据',
  `delivery_requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin COMMENT '交付资料要求',
  `delivery_frequency` enum('monthly','quarterly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'monthly' COMMENT '交付频率',
  `delivery_method` enum('express','electronic') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'electronic' COMMENT '交付方式',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `labor_contract_notice_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '劳动合同须知文件名称',
  `labor_contract_notice_file` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '劳动合同须知文件路径',
  `medical_insurance_regions` json DEFAULT NULL COMMENT '医保地区ID列表',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `code` (`code`) USING BTREE,
  KEY `idx_name` (`name`) USING BTREE,
  KEY `idx_code` (`code`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_created_at` (`created_at`) USING BTREE,
  KEY `idx_projects_account_set_id` (`account_set_id`),
  KEY `idx_notice_file` (`contract_notice_file_id`),
  CONSTRAINT `projects_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recruitment`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recruitment` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `project_id` bigint unsigned NOT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `required_count` int NOT NULL,
  `requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `salary_min` decimal(10,2) DEFAULT NULL,
  `salary_max` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','active','completed','paused','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：pending-待分配, active-进行中, completed-已完成, paused-已暂停, cancelled-已取消',
  `assigned_to` bigint unsigned DEFAULT NULL,
  `progress_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `candidates` varchar(0) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `hired_count` int NOT NULL DEFAULT '0',
  `completion_notes` text COLLATE utf8mb4_unicode_ci COMMENT '完成情况说明',
  `candidates_summary` text COLLATE utf8mb4_unicode_ci COMMENT '候选人信息汇总',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注说明',
  `deadline` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '部门',
  `salary_range` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '薪资范围',
  `work_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '工作地点',
  `education` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '学历要求',
  `experience` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '工作经验',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci COMMENT '职位描述',
  `start_date` date DEFAULT NULL COMMENT '开始日期',
  `end_date` date DEFAULT NULL COMMENT '结束日期',
  `assigned_at` datetime DEFAULT NULL,
  `applied_count` int NOT NULL DEFAULT '0' COMMENT '申请人数',
  `interviewed_count` int NOT NULL DEFAULT '0' COMMENT '面试人数',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `recruitment_project_id_foreign` (`project_id`) USING BTREE,
  KEY `recruitment_assigned_to_foreign` (`assigned_to`) USING BTREE,
  CONSTRAINT `recruitment_assigned_to_foreign` FOREIGN KEY (`assigned_to`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE RESTRICT,
  CONSTRAINT `recruitment_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recruitment_candidates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recruitment_candidates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `recruitment_id` bigint unsigned NOT NULL COMMENT '招聘需求ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '姓名',
  `gender` enum('male','female') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '性别：male-男，female-女',
  `age` int DEFAULT NULL COMMENT '年龄',
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '联系电话',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '邮箱',
  `education` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '学历：high_school, college, bachelor, master, doctor',
  `experience` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '工作经验',
  `status` enum('pending','interviewing','to_be_hired','hired','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：pending-待面试, interviewing-面试中, to_be_hired-待录用, hired-已录用, rejected-已拒绝',
  `interview_date` datetime DEFAULT NULL COMMENT '面试时间',
  `interview_result` text COLLATE utf8mb4_unicode_ci COMMENT '面试结果',
  `resume_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '简历文件URL',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建人ID',
  PRIMARY KEY (`id`),
  KEY `idx_recruitment` (`recruitment_id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_status` (`status`),
  KEY `idx_phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='招聘候选人表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recruitment_progress_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recruitment_progress_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `recruitment_id` bigint unsigned NOT NULL COMMENT '招聘需求ID',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `applied_count` int NOT NULL DEFAULT '0' COMMENT '申请人数',
  `interviewed_count` int NOT NULL DEFAULT '0' COMMENT '面试人数',
  `hired_count` int NOT NULL DEFAULT '0' COMMENT '录用人数',
  `progress_notes` text COLLATE utf8mb4_unicode_ci COMMENT '进度说明',
  `updated_by` bigint unsigned NOT NULL COMMENT '更新人ID',
  `updated_by_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '更新人姓名',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_recruitment` (`recruitment_id`),
  KEY `idx_account_set` (`account_set_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='招聘进度记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `recruitments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `recruitments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `project_id` bigint unsigned NOT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recruitment_count` int NOT NULL DEFAULT '1',
  `applied_count` int DEFAULT '0',
  `interviewed_count` int DEFAULT '0',
  `hired_count` int DEFAULT '0',
  `salary_range` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `work_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `education` enum('high_school','college','bachelor','master','doctor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `experience` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','completed','paused','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `required_count` bigint DEFAULT NULL,
  `salary_min` decimal(10,0) DEFAULT NULL,
  `salary_max` decimal(10,0) DEFAULT NULL,
  `deadline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `assigned_to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `progress_notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `candidates` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `project_id` (`project_id`) USING BTREE,
  KEY `idx_recruitment_status` (`status`) USING BTREE,
  KEY `idx_recruitments_account_set_id` (`account_set_id`),
  CONSTRAINT `recruitments_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `recruitments_ibfk_1` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `region_portals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `region_portals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `region_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `business_type` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '业务类型（如：社保、公积金、税务等）',
  `portal_name` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '网站名称',
  `portal_url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '网站地址',
  `login_account` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '登录账号（可选）',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT '备注说明',
  `sort_order` int DEFAULT '0' COMMENT '排序序号',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '是否启用：1-启用，0-禁用',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建人ID',
  `updated_by` bigint unsigned DEFAULT NULL COMMENT '更新人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_region_name` (`region_name`),
  KEY `idx_business_type` (`business_type`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='地区网页入口管理表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reimbursement_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reimbursement_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `reimbursement_id` bigint unsigned NOT NULL COMMENT '报销申请ID',
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件类型',
  `file_size` bigint DEFAULT NULL COMMENT '文件大小(字节)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_reimbursement_id` (`reimbursement_id`),
  CONSTRAINT `fk_reimbursement_attachments_reimbursement_id` FOREIGN KEY (`reimbursement_id`) REFERENCES `reimbursements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='报销附件表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `reimbursements`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `reimbursements` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '单位名称',
  `invoice_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '发票号码',
  `payment_date` date DEFAULT NULL COMMENT '打款日期',
  `applicant` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '报销人',
  `amount` decimal(10,2) NOT NULL COMMENT '报销金额',
  `category` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '类目',
  `project` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '项目',
  `received_invoice` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '收到发票(是/否)',
  `invoice_type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '发票类型',
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '报销事由',
  `invoice_amount` decimal(10,2) DEFAULT NULL COMMENT '开票金额',
  `tax_rate` decimal(5,2) DEFAULT NULL COMMENT '税率(%)',
  `tax_deduction` decimal(10,2) DEFAULT NULL COMMENT '扣税额',
  `amount_excluding_tax` decimal(10,2) DEFAULT NULL COMMENT '不含税金额',
  `tax_amount` decimal(10,2) DEFAULT NULL COMMENT '税金',
  `invoice_date` date DEFAULT NULL COMMENT '开票日期',
  `record_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '状态(是/否)',
  `accounting_status` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '入账(是/否)',
  `verification` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '查验(是/否)',
  `is_consistent` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '是否一致(是/否)',
  `check_date` date DEFAULT NULL COMMENT '勾选日期',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态: pending-待审批, approved-已通过, rejected-已拒绝',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `approval_flow_id` bigint unsigned DEFAULT NULL COMMENT '审批流程ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_status` (`status`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='报销申请表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `seq_number` int DEFAULT NULL COMMENT '序号',
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `employee_id` bigint unsigned NOT NULL,
  `id_card` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '身份证号',
  `employee_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '员工姓名',
  `department` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '所在部门',
  `position` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '岗位',
  `insurance_import_setting` enum('current','next','none') COLLATE utf8mb4_unicode_ci DEFAULT 'current' COMMENT '保险导入设置（生成时的设置）：current-当月，next-次月，none-不导入',
  `salary_approval_id` bigint unsigned DEFAULT NULL COMMENT '工资表审批ID',
  `project_id` bigint unsigned NOT NULL,
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '月份 YYYY-MM',
  `period_start` int DEFAULT NULL COMMENT '工资周期开始日期',
  `period_end` int DEFAULT NULL COMMENT '工资周期结束日期',
  `work_days` int DEFAULT '0' COMMENT '应出勤天数',
  `actual_work_days` decimal(5,1) DEFAULT '0.0' COMMENT '实际出勤天数',
  `absent_days` decimal(5,1) DEFAULT '0.0' COMMENT '缺勤天数',
  `absent_deduction` decimal(10,2) DEFAULT '0.00' COMMENT '缺勤扣款',
  `basic_salary` decimal(10,2) DEFAULT '0.00' COMMENT '基本工资',
  `allowance` decimal(10,2) DEFAULT '0.00' COMMENT '津贴',
  `overtime_pay` decimal(10,2) DEFAULT '0.00' COMMENT '加班费',
  `bonus` decimal(10,2) DEFAULT '0.00' COMMENT '奖金',
  `gross_salary` decimal(10,2) DEFAULT '0.00' COMMENT '应发工资',
  `cumulative_income` decimal(10,2) DEFAULT '0.00' COMMENT '累计收入（今年1月至当前月应发工资总和）',
  `cumulative_basic_deduction` decimal(10,2) DEFAULT '0.00' COMMENT '累计减除费用（5000×月数）',
  `cumulative_special_deduction_insurance` decimal(10,2) DEFAULT '0.00' COMMENT '累计专项扣除（社保公积金个人部分）',
  `cumulative_taxable_income` decimal(10,2) DEFAULT '0.00' COMMENT '累计应纳税所得额',
  `tax_rate` decimal(5,2) DEFAULT '0.00' COMMENT '税率(%)',
  `quick_deduction` decimal(10,2) DEFAULT '0.00' COMMENT '速算扣除数',
  `cumulative_tax_payable` decimal(10,2) DEFAULT '0.00' COMMENT '累计应扣缴税额（累计应纳税所得额×税率-速算扣除数）',
  `tax_already_withheld` decimal(10,2) DEFAULT '0.00' COMMENT '已扣缴税额（1月到上月的累计应扣缴税额之和）',
  `social_security` decimal(10,2) DEFAULT '0.00' COMMENT '社保个人部分',
  `housing_fund` decimal(10,2) DEFAULT '0.00' COMMENT '公积金个人部分',
  `special_deduction` decimal(10,2) DEFAULT '0.00' COMMENT '专项扣除',
  `special_deduction_monthly` decimal(10,2) DEFAULT '0.00' COMMENT '当月专项附加扣除（6项扣除合计）',
  `taxable_income` decimal(10,2) DEFAULT '0.00' COMMENT '应纳税所得额',
  `cumulative_other_taxable` decimal(10,2) DEFAULT '0.00' COMMENT '累计其他应纳税项（合并扣税）',
  `tax_payable_or_refundable` decimal(10,2) DEFAULT '0.00' COMMENT '应补（退）税额',
  `employee_signature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '本人签字',
  `personal_tax` decimal(10,2) DEFAULT '0.00' COMMENT '个人所得税',
  `actual_tax` decimal(10,2) DEFAULT '0.00' COMMENT '实际个税',
  `deductions` decimal(10,2) DEFAULT '0.00' COMMENT '扣款',
  `net_salary` decimal(10,2) DEFAULT '0.00' COMMENT '实发工资',
  `paid_salary` decimal(10,2) DEFAULT '0.00' COMMENT '实际发放金额',
  `status` enum('draft','submitted','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `submitted_by` bigint unsigned DEFAULT NULL COMMENT '提交人',
  `approved_by` bigint unsigned DEFAULT NULL COMMENT '审批人',
  `submitted_at` timestamp NULL DEFAULT NULL COMMENT '提交时间',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '审批时间',
  `paid_at` timestamp NULL DEFAULT NULL COMMENT '发放时间',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci COMMENT '拒绝原因',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `company_insurance_total` decimal(10,2) DEFAULT '0.00' COMMENT '单位保险合计',
  `personal_insurance_total` decimal(10,2) DEFAULT '0.00' COMMENT '个人保险合计',
  `compensation_total` decimal(10,2) DEFAULT '0.00' COMMENT '补差合计',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `employee_id` (`employee_id`) USING BTREE,
  KEY `project_id` (`project_id`) USING BTREE,
  KEY `idx_salaries_month` (`month`) USING BTREE,
  KEY `idx_employee_id` (`employee_id`) USING BTREE,
  KEY `idx_project_id` (`project_id`) USING BTREE,
  KEY `idx_month` (`month`) USING BTREE,
  KEY `idx_status` (`status`) USING BTREE,
  KEY `idx_salaries_account_set_id` (`account_set_id`),
  KEY `idx_salary_approval_id` (`salary_approval_id`),
  CONSTRAINT `salaries_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `salaries_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT,
  CONSTRAINT `salaries_ibfk_2` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_approval_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_approval_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `salary_approval_id` bigint unsigned NOT NULL COMMENT '工资表审批ID',
  `filename` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_size` bigint unsigned NOT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '文件类型',
  `uploaded_by` bigint unsigned NOT NULL COMMENT '上传人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_salary_approval_id` (`salary_approval_id`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资表审批附件';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_approvals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `approval_instance_id` bigint unsigned DEFAULT NULL COMMENT '审批流程实例ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '工资期间（YYYY-MM）',
  `approval_type` enum('online','offline') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online' COMMENT '审批方式：线上/线下',
  `status` enum('pending','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '审批状态',
  `submitted_by` bigint unsigned DEFAULT NULL COMMENT '提交人ID',
  `submitted_at` datetime DEFAULT NULL COMMENT '提交时间',
  `approved_by` bigint unsigned DEFAULT NULL COMMENT '审批人ID',
  `approved_at` datetime DEFAULT NULL COMMENT '审批时间',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci COMMENT '拒绝原因',
  `remarks` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_month` (`project_id`,`month`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_month` (`month`),
  KEY `idx_status` (`status`),
  KEY `idx_approval_instance_id` (`approval_instance_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资表审批记录';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_sheets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_sheets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `month` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '月份 (YYYY-MM)',
  `attendance_sheet_id` bigint unsigned NOT NULL COMMENT '考勤表ID',
  `total_employees` int NOT NULL DEFAULT '0' COMMENT '员工总数',
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `status` enum('draft','submitted','approved','rejected') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT '状态',
  `notes` text COLLATE utf8mb4_unicode_ci COMMENT '备注',
  `created_by` bigint unsigned DEFAULT NULL COMMENT '创建人ID',
  `submitted_at` timestamp NULL DEFAULT NULL COMMENT '提交时间',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '审批时间',
  `rejected_at` timestamp NULL DEFAULT NULL COMMENT '拒绝时间',
  `rejection_reason` text COLLATE utf8mb4_unicode_ci COMMENT '拒绝原因',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_month` (`month`),
  KEY `idx_attendance_sheet_id` (`attendance_sheet_id`),
  KEY `idx_status` (`status`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_project_month` (`project_id`,`month`),
  KEY `idx_status_created` (`status`,`created_at`),
  CONSTRAINT `fk_salary_sheets_account_set` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_salary_sheets_attendance` FOREIGN KEY (`attendance_sheet_id`) REFERENCES `attendance_sheets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_salary_sheets_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_salary_sheets_project` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `salary_summaries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `salary_summaries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `project_id` bigint unsigned NOT NULL COMMENT '项目ID',
  `project_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称',
  `month` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '工资期间（YYYY-MM）',
  `period_start` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '工资周期开始',
  `period_end` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '工资周期结束',
  `employee_count` int NOT NULL DEFAULT '0' COMMENT '员工人数',
  `insurance_import_setting` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '保险导入设置（current/next/none）',
  `social_security_location` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '参保地',
  `salary_payment_day` int DEFAULT NULL COMMENT '工资发放日（几号）',
  `requires_salary_basis` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否需要上传工资依据',
  `salary_basis_uploaded` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否已上传工资依据',
  `total_work_days` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '应出勤天数合计',
  `total_actual_work_days` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实际出勤天数合计',
  `total_absent_days` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '缺勤天数合计',
  `total_absent_deduction` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '缺勤扣款合计',
  `total_basic_salary` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '基本工资合计',
  `total_gross_salary` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '应发工资合计',
  `total_cumulative_income` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '累计收入合计',
  `total_cumulative_basic_deduction` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '累计减除费用合计',
  `total_cumulative_special_deduction_insurance` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '累计专项扣除合计',
  `avg_tax_rate` decimal(10,2) DEFAULT NULL COMMENT '平均税率',
  `avg_quick_deduction` decimal(12,2) DEFAULT NULL COMMENT '平均速算扣除数',
  `total_cumulative_tax_payable` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '累计应扣缴税额合计',
  `total_tax_already_withheld` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '已扣缴税额合计',
  `total_pension_company` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '养老保险单位合计',
  `total_medical_company` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '医疗保险单位合计',
  `total_unemployment_company` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '失业保险单位合计',
  `total_work_injury_company` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '工伤保险单位合计',
  `total_maternity_company` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '生育保险单位合计',
  `total_pension_personal` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '养老保险个人合计',
  `total_medical_personal` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '医疗保险个人合计',
  `total_unemployment_personal` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '失业保险个人合计',
  `total_housing_fund_company` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '公积金单位合计',
  `total_housing_fund_personal` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '公积金个人合计',
  `total_large_medical_company` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '大额医疗单位合计',
  `total_large_medical_personal` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '大额医疗个人合计（不计入个人合计）',
  `total_social_security_compensation` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '社保补差合计',
  `total_housing_fund_compensation` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '公积金补差合计',
  `total_company_insurance_total` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '单位保险合计',
  `total_personal_insurance_total` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '个人保险合计',
  `total_special_deduction_monthly` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '当月专项附加扣除合计',
  `total_special_deduction` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '累计专项附加扣除合计',
  `total_taxable_income` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '应纳税所得额合计',
  `total_cumulative_other_taxable` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '累计其他应纳税项合计',
  `total_tax_payable_or_refundable` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '应补退税额合计',
  `total_deductions` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '其他扣款合计',
  `total_net_salary` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '实发工资合计',
  `total_paid_salary` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '已发放工资合计',
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved' COMMENT '状态',
  `salary_approval_id` bigint unsigned DEFAULT NULL COMMENT '工资表审批ID',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '审批时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_month` (`project_id`,`month`),
  KEY `salary_summaries_account_set_id_foreign` (`account_set_id`),
  KEY `salary_summaries_project_id_foreign` (`project_id`),
  KEY `salary_summaries_salary_approval_id_foreign` (`salary_approval_id`),
  CONSTRAINT `salary_summaries_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_summaries_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_summaries_salary_approval_id_foreign` FOREIGN KEY (`salary_approval_id`) REFERENCES `salary_approvals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资汇总表（完整版）';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `shared_files`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `shared_files` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '所属账套ID',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_category` enum('shared','notice') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shared' COMMENT '文件分类：shared=共享文件, notice=须知文件',
  `notice_type` enum('signing_notice','employee_handbook','regulations','confidentiality') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '须知类型：signing_notice=签订须知, employee_handbook=员工手册, regulations=规章制度, confidentiality=保密协议',
  `size` bigint NOT NULL,
  `uploader_id` bigint unsigned NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  KEY `uploader_id` (`uploader_id`) USING BTREE,
  KEY `idx_shared_files_account_set_id` (`account_set_id`),
  KEY `idx_file_category` (`file_category`),
  KEY `idx_notice_type` (`notice_type`),
  CONSTRAINT `shared_files_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL,
  CONSTRAINT `shared_files_ibfk_1` FOREIGN KEY (`uploader_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `social_security_regions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_security_regions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '社保编号',
  `min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '最低基数',
  `max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '最高基数',
  `old_min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '旧最低基数',
  `old_max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '旧最高基数',
  `old_limits_updated_at` timestamp NULL DEFAULT NULL COMMENT '旧上下限修改时间',
  `new_min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '新最低基数',
  `new_max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '新最高基数',
  `new_limits_updated_at` timestamp NULL DEFAULT NULL COMMENT '新上下限修改时间',
  `account_set_id` bigint unsigned NOT NULL COMMENT '账套ID',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `adjustment_base` decimal(10,2) DEFAULT NULL COMMENT '调整基数（预设值）',
  `effective_date` date DEFAULT NULL COMMENT '生效时间',
  PRIMARY KEY (`id`),
  KEY `social_security_regions_account_set_id_foreign` (`account_set_id`),
  KEY `social_security_regions_created_by_foreign` (`created_by`),
  CONSTRAINT `social_security_regions_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `social_security_regions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='社保地区表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `social_security_types`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `social_security_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `region_id` bigint unsigned NOT NULL COMMENT '地区ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险名称',
  `base_amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '基数',
  `min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '下限基数',
  `max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '上限基数',
  `employee_ratio` decimal(5,4) NOT NULL DEFAULT '0.0000' COMMENT '员工缴纳比例',
  `company_ratio` decimal(5,4) NOT NULL DEFAULT '0.0000' COMMENT '公司缴纳比例',
  `created_by` bigint unsigned NOT NULL COMMENT '创建人ID',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `social_security_types_region_id_foreign` (`region_id`),
  KEY `social_security_types_created_by_foreign` (`created_by`),
  CONSTRAINT `social_security_types_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `social_security_types_region_id_foreign` FOREIGN KEY (`region_id`) REFERENCES `social_security_regions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='社保细分种类表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `special_deduction_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `special_deduction_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '账套ID',
  `project_id` bigint unsigned DEFAULT NULL COMMENT '项目ID',
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '专项扣除名称',
  `amount` decimal(10,2) NOT NULL COMMENT '扣除金额',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT '说明描述',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `sort_order` int NOT NULL DEFAULT '0' COMMENT '排序',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='专项扣除项目表';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '账套ID',
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `idx_account_set_key` (`account_set_id`,`key`),
  KEY `idx_account_set_id` (`account_set_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_application_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_application_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `travel_application_id` bigint unsigned NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `file_size` bigint DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `travel_application_attachments_travel_application_id_foreign` (`travel_application_id`),
  CONSTRAINT `travel_application_attachments_travel_application_id_foreign` FOREIGN KEY (`travel_application_id`) REFERENCES `travel_applications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `travel_applications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `travel_applications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint unsigned NOT NULL,
  `department` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apply_date` date DEFAULT NULL,
  `applicant` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `days` int NOT NULL DEFAULT '0',
  `advance_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `payment_date` date DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_by` bigint unsigned NOT NULL,
  `approval_flow_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `travel_applications_account_set_id_foreign` (`account_set_id`),
  KEY `travel_applications_created_by_foreign` (`created_by`),
  KEY `travel_applications_approval_flow_id_foreign` (`approval_flow_id`),
  CONSTRAINT `travel_applications_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `travel_applications_approval_flow_id_foreign` FOREIGN KEY (`approval_flow_id`) REFERENCES `approval_instances` (`id`) ON DELETE SET NULL,
  CONSTRAINT `travel_applications_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_seals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_seals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '印章名称',
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '印章图片路径',
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原始文件名',
  `is_default` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否默认印章',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_seals_user_id_index` (`user_id`),
  CONSTRAINT `user_seals_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_signatures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_signatures` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `image_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '签名图片路径',
  `original_filename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '原始文件名',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_signatures_user_id_unique` (`user_id`),
  CONSTRAINT `user_signatures_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '昵称/显示名称',
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '手机号',
  `avatar` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '头像URL',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','hr','manager','employee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT 'employee',
  `account_set_id` bigint unsigned DEFAULT NULL COMMENT '账套ID',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `email` (`email`) USING BTREE,
  KEY `idx_account_set_id` (`account_set_id`),
  CONSTRAINT `fk_users_account_set` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `v_large_medical_payment_status`;
/*!50001 DROP VIEW IF EXISTS `v_large_medical_payment_status`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
/*!50001 CREATE VIEW `v_large_medical_payment_status` AS SELECT 
 1 AS `employee_id`,
 1 AS `employee_name`,
 1 AS `large_medical_insurance_enabled`,
 1 AS `large_medical_payment_start_month`,
 1 AS `large_medical_payment_start_year`,
 1 AS `large_medical_last_payment_month`,
 1 AS `large_medical_last_payment_year`,
 1 AS `payment_cycle`,
 1 AS `calculation_type`,
 1 AS `payment_cycle_text`,
 1 AS `calculation_type_text`*/;
SET character_set_client = @saved_cs_client;
/*!50003 DROP FUNCTION IF EXISTS `CalculateLargeMedicalAmount` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` FUNCTION `CalculateLargeMedicalAmount`(
    p_employee_id INT,
    p_year INT,
    p_month INT
) RETURNS decimal(10,2)
    READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE v_amount DECIMAL(10,2) DEFAULT 0.00;
    DECLARE v_is_payment_month BOOLEAN DEFAULT FALSE;
    DECLARE v_base_amount DECIMAL(10,2);
    DECLARE v_company_ratio DECIMAL(5,4);
    DECLARE v_employee_ratio DECIMAL(5,4);
    DECLARE v_company_amount DECIMAL(10,2);
    DECLARE v_employee_amount DECIMAL(10,2);
    DECLARE v_calculation_type VARCHAR(20);
    DECLARE v_payment_cycle VARCHAR(20);
    
    -- 调用存储过程判断是否为支付月份
    CALL IsLargeMedicalPaymentMonth(p_employee_id, p_year, p_month, v_is_payment_month);
    
    -- 如果不是支付月份，返回0
    IF v_is_payment_month = FALSE THEN
        RETURN 0.00;
    END IF;
    
    -- 获取大额医疗保险配置
    SELECT 
        ip.employee_large_medical_base,
        JSON_UNQUOTE(JSON_EXTRACT(ip.large_medical_insurance_config, '$.company_ratio')) as company_ratio,
        JSON_UNQUOTE(JSON_EXTRACT(ip.large_medical_insurance_config, '$.employee_ratio')) as employee_ratio,
        JSON_UNQUOTE(JSON_EXTRACT(ip.large_medical_insurance_config, '$.company_amount')) as company_amount,
        JSON_UNQUOTE(JSON_EXTRACT(ip.large_medical_insurance_config, '$.employee_amount')) as employee_amount,
        JSON_UNQUOTE(JSON_EXTRACT(ip.large_medical_insurance_config, '$.calculation_type')) as calculation_type
    INTO 
        v_base_amount,
        v_company_ratio,
        v_employee_ratio,
        v_company_amount,
        v_employee_amount,
        v_calculation_type
    FROM insurance_personnel ip
    WHERE ip.employee_id = p_employee_id
    LIMIT 1;
    
    -- 根据计算方式计算金额
    IF v_calculation_type = 'fixed' THEN
        -- 固定金额模式
        SET v_amount = v_company_amount + v_employee_amount;
    ELSE
        -- 按基数模式
        SET v_amount = v_base_amount * (v_company_ratio + v_employee_ratio);
    END IF;
    
    RETURN IFNULL(v_amount, 0.00);
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50003 DROP PROCEDURE IF EXISTS `IsLargeMedicalPaymentMonth` */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `IsLargeMedicalPaymentMonth`(
    IN p_employee_id INT,
    IN p_year INT,
    IN p_month INT,
    OUT p_is_payment_month BOOLEAN
)
BEGIN
    DECLARE v_payment_cycle VARCHAR(20);
    DECLARE v_payment_start_month INT;
    DECLARE v_payment_start_year INT;
    DECLARE v_large_medical_enabled BOOLEAN;
    
    -- 获取员工的大额医疗保险配置
    SELECT 
        ip.large_medical_insurance_enabled,
        ip.large_medical_payment_start_month,
        ip.large_medical_payment_start_year,
        JSON_UNQUOTE(JSON_EXTRACT(ip.large_medical_insurance_config, '$.payment_cycle')) as payment_cycle
    INTO 
        v_large_medical_enabled,
        v_payment_start_month,
        v_payment_start_year,
        v_payment_cycle
    FROM insurance_personnel ip
    WHERE ip.employee_id = p_employee_id
    LIMIT 1;
    
    -- 初始化返回值
    SET p_is_payment_month = FALSE;
    
    -- 如果大额医疗保险未启用，直接返回FALSE
    IF v_large_medical_enabled = 0 OR v_large_medical_enabled IS NULL THEN
        SET p_is_payment_month = FALSE;
    ELSE
        -- 根据付款周期判断
        IF v_payment_cycle = 'yearly' THEN
            -- 年付模式：只有支付月份才生成金额
            IF v_payment_start_month = p_month THEN
                -- 计算年份差值，必须是12的倍数
                IF (p_year - v_payment_start_year) % 1 = 0 THEN
                    SET p_is_payment_month = TRUE;
                END IF;
            END IF;
        ELSE
            -- 月付模式：每月都生成金额
            SET p_is_payment_month = TRUE;
        END IF;
    END IF;
    
END ;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;
/*!50001 DROP VIEW IF EXISTS `v_large_medical_payment_status`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_0900_ai_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`root`@`localhost` SQL SECURITY DEFINER */
/*!50001 VIEW `v_large_medical_payment_status` AS select `ip`.`employee_id` AS `employee_id`,`ip`.`employee_name` AS `employee_name`,`ip`.`large_medical_insurance_enabled` AS `large_medical_insurance_enabled`,`ip`.`large_medical_payment_start_month` AS `large_medical_payment_start_month`,`ip`.`large_medical_payment_start_year` AS `large_medical_payment_start_year`,`ip`.`large_medical_last_payment_month` AS `large_medical_last_payment_month`,`ip`.`large_medical_last_payment_year` AS `large_medical_last_payment_year`,json_unquote(json_extract(`ip`.`large_medical_insurance_config`,'$.payment_cycle')) AS `payment_cycle`,json_unquote(json_extract(`ip`.`large_medical_insurance_config`,'$.calculation_type')) AS `calculation_type`,(case when (json_unquote(json_extract(`ip`.`large_medical_insurance_config`,'$.payment_cycle')) = 'yearly') then '年付' else '月付' end) AS `payment_cycle_text`,(case when (json_unquote(json_extract(`ip`.`large_medical_insurance_config`,'$.calculation_type')) = 'fixed') then '固定金额' else '按基数' end) AS `calculation_type_text` from `insurance_personnel` `ip` where (`ip`.`large_medical_insurance_enabled` = 1) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'2019_12_14_000001_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2024_01_01_000012_create_recruitment_table',3);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2025_10_02_182823_add_fields_to_recruitment_table',4);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2024_01_01_000011_create_invoices_table',5);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2025_10_05_create_user_signatures_and_seals_tables',6);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2025_10_07_130934_add_labor_contract_notice_to_projects_table',7);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2025_10_08_161603_add_placeholder_positions_to_contract_templates_table',8);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2025_01_15_add_base_adjustment_fields',9);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2025_10_09_171637_add_medical_insurance_regions_to_projects_table',10);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2025_10_09_210958_add_insurance_region_fields_to_employees_table',11);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2025_10_22_190103_add_base_limits_to_region_tables',12);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2025_11_03_000001_add_task_name_and_project_name_to_invoice_applications',13);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2025_11_03_000003_add_resubmitted_flag_to_invoice_applications',14);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2025_11_03_000004_add_approval_status_to_invoice_applications',15);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2025_11_03_000005_create_payroll_remarks_table',16);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2025_11_03_000006_add_invoice_details_to_invoice_applications',17);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2025_11_04_161122_add_period_fields_to_salaries_table',18);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2025_11_06_193408_create_travel_applications_table',19);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2025_11_06_193445_create_travel_application_attachments_table',20);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2025_11_06_201954_add_id_card_to_salaries_table',21);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2025_11_08_add_requires_basis_fields_to_projects_table',22);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2025_11_08_create_basis_records_table',23);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2025_11_08_create_basis_attachments_table',24);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2025_11_08_modify_salary_payment_date_to_day',25);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2025_11_11_000001_add_termination_and_retirement_fields_to_employees_table',26);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2024_11_12_172700_create_contract_reminders_table',27);
