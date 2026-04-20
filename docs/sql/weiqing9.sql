/*
 Navicat Premium Data Transfer

 Source Server         : a
 Source Server Type    : MySQL
 Source Server Version : 80019
 Source Host           : localhost:3306
 Source Schema         : weiqing

 Target Server Type    : MySQL
 Target Server Version : 80019
 File Encoding         : 65001

 Date: 14/11/2025 19:47:16
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for account_set_users
-- ----------------------------
DROP TABLE IF EXISTS `account_set_users`;
CREATE TABLE `account_set_users`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL,
  `user_id` bigint(0) UNSIGNED NOT NULL,
  `role` enum('owner','admin','viewer') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'viewer' COMMENT '在此套账中的角色',
  `approval_level` tinyint(0) NULL DEFAULT NULL COMMENT '审批级别：1=第1级,2=第2级,3=第3级,4=第4级,NULL=不参与审批',
  `approval_level_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '审批级别名称：经办、复核、审核、终审',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为该用户的默认账套',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '用户套账关联表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for account_sets
-- ----------------------------
DROP TABLE IF EXISTS `account_sets`;
CREATE TABLE `account_sets`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '套账名称',
  `code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '套账代码',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '描述',
  `company_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '公司名称',
  `tax_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '税号',
  `contact_person` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系人',
  `contact_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系电话',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '地址',
  `base_adjustment_months` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `status` enum('active','inactive','archived') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '状态',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认套账',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '套账管理表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for approval_attachments
-- ----------------------------
DROP TABLE IF EXISTS `approval_attachments`;
CREATE TABLE `approval_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `instance_id` bigint(0) UNSIGNED NOT NULL COMMENT '审批实例ID',
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '文件路径',
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '文件原始名称',
  `file_size` bigint(0) NULL DEFAULT NULL COMMENT '文件大小（字节）',
  `file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL COMMENT '文件类型（MIME）',
  `uploaded_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '上传人ID（NULL=系统自动）',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 208 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci COMMENT = '审批附件表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for approval_cc_users
-- ----------------------------
DROP TABLE IF EXISTS `approval_cc_users`;
CREATE TABLE `approval_cc_users`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `instance_id` bigint(0) UNSIGNED NOT NULL COMMENT '审批实例ID',
  `user_id` bigint(0) UNSIGNED NOT NULL COMMENT '抄送人ID',
  `user_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '抄送人姓名',
  `added_by` bigint(0) UNSIGNED NOT NULL COMMENT '添加人ID',
  `added_at_step` tinyint(0) NOT NULL COMMENT '在哪个步骤添加的',
  `has_read` tinyint(1) NULL DEFAULT 0 COMMENT '是否已读',
  `read_at` timestamp(0) NULL DEFAULT NULL COMMENT '阅读时间',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_instance`(`instance_id`) USING BTREE,
  INDEX `idx_user`(`user_id`, `has_read`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci COMMENT = '审批抄送表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for approval_instances
-- ----------------------------
DROP TABLE IF EXISTS `approval_instances`;
CREATE TABLE `approval_instances`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `business_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '业务类型：employee_contract, salary, insurance等',
  `business_id` bigint(0) UNSIGNED NOT NULL COMMENT '业务数据ID',
  `current_step` tinyint(0) NOT NULL DEFAULT 1 COMMENT '当前审批步骤',
  `total_steps` tinyint(0) NOT NULL DEFAULT 4 COMMENT '总审批步骤数',
  `status` enum('pending','approved','rejected','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pending' COMMENT '整体状态',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '发起人ID',
  `attachment_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL COMMENT '附件路径（合同文件等）',
  `attachment_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL COMMENT '附件原始文件名',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `completed_at` timestamp(0) NULL DEFAULT NULL COMMENT '完成时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_business`(`account_set_id`, `business_type`, `business_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 192 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci COMMENT = '审批流程实例表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for approval_records
-- ----------------------------
DROP TABLE IF EXISTS `approval_records`;
CREATE TABLE `approval_records`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `instance_id` bigint(0) UNSIGNED NOT NULL COMMENT '审批实例ID',
  `step_order` tinyint(0) NOT NULL COMMENT '审批步骤：1,2,3,4',
  `step_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '步骤名称：经办、复核、审核、终审',
  `approver_id` bigint(0) UNSIGNED NOT NULL COMMENT '审批人ID',
  `approver_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '审批人姓名',
  `status` enum('waiting','pending','approved','rejected','returned') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'waiting' COMMENT '状态：waiting=等待中, pending=审批中, approved=已通过, rejected=已驳回, returned=已退回',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL COMMENT '审批意见',
  `signature_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL COMMENT '签名图片路径',
  `seal_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL COMMENT '印章图片路径',
  `returned_to_step` tinyint(0) NULL DEFAULT NULL COMMENT '退回到的步骤',
  `returned_at` timestamp(0) NULL DEFAULT NULL COMMENT '退回时间',
  `approved_at` timestamp(0) NULL DEFAULT NULL COMMENT '审批时间',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_instance`(`instance_id`) USING BTREE,
  INDEX `idx_approver`(`approver_id`, `status`) USING BTREE,
  INDEX `idx_step`(`instance_id`, `step_order`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 572 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci COMMENT = '审批记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for approvals
-- ----------------------------
DROP TABLE IF EXISTS `approvals`;
CREATE TABLE `approvals`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `user_id` bigint(0) UNSIGNED NOT NULL,
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `type` enum('leave','overtime','business_trip','expense','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_date` date NULL DEFAULT NULL,
  `end_date` date NULL DEFAULT NULL,
  `amount` decimal(10, 2) NULL DEFAULT 0.00,
  `status` enum('pending','approved','rejected','returned') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'pending',
  `comment` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '审批意见',
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `approver_id` bigint(0) NULL DEFAULT NULL,
  `approved_at` timestamp(0) NULL DEFAULT NULL,
  `rejected_at` timestamp(0) NULL DEFAULT NULL,
  `submitted_at` timestamp(0) NULL DEFAULT NULL,
  `actual_completion_date` date NULL DEFAULT NULL,
  `related_id` bigint(0) NULL DEFAULT NULL COMMENT '业务关联id',
  `applicant_id` bigint(0) NULL DEFAULT NULL,
  `reason` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `expected_completion_date` datetime(0) NULL DEFAULT NULL,
  `approval_comment` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `seal` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `stamp_method` enum('online','offline') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'online' COMMENT '盖章方式：online=线上盖章，offline=线下盖章',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id`) USING BTREE,
  INDEX `project_id`(`project_id`) USING BTREE,
  INDEX `idx_approvals_status`(`status`) USING BTREE,
  INDEX `idx_approvals_account_set_id`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for assessment_records
-- ----------------------------
DROP TABLE IF EXISTS `assessment_records`;
CREATE TABLE `assessment_records`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `business_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '业务类型：insurance_enrollment-参保入职, contract_signing-合同签署, salary_payment-工资发放等',
  `business_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '业务记录ID（可选）',
  `business_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '业务描述（如：张三-社保参保）',
  `handler_id` bigint(0) UNSIGNED NOT NULL COMMENT '责任人ID（经办人）',
  `handler_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '责任人姓名',
  `deadline_date` date NOT NULL COMMENT '应完成时间',
  `actual_complete_date` datetime(0) NULL DEFAULT NULL COMMENT '实际完成时间',
  `overdue_days` int(0) NOT NULL DEFAULT 0 COMMENT '超期天数',
  `status` enum('pending','overdue','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：pending-待处理, overdue-已超期, completed-已完成',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注说明',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_business_type`(`business_type`) USING BTREE,
  INDEX `idx_handler`(`handler_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_deadline`(`deadline_date`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '考核记录表（通用）' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for attendance
-- ----------------------------
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL,
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `date` date NOT NULL,
  `check_in_time` time(0) NULL DEFAULT NULL,
  `check_out_time` time(0) NULL DEFAULT NULL,
  `work_hours` decimal(4, 2) NULL DEFAULT 0.00 COMMENT '工作时长',
  `overtime_hours` decimal(4, 2) NULL DEFAULT 0.00 COMMENT '加班时长',
  `status` enum('normal','late','early','absent','leave') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'normal',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `project_id`(`project_id`) USING BTREE,
  INDEX `idx_attendance_date`(`date`) USING BTREE,
  INDEX `idx_attendance_employee_date`(`employee_id`, `date`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_date`(`date`) USING BTREE,
  INDEX `idx_employee_date`(`employee_id`, `date`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_attendance_account_set_id`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for attendance_records
-- ----------------------------
DROP TABLE IF EXISTS `attendance_records`;
CREATE TABLE `attendance_records`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `attendance_sheet_id` bigint(0) UNSIGNED NOT NULL COMMENT '考勤表ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `date` date NOT NULL COMMENT '考勤日期',
  `day_of_month` int(0) NOT NULL COMMENT '月份中的第几天',
  `status` enum('normal','late','early','absent','leave','off') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '考勤状态',
  `check_in_time` time(0) NULL DEFAULT NULL COMMENT '上班时间',
  `check_out_time` time(0) NULL DEFAULT NULL COMMENT '下班时间',
  `work_hours` decimal(4, 2) NULL DEFAULT 8.00 COMMENT '工作时长',
  `overtime_hours` decimal(4, 2) NULL DEFAULT 0.00 COMMENT '加班时长',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_sheet_employee_day`(`attendance_sheet_id`, `employee_id`, `day_of_month`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_attendance_sheet_id`(`attendance_sheet_id`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_date`(`date`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 199 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '考勤记录' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for attendance_rules
-- ----------------------------
DROP TABLE IF EXISTS `attendance_rules`;
CREATE TABLE `attendance_rules`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `rule_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '规则名称',
  `work_start_time` time(0) NOT NULL DEFAULT '09:00:00' COMMENT '上班时间',
  `work_end_time` time(0) NOT NULL DEFAULT '18:00:00' COMMENT '下班时间',
  `late_threshold` int(0) NOT NULL DEFAULT 15 COMMENT '迟到阈值(分钟)',
  `early_threshold` int(0) NOT NULL DEFAULT 15 COMMENT '早退阈值(分钟)',
  `work_days_per_week` int(0) NOT NULL DEFAULT 5 COMMENT '每周工作天数',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '考勤规则配置' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for attendance_sheets
-- ----------------------------
DROP TABLE IF EXISTS `attendance_sheets`;
CREATE TABLE `attendance_sheets`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '月份 YYYY-MM',
  `work_days` int(0) NOT NULL DEFAULT 22 COMMENT '工作日天数',
  `status` enum('draft','submitted','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'draft',
  `total_employees` int(0) NULL DEFAULT 0,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `attachments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '提交的附件信息(JSON格式)',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `submitted_at` timestamp(0) NULL DEFAULT NULL,
  `approved_at` timestamp(0) NULL DEFAULT NULL,
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `submitted_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '提交人ID',
  `approved_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批人ID',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `project_id`(`project_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_month`(`month`) USING BTREE,
  INDEX `idx_created_at`(`created_at`) USING BTREE,
  INDEX `idx_attendance_sheets_account_set_id`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 24 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for attendance_statistics
-- ----------------------------
DROP TABLE IF EXISTS `attendance_statistics`;
CREATE TABLE `attendance_statistics`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `attendance_sheet_id` bigint(0) UNSIGNED NOT NULL COMMENT '考勤表ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `work_days` int(0) NOT NULL DEFAULT 0 COMMENT '应出勤天数',
  `actual_work_days` int(0) NOT NULL DEFAULT 0 COMMENT '实际出勤天数',
  `absent_days` int(0) NOT NULL DEFAULT 0 COMMENT '缺勤天数',
  `late_count` int(0) NOT NULL DEFAULT 0 COMMENT '迟到次数',
  `early_count` int(0) NOT NULL DEFAULT 0 COMMENT '早退次数',
  `leave_days` int(0) NOT NULL DEFAULT 0 COMMENT '请假天数',
  `off_days` int(0) NOT NULL DEFAULT 0 COMMENT '调休天数',
  `attendance_rate` decimal(5, 4) NOT NULL DEFAULT 0.0000 COMMENT '出勤率',
  `total_work_hours` decimal(6, 2) NOT NULL DEFAULT 0.00 COMMENT '总工作时长',
  `total_overtime_hours` decimal(6, 2) NOT NULL DEFAULT 0.00 COMMENT '总加班时长',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_sheet_employee`(`attendance_sheet_id`, `employee_id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_attendance_sheet_id`(`attendance_sheet_id`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '考勤统计' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for base_adjustments
-- ----------------------------
DROP TABLE IF EXISTS `base_adjustments`;
CREATE TABLE `base_adjustments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `project_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '项目ID（旧字段，已废弃）',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `old_social_security_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整前社保基数',
  `old_medical_insurance_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整前医保基数',
  `old_housing_fund_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整前公积金基数',
  `old_large_medical_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整前大额医疗基数',
  `new_social_security_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整后社保基数',
  `new_medical_insurance_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整后医保基数',
  `new_housing_fund_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整后公积金基数',
  `new_large_medical_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整后大额医疗基数（选填）',
  `effective_date` date NULL DEFAULT NULL COMMENT '统一生效时间（旧字段，已废弃）',
  `status` enum('pending','approved','applied','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：待审批、已审批、已生效、已取消',
  `applied_at` timestamp(0) NULL DEFAULT NULL COMMENT '生效时间（实际应用到档案的时间）',
  `adjustment_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '调整原因',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '调整原因',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `approved_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批人ID',
  `approved_at` timestamp(0) NULL DEFAULT NULL COMMENT '审批时间',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `social_security_effective_date` date NULL DEFAULT NULL COMMENT '社保基数生效时间',
  `medical_insurance_effective_date` date NULL DEFAULT NULL COMMENT '医保基数生效时间',
  `housing_fund_effective_date` date NULL DEFAULT NULL COMMENT '公积金基数生效时间',
  `large_medical_effective_date` date NULL DEFAULT NULL COMMENT '大额医疗基数生效时间',
  `social_security_min_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '社保下限（调基时）',
  `social_security_max_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '社保上限（调基时）',
  `medical_insurance_min_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '医保下限（调基时）',
  `medical_insurance_max_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '医保上限（调基时）',
  `housing_fund_min_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '公积金下限（调基时）',
  `housing_fund_max_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '公积金上限（调基时）',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_effective_date`(`effective_date`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE,
  INDEX `base_adjustments_approved_by_foreign`(`approved_by`) USING BTREE,
  INDEX `idx_employee_effective`(`employee_id`, `effective_date`, `status`) USING BTREE,
  INDEX `idx_account_status`(`account_set_id`, `status`, `effective_date`) USING BTREE,
  INDEX `idx_social_effective_date`(`social_security_effective_date`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 42 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '基数调差记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for basis_attachments
-- ----------------------------
DROP TABLE IF EXISTS `basis_attachments`;
CREATE TABLE `basis_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `basis_record_id` bigint(0) UNSIGNED NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名',
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件类型：image/document/other',
  `file_size` bigint(0) NOT NULL COMMENT '文件大小（字节）',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `basis_attachments_basis_record_id_foreign`(`basis_record_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for basis_records
-- ----------------------------
DROP TABLE IF EXISTS `basis_records`;
CREATE TABLE `basis_records`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL,
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `type` enum('attendance','salary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '依据类型：attendance-考勤依据，salary-工资依据',
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '月份，格式：YYYY-MM',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '文字说明',
  `created_by` bigint(0) UNSIGNED NOT NULL,
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  `deleted_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_project_month_type`(`project_id`, `month`, `type`, `deleted_at`) USING BTREE,
  INDEX `basis_records_account_set_id_foreign`(`account_set_id`) USING BTREE,
  INDEX `basis_records_created_by_foreign`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for bid_documents
-- ----------------------------
DROP TABLE IF EXISTS `bid_documents`;
CREATE TABLE `bid_documents`  (
  `id` int(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '文件ID',
  `bid_project_id` int(0) UNSIGNED NOT NULL COMMENT '投标项目ID',
  `document_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件类型（招标文件、投标文件、技术方案、报价单、资质证明等）',
  `document_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名称',
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_size` bigint(0) NULL DEFAULT NULL COMMENT '文件大小（字节）',
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件格式（pdf、doc、xls等）',
  `upload_by` int(0) UNSIGNED NULL DEFAULT NULL COMMENT '上传人ID',
  `upload_at` datetime(0) NULL DEFAULT NULL COMMENT '上传时间',
  `version` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '1.0' COMMENT '版本号',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_bid_project`(`bid_project_id`) USING BTREE,
  INDEX `idx_document_type`(`document_type`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '投标文件表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for bid_progress_logs
-- ----------------------------
DROP TABLE IF EXISTS `bid_progress_logs`;
CREATE TABLE `bid_progress_logs`  (
  `id` int(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `bid_project_id` int(0) UNSIGNED NOT NULL COMMENT '投标项目ID',
  `log_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '记录类型（status_change、document_upload、payment、meeting、other）',
  `log_title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '记录标题',
  `log_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '记录内容',
  `old_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '变更前状态（状态变更时记录）',
  `new_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '变更后状态（状态变更时记录）',
  `log_time` datetime(0) NOT NULL COMMENT '记录时间',
  `operator_id` int(0) UNSIGNED NULL DEFAULT NULL COMMENT '操作人ID',
  `operator_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '操作人姓名',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_bid_project`(`bid_project_id`) USING BTREE,
  INDEX `idx_log_time`(`log_time`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '投标进度记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for bid_projects
-- ----------------------------
DROP TABLE IF EXISTS `bid_projects`;
CREATE TABLE `bid_projects`  (
  `id` int(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '项目ID',
  `account_set_id` int(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '项目编号（自动生成）',
  `project_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称',
  `project_category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '项目类别（如：劳务派遣、物业管理、保洁服务等）',
  `client_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '招标单位名称',
  `client_contact` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '招标单位联系人',
  `client_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '招标单位联系电话',
  `project_budget` decimal(15, 2) NULL DEFAULT NULL COMMENT '项目预算金额',
  `bid_bond` decimal(15, 2) NULL DEFAULT NULL COMMENT '投标保证金',
  `bond_paid_at` datetime(0) NULL DEFAULT NULL COMMENT '保证金缴纳时间',
  `bond_refunded_at` datetime(0) NULL DEFAULT NULL COMMENT '保证金退还时间',
  `project_location` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '项目地点',
  `project_scale` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '项目规模描述',
  `service_period` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '服务期限（如：1年、3年）',
  `bid_deadline` datetime(0) NULL DEFAULT NULL COMMENT '投标截止时间',
  `bid_opening_time` datetime(0) NULL DEFAULT NULL COMMENT '开标时间',
  `bid_method` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '公开招标' COMMENT '招标方式（公开招标、邀请招标、竞争性谈判等）',
  `information_source` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '信息来源',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'preparing' COMMENT '项目状态',
  `bid_result` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '投标结果（won=中标, lost=未中标, abandoned=放弃）',
  `win_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '中标金额',
  `win_date` date NULL DEFAULT NULL COMMENT '中标日期',
  `contract_signed_at` datetime(0) NULL DEFAULT NULL COMMENT '合同签订时间',
  `contract_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '合同编号',
  `contract_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '合同金额',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注说明',
  `responsible_person` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '负责人',
  `responsible_department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '负责部门',
  `created_by` int(0) UNSIGNED NULL DEFAULT NULL COMMENT '创建人ID',
  `updated_by` int(0) UNSIGNED NULL DEFAULT NULL COMMENT '最后更新人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `project_code`(`project_code`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_bid_deadline`(`bid_deadline`) USING BTREE,
  INDEX `idx_project_category`(`project_category`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '投标项目表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for bid_reminders
-- ----------------------------
DROP TABLE IF EXISTS `bid_reminders`;
CREATE TABLE `bid_reminders`  (
  `id` int(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '提醒ID',
  `bid_project_id` int(0) UNSIGNED NOT NULL COMMENT '投标项目ID',
  `reminder_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '提醒类型（deadline、bond、opening等）',
  `reminder_time` datetime(0) NOT NULL COMMENT '提醒时间',
  `reminder_title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '提醒标题',
  `reminder_content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '提醒内容',
  `is_sent` tinyint(1) NULL DEFAULT 0 COMMENT '是否已发送',
  `sent_at` datetime(0) NULL DEFAULT NULL COMMENT '发送时间',
  `recipient_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '接收人ID列表（JSON数组）',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_bid_project`(`bid_project_id`) USING BTREE,
  INDEX `idx_reminder_time`(`reminder_time`) USING BTREE,
  INDEX `idx_is_sent`(`is_sent`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '投标提醒设置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for contract_reminders
-- ----------------------------
DROP TABLE IF EXISTS `contract_reminders`;
CREATE TABLE `contract_reminders`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL,
  `employee_id` bigint(0) UNSIGNED NOT NULL,
  `reminder_type` enum('labor_contract','termination_agreement','retirement_agreement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `employee_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `contract_start_date` date NULL DEFAULT NULL,
  `contract_end_date` date NULL DEFAULT NULL,
  `termination_date` date NULL DEFAULT NULL,
  `retirement_date` date NULL DEFAULT NULL,
  `status` enum('pending','resolved','escalated') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `handler_id` bigint(0) UNSIGNED NOT NULL DEFAULT 0,
  `handler_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '业务人员',
  `reminder_date` date NOT NULL,
  `escalation_date` date NULL DEFAULT NULL,
  `is_escalated` tinyint(1) NOT NULL DEFAULT 0,
  `assessment_record_id` bigint(0) UNSIGNED NULL DEFAULT NULL,
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `contract_reminders_assessment_record_id_foreign`(`assessment_record_id`) USING BTREE,
  INDEX `contract_reminders_account_set_id_reminder_date_index`(`account_set_id`, `reminder_date`) USING BTREE,
  INDEX `contract_reminders_status_is_escalated_index`(`status`, `is_escalated`) USING BTREE,
  INDEX `contract_reminders_employee_id_reminder_type_index`(`employee_id`, `reminder_type`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for contract_templates
-- ----------------------------
DROP TABLE IF EXISTS `contract_templates`;
CREATE TABLE `contract_templates`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `contract_type` enum('labor','termination','retirement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '合同类型：labor-劳动合同，termination-解除协议合同，retirement-退休解除协议合同',
  `shared_file_id` bigint(0) UNSIGNED NOT NULL COMMENT '共享文件ID',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为默认模板',
  `placeholder_positions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '占位符位置坐标',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_project_type`(`project_id`, `contract_type`) USING BTREE,
  INDEX `idx_shared_file`(`shared_file_id`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '合同模板表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for document_deliveries
-- ----------------------------
DROP TABLE IF EXISTS `document_deliveries`;
CREATE TABLE `document_deliveries`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '配置ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `delivery_cycle` enum('monthly','quarterly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交付周期',
  `delivery_method` enum('express','electronic') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交付方式',
  `delivery_period` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '交付期间（月份：YYYY-MM，季度：YYYY-Q1/Q2/Q3/Q4）',
  `status` enum('pending','submitted','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：pending-待交付，submitted-已提交，completed-已完成',
  `handler_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '经办人ID（负责交付的人）',
  `required_documents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '应交付资料清单（JSON格式）',
  `submitted_documents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '已提交资料说明',
  `express_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '快递单号',
  `express_date` date NULL DEFAULT NULL COMMENT '快递寄出日期',
  `submitted_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '提交人ID（经办）',
  `submitted_at` datetime(0) NULL DEFAULT NULL COMMENT '提交时间',
  `completed_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '完成人ID',
  `completed_at` datetime(0) NULL DEFAULT NULL COMMENT '完成时间',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_project_period`(`project_id`, `delivery_period`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_delivery_period`(`delivery_period`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_submitted_at`(`submitted_at`) USING BTREE,
  INDEX `idx_config_id`(`config_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '资料交付记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for document_delivery_attachments
-- ----------------------------
DROP TABLE IF EXISTS `document_delivery_attachments`;
CREATE TABLE `document_delivery_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_id` bigint(0) UNSIGNED NOT NULL COMMENT '交付记录ID',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名',
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_size` bigint(0) NULL DEFAULT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件类型',
  `uploaded_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '上传人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_delivery_id`(`delivery_id`) USING BTREE,
  INDEX `idx_uploaded_by`(`uploaded_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '资料交付附件表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for document_delivery_reminders
-- ----------------------------
DROP TABLE IF EXISTS `document_delivery_reminders`;
CREATE TABLE `document_delivery_reminders`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `delivery_id` bigint(0) UNSIGNED NOT NULL COMMENT '交付记录ID',
  `reminder_type` enum('new_period','not_submitted') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '提醒类型：new_period-新周期提醒，not_submitted-未交付提醒',
  `recipient_id` bigint(0) UNSIGNED NOT NULL COMMENT '接收人ID（经办）',
  `is_read` tinyint(1) NULL DEFAULT 0 COMMENT '是否已读：0-未读，1-已读',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_delivery_id`(`delivery_id`) USING BTREE,
  INDEX `idx_recipient_id`(`recipient_id`) USING BTREE,
  INDEX `idx_is_read`(`is_read`) USING BTREE,
  INDEX `idx_created_at`(`created_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '资料交付提醒记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for employee_base_adjustments
-- ----------------------------
DROP TABLE IF EXISTS `employee_base_adjustments`;
CREATE TABLE `employee_base_adjustments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `current_social_security_base` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '当前社保基数',
  `current_medical_insurance_base` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '当前医保基数',
  `current_housing_fund_base` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '当前公积金基数',
  `current_large_medical_base` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '当前大额医疗基数',
  `adjusted_social_security_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整后社保基数',
  `adjusted_medical_insurance_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整后医保基数',
  `adjusted_housing_fund_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整后公积金基数',
  `adjusted_large_medical_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整后大额医疗基数（选填）',
  `effective_date` date NULL DEFAULT NULL COMMENT '生效时间（年-月-日）',
  `status` enum('pending','applied') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：待生效、已生效',
  `applied_at` timestamp(0) NULL DEFAULT NULL COMMENT '生效时间（实际应用到档案的时间）',
  `adjustment_reason` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '调整原因',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_employee_adjustment`(`employee_id`, `account_set_id`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_effective_date`(`effective_date`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `employee_base_adjustments_created_by_foreign`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '员工调差数据表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for employee_contracts
-- ----------------------------
DROP TABLE IF EXISTS `employee_contracts`;
CREATE TABLE `employee_contracts`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '账套ID',
  `contract_type` enum('labor','termination','retirement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '合同类型：labor-劳动合同，termination-解除协议，retirement-退休解除协议',
  `contract_file` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '合同文件路径',
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '原始文件名',
  `status` enum('draft','pending_sign','employee_signed','in_approval','completed','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT '合同状态：draft=草稿, pending_sign=签署中, employee_signed=乙方已签署, in_approval=审批中, completed=已完成, rejected=已驳回',
  `approval_instance_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批实例ID',
  `created_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '创建人ID',
  `uploaded_at` timestamp(0) NULL DEFAULT NULL COMMENT '上传时间',
  `employee_signed_at` timestamp(0) NULL DEFAULT NULL COMMENT '员工签署时间',
  `completed_at` timestamp(0) NULL DEFAULT NULL COMMENT '完成时间',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `signature_image` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '员工签名图片',
  `sign_x_percent` decimal(8, 4) NULL DEFAULT NULL COMMENT '签名X坐标百分比(0-100)',
  `sign_y_percent` decimal(8, 4) NULL DEFAULT NULL COMMENT '签名Y坐标百分比(0-100)',
  `sign_page_index` int(0) NULL DEFAULT NULL COMMENT '签名页码(从0开始)',
  `sign_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '签署IP地址',
  `sign_device` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '签署设备信息',
  `employee_reject_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '员工拒绝原因',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_contract_type`(`contract_type`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `employee_contracts_created_by_foreign`(`created_by`) USING BTREE,
  INDEX `idx_approval`(`approval_instance_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 110 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '员工合同表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for employee_deduction_details
-- ----------------------------
DROP TABLE IF EXISTS `employee_deduction_details`;
CREATE TABLE `employee_deduction_details`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `project_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '项目ID',
  `deduction_items` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '专项扣除项目（格式：项目ID:金额|项目ID:金额）',
  `total_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '总扣除金额',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `updated_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '更新人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_employee_project`(`employee_id`, `project_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_employee`(`employee_id`) USING BTREE,
  INDEX `idx_project`(`project_id`) USING BTREE,
  INDEX `idx_active`(`is_active`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '员工专项扣除明细表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for employee_documents
-- ----------------------------
DROP TABLE IF EXISTS `employee_documents`;
CREATE TABLE `employee_documents`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `document_config_id` bigint(0) UNSIGNED NOT NULL COMMENT '资料配置ID',
  `document_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '资料名称',
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
  `file_size` bigint(0) UNSIGNED NULL DEFAULT 0 COMMENT '文件大小（字节）',
  `file_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件MIME类型',
  `upload_source` enum('miniapp','pc') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'miniapp' COMMENT '上传来源：miniapp=小程序，pc=PC端',
  `uploaded_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '上传时间',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `idx_unique_employee_document`(`employee_id`, `document_config_id`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_document_config_id`(`document_config_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '员工资料上传记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for employee_insurance_enrollment
-- ----------------------------
DROP TABLE IF EXISTS `employee_insurance_enrollment`;
CREATE TABLE `employee_insurance_enrollment`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `policy_id` bigint(0) UNSIGNED NOT NULL COMMENT '保单ID',
  `policy_version` int(0) NOT NULL DEFAULT 1 COMMENT '保单版本号',
  `enrollment_date` date NOT NULL COMMENT '参保日期',
  `payment_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '支付金额',
  `status` enum('active','expired') CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT 'active' COMMENT '状态：active=生效，expired=过期',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_enrollment`(`employee_id`, `policy_id`, `policy_version`) USING BTREE,
  INDEX `idx_employee`(`employee_id`) USING BTREE,
  INDEX `idx_policy`(`policy_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci COMMENT = '员工其他保险参保记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for employee_large_medical_insurance
-- ----------------------------
DROP TABLE IF EXISTS `employee_large_medical_insurance`;
CREATE TABLE `employee_large_medical_insurance`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `config_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '大额医疗保险配置ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '所属账套ID',
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用：1=是，0=否',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_employee`(`employee_id`) USING BTREE,
  INDEX `idx_config`(`config_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_enabled`(`is_enabled`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '员工大额医疗保险配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for employee_projects
-- ----------------------------
DROP TABLE IF EXISTS `employee_projects`;
CREATE TABLE `employee_projects`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(0) UNSIGNED NOT NULL,
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NULL DEFAULT NULL,
  `status` enum('active','inactive','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'active',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_employee_project`(`employee_id`, `project_id`) USING BTREE,
  INDEX `project_id`(`project_id`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_employee_project`(`employee_id`, `project_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 24 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for employee_salaries
-- ----------------------------
DROP TABLE IF EXISTS `employee_salaries`;
CREATE TABLE `employee_salaries`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `salary_sheet_id` bigint(0) UNSIGNED NOT NULL COMMENT '工资表ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `basic_salary` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '基本工资',
  `overtime_pay` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '加班费',
  `bonus` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '奖金',
  `deductions` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '扣款',
  `net_salary` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '实发工资',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_salary_employee`(`salary_sheet_id`, `employee_id`) USING BTREE,
  INDEX `idx_salary_sheet_id`(`salary_sheet_id`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '员工工资详情' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for employee_special_deductions
-- ----------------------------
DROP TABLE IF EXISTS `employee_special_deductions`;
CREATE TABLE `employee_special_deductions`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `project_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '项目ID',
  `deduction_item_id` bigint(0) UNSIGNED NOT NULL COMMENT '专项扣除项目ID',
  `custom_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '自定义金额（为空则使用项目默认金额）',
  `expiry_date` date NULL DEFAULT NULL COMMENT '失效日期',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_employee_deduction`(`employee_id`, `deduction_item_id`, `project_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_employee`(`employee_id`) USING BTREE,
  INDEX `idx_project`(`project_id`) USING BTREE,
  INDEX `idx_deduction_item`(`deduction_item_id`) USING BTREE,
  INDEX `idx_active`(`is_active`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '员工专项扣除表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for employees
-- ----------------------------
DROP TABLE IF EXISTS `employees`;
CREATE TABLE `employees`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '岗位',
  `department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '部门',
  `id_number` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '身份证号',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `gender` enum('male','female') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nationality` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT '中国' COMMENT '国籍',
  `marital_status` enum('single','married','divorced','widowed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'single' COMMENT '婚姻状态',
  `education` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '学历',
  `birth_date` date NOT NULL,
  `hire_date` date NOT NULL COMMENT '入职日期',
  `contract_start_date` date NOT NULL COMMENT '合同开始日期',
  `contract_end_date` date NULL DEFAULT NULL COMMENT '合同结束日期',
  `contract_status` enum('unsigned','in_approval','active','expired','terminated','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'unsigned' COMMENT '合同状态：unsigned=未签署, in_approval=审批中, active=在职, expired=已过期, terminated=已终止, rejected=已驳回',
  `termination_date` date NULL DEFAULT NULL,
  `termination_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `is_retired` tinyint(1) NOT NULL DEFAULT 0,
  `retirement_date` date NULL DEFAULT NULL,
  `social_security_region_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '社保参保地区ID',
  `housing_fund_region_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '公积金参保地区ID',
  `housing_fund_config_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '公积金配置ID',
  `large_medical_insurance_config_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '大额医疗保险配置ID',
  `password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '自定义密码（修改后）',
  `password_changed_at` timestamp(0) NULL DEFAULT NULL COMMENT '密码修改时间',
  `login_failed_count` int(0) NULL DEFAULT 0 COMMENT '登录失败次数',
  `locked_until` timestamp(0) NULL DEFAULT NULL COMMENT '锁定到什么时间',
  `last_login_at` timestamp(0) NULL DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '最后登录IP',
  `address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `emergency_contact` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '紧急联系人',
  `emergency_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '紧急联系电话',
  `bank_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '开户银行',
  `bank_account` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '银行账号',
  `bank_account_holder` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '银行账户户名',
  `bank_branch` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '开户支行',
  `basic_salary` decimal(10, 2) NULL DEFAULT NULL COMMENT '基础工资',
  `social_security_base` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '社保缴费基数',
  `medical_insurance_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '医保基数',
  `large_medical_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '大额基数',
  `housing_fund_base` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '公积金缴费基数',
  `large_medical_payment_cycle` enum('month','year') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '大额医疗保险付款周期',
  `large_medical_calculation_method` enum('base','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '大额医疗保险计算方式',
  `large_medical_company_ratio` decimal(5, 4) NULL DEFAULT NULL COMMENT '大额医疗保险公司缴纳比例',
  `large_medical_employee_ratio` decimal(5, 4) NULL DEFAULT NULL COMMENT '大额医疗保险员工缴纳比例',
  `large_medical_company_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '大额医疗保险公司缴纳金额',
  `large_medical_employee_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '大额医疗保险员工缴纳金额',
  `special_deduction` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '专项扣除金额',
  `is_annual_deduction` tinyint(1) NULL DEFAULT 0 COMMENT '是否年度扣除',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `project_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '所属项目',
  `medical_insurance_region_id` bigint(0) UNSIGNED NULL DEFAULT NULL,
  `insurance_completed_at` timestamp(0) NULL DEFAULT NULL COMMENT '参保完成时间',
  `employee_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `is_face_verified` tinyint(1) NULL DEFAULT 0 COMMENT '是否已完成人脸识别',
  `face_verified_at` timestamp(0) NULL DEFAULT NULL COMMENT '人脸识别时间',
  `tencent_biz_token` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '腾讯云核身流水号',
  `verification_result` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '核身结果数据(JSON)',
  `face_similarity` decimal(5, 2) NULL DEFAULT NULL COMMENT '人脸相似度',
  `login_password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '登录密码',
  `country_region` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '国籍(地区)',
  `chinese_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '中文名',
  `birth_country` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '出生国家(地区)',
  `other_id_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '其他证件类型',
  `other_id_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '其他证件号码',
  `personnel_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '人员状态',
  `employment_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '任职受雇从业类型',
  `employment_date` date NULL DEFAULT NULL COMMENT '任职受雇从业日期',
  `resignation_date` date NULL DEFAULT NULL COMMENT '离职日期',
  `annual_employment_status` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '入职年度就业情形',
  `job_title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '职务',
  `is_disabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否残疾',
  `disability_cert_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '残疾证件类型',
  `disability_cert_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '残疾证号',
  `is_martyr_family` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否烈属',
  `martyr_family_cert_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '烈属证号',
  `is_elderly_alone` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否孤老',
  `tax_matter` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '涉税事由',
  `deduct_expense` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否扣除减除费用',
  `personal_investment_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '个人投资额',
  `personal_investment_ratio` decimal(5, 2) NULL DEFAULT NULL COMMENT '个人投资比例(%)',
  `first_entry_date` date NULL DEFAULT NULL COMMENT '首次入境时间',
  `expected_departure_date` date NULL DEFAULT NULL COMMENT '预计离境时间',
  `email_address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '电子邮箱',
  `bank_province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '开户行省份',
  `other_notes` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '其他情况说明',
  `household_province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '户籍所在地（省）',
  `household_city` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '户籍所在地（市）',
  `household_district` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '户籍所在地（区县）',
  `household_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '户籍所在地（详细地址）',
  `residence_province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '经常居住地（省）',
  `residence_city` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '经常居住地（市）',
  `residence_district` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '经常居住地（区县）',
  `residence_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '经常居住地（详细地址）',
  `contact_province` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系地址（省）',
  `contact_city` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系地址（市）',
  `contact_district` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系地址（区县）',
  `contact_address` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '联系地址（详细地址）',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `id_number`(`id_number`) USING BTREE,
  INDEX `idx_employees_id_number`(`id_number`) USING BTREE,
  INDEX `idx_employees_phone`(`phone`) USING BTREE,
  INDEX `idx_id_number`(`id_number`) USING BTREE,
  INDEX `idx_phone`(`phone`) USING BTREE,
  INDEX `idx_contract_status`(`contract_status`) USING BTREE,
  INDEX `idx_created_at`(`created_at`) USING BTREE,
  INDEX `idx_employees_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_employees_social_security_region`(`social_security_region_id`) USING BTREE,
  INDEX `idx_employees_housing_fund_region`(`housing_fund_region_id`) USING BTREE,
  INDEX `employees_medical_insurance_region_id_foreign`(`medical_insurance_region_id`) USING BTREE,
  INDEX `employees_housing_fund_config_id_foreign`(`housing_fund_config_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 26 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for housing_fund_configs
-- ----------------------------
DROP TABLE IF EXISTS `housing_fund_configs`;
CREATE TABLE `housing_fund_configs`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_id` bigint(0) UNSIGNED NOT NULL COMMENT '地区ID',
  `config_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置名称',
  `base_amount` decimal(10, 2) NOT NULL COMMENT '基数',
  `min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '下限基数',
  `max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '上限基数',
  `old_min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '旧最低基数',
  `old_max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '旧最高基数',
  `old_limits_updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '旧上下限修改时间',
  `new_min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '新最低基数',
  `new_max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '新最高基数',
  `new_limits_updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '新上下限修改时间',
  `employee_ratio` decimal(5, 4) NOT NULL COMMENT '员工缴纳比例',
  `company_ratio` decimal(5, 4) NOT NULL COMMENT '公司缴纳比例',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为默认配置',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `housing_fund_configs_region_id_foreign`(`region_id`) USING BTREE,
  INDEX `housing_fund_configs_created_by_foreign`(`created_by`) USING BTREE,
  INDEX `housing_fund_configs_account_set_id_foreign`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '公积金配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for housing_fund_regions
-- ----------------------------
DROP TABLE IF EXISTS `housing_fund_regions`;
CREATE TABLE `housing_fund_regions`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `account_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '公积金账号',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '公积金地区表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for housing_funds
-- ----------------------------
DROP TABLE IF EXISTS `housing_funds`;
CREATE TABLE `housing_funds`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '公积金配置ID',
  `region_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `base_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '基数',
  `employee_ratio` decimal(5, 4) NOT NULL DEFAULT 0.0000 COMMENT '员工缴纳比例',
  `company_ratio` decimal(5, 4) NOT NULL DEFAULT 0.0000 COMMENT '公司缴纳比例',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `adjustment_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整基数（预设值）',
  `effective_date` date NULL DEFAULT NULL COMMENT '生效时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `housing_funds_account_set_id_foreign`(`account_set_id`) USING BTREE,
  INDEX `housing_funds_created_by_foreign`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '公积金管理表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for insurance_change_attachments
-- ----------------------------
DROP TABLE IF EXISTS `insurance_change_attachments`;
CREATE TABLE `insurance_change_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `insurance_change_id` bigint(0) UNSIGNED NOT NULL COMMENT '增减记录ID',
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
  `file_size` int(0) UNSIGNED NOT NULL COMMENT '文件大小（字节）',
  `file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件类型',
  `uploaded_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '上传人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_insurance_change_id`(`insurance_change_id`) USING BTREE,
  INDEX `idx_uploaded_by`(`uploaded_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 62 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '保险变更附件表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for insurance_change_details
-- ----------------------------
DROP TABLE IF EXISTS `insurance_change_details`;
CREATE TABLE `insurance_change_details`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `insurance_change_id` bigint(0) UNSIGNED NOT NULL COMMENT '参保增减ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `insurance_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险类型：社保、医保、公积金、其他保险',
  `insurance_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险名称',
  `region_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '地区名称',
  `base_amount` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '基数',
  `employee_ratio` decimal(8, 4) NULL DEFAULT 0.0000 COMMENT '员工比例',
  `company_ratio` decimal(8, 4) NULL DEFAULT 0.0000 COMMENT '公司比例',
  `employee_amount` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '员工缴纳金额',
  `company_amount` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '公司缴纳金额',
  `total_amount` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '总缴纳金额',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'active' COMMENT '状态',
  `effective_date` timestamp(0) NULL DEFAULT NULL COMMENT '生效日期',
  `expiry_date` timestamp(0) NULL DEFAULT NULL COMMENT '失效日期',
  `payment_cycle` enum('month','year') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '付款周期：month=按月，year=按年',
  `payment_month` tinyint(0) UNSIGNED NULL DEFAULT NULL COMMENT '付款月份（1-12），用于按年付款',
  `medical_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '医疗基数',
  `pension_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '养老、失业、工伤基数',
  `employee_medical_insurance_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工医保基数',
  `employee_social_security_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工社保基数',
  `employee_housing_fund_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工公积金基数',
  `dynamic_insurance_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `detail_type` enum('summary','detail') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'detail' COMMENT '明细类型：summary=汇总明细，detail=细分明细',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `insurance_change_details_insurance_change_id_index`(`insurance_change_id`) USING BTREE,
  INDEX `insurance_change_details_employee_id_project_id_index`(`employee_id`, `project_id`) USING BTREE,
  INDEX `insurance_change_details_insurance_type_index`(`insurance_type`) USING BTREE,
  INDEX `insurance_change_details_account_set_id_index`(`account_set_id`) USING BTREE,
  INDEX `insurance_change_details_status_index`(`status`) USING BTREE,
  INDEX `insurance_change_details_effective_date_index`(`effective_date`) USING BTREE,
  INDEX `insurance_change_details_project_id_foreign`(`project_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 182 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '参保明细表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for insurance_change_summaries
-- ----------------------------
DROP TABLE IF EXISTS `insurance_change_summaries`;
CREATE TABLE `insurance_change_summaries`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `region_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `insurance_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险类型：社保、医保、公积金、其他保险',
  `insurance_name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险名称',
  `employee_count` int(0) NULL DEFAULT 0 COMMENT '参保人数',
  `total_base_amount` decimal(15, 2) NULL DEFAULT 0.00 COMMENT '总基数',
  `total_employee_amount` decimal(15, 2) NULL DEFAULT 0.00 COMMENT '员工总缴纳金额',
  `total_company_amount` decimal(15, 2) NULL DEFAULT 0.00 COMMENT '公司总缴纳金额',
  `total_amount` decimal(15, 2) NULL DEFAULT 0.00 COMMENT '总缴纳金额',
  `summary_date` date NOT NULL COMMENT '汇总日期',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `insurance_change_summaries_account_set_id_summary_date_index`(`account_set_id`, `summary_date`) USING BTREE,
  INDEX `insurance_change_summaries_insurance_type_index`(`insurance_type`) USING BTREE,
  INDEX `insurance_change_summaries_summary_date_index`(`summary_date`) USING BTREE,
  INDEX `insurance_change_summaries_created_by_index`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 16 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '参保汇总表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for insurance_changes
-- ----------------------------
DROP TABLE IF EXISTS `insurance_changes`;
CREATE TABLE `insurance_changes`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `employee_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '员工姓名',
  `employee_id_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '员工身份证号',
  `employee_gender` tinyint(0) NULL DEFAULT NULL COMMENT '员工性别 1男2女',
  `employee_birth_date` date NULL DEFAULT NULL COMMENT '员工出生日期',
  `employee_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '员工联系电话',
  `employee_status` tinyint(0) NULL DEFAULT NULL COMMENT '员工状态 1在职2离职',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `change_type` enum('increase','decrease') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'increase' COMMENT '增减类型：increase=新增参保，decrease=减少参保',
  `contract_id` bigint(0) UNSIGNED NULL DEFAULT NULL,
  `social_security_region_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '社保地区ID',
  `medical_insurance_region_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '医保地区ID',
  `housing_fund_region_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '公积金地区ID',
  `housing_fund_config_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '公积金配置ID',
  `large_medical_insurance_config_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '大额医疗保险配置ID',
  `large_medical_insurance_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用大额医疗保险：1=是，0=否',
  `employee_social_security_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工社保基数',
  `employee_medical_insurance_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工医保基数',
  `employee_housing_fund_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工公积金基数',
  `employee_large_medical_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工大额医疗保险基数',
  `social_security_types` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `medical_insurance_types` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `housing_fund_params` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `other_insurance_policies` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `used_quotas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `last_snapshot` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '上次保险配置快照',
  `change_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '变更摘要',
  `change_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `status` enum('pending','processing','submitted','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'pending' COMMENT '状态：待处理、处理中、待提交汇总审批、已完成',
  `fully_confirmed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已完整确认处理（1=是，0=否）',
  `other_insurance_processed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已单独处理其他保险（1=是，0=否）',
  `attachment_path` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '附件路径',
  `attachment_uploaded_at` timestamp(0) NULL DEFAULT NULL COMMENT '附件上传时间',
  `processed_at` timestamp(0) NULL DEFAULT NULL COMMENT '处理完成时间',
  `submitted_at` timestamp(0) NULL DEFAULT NULL COMMENT '提交汇总审批时间',
  `completed_at` timestamp(0) NULL DEFAULT NULL COMMENT '完成时间',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人',
  `processed_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '处理人',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  `large_medical_insurance_config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `personnel_id` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '人员ID，用于与人员档案绑定关联',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `insurance_changes_employee_id_project_id_index`(`employee_id`, `project_id`) USING BTREE,
  INDEX `insurance_changes_status_index`(`status`) USING BTREE,
  INDEX `insurance_changes_account_set_id_index`(`account_set_id`) USING BTREE,
  INDEX `insurance_changes_social_security_region_id_index`(`social_security_region_id`) USING BTREE,
  INDEX `insurance_changes_medical_insurance_region_id_index`(`medical_insurance_region_id`) USING BTREE,
  INDEX `insurance_changes_housing_fund_id_index`(`housing_fund_region_id`) USING BTREE,
  INDEX `insurance_changes_created_by_index`(`created_by`) USING BTREE,
  INDEX `insurance_changes_processed_by_index`(`processed_by`) USING BTREE,
  INDEX `insurance_changes_project_id_foreign`(`project_id`) USING BTREE,
  INDEX `insurance_changes_housing_fund_region_id_index`(`housing_fund_region_id`) USING BTREE,
  INDEX `insurance_changes_housing_fund_config_id_index`(`housing_fund_config_id`) USING BTREE,
  INDEX `insurance_changes_contract_id_foreign`(`contract_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 111 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '参保增减主表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for insurance_compensation_records
-- ----------------------------
DROP TABLE IF EXISTS `insurance_compensation_records`;
CREATE TABLE `insurance_compensation_records`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `employee_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '员工姓名',
  `employee_id_number` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '员工身份证号',
  `compensation_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '补差类型',
  `old_base` decimal(10, 2) NOT NULL COMMENT '旧基数',
  `old_base_constrained` decimal(10, 2) NULL DEFAULT NULL COMMENT '旧基数（约束后）',
  `new_base` decimal(10, 2) NOT NULL COMMENT '新基数',
  `new_base_constrained` decimal(10, 2) NULL DEFAULT NULL COMMENT '新基数（约束后）',
  `compensation_start_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '补差开始月份 YYYY-MM',
  `compensation_end_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '补差结束月份 YYYY-MM',
  `compensation_months` int(0) NOT NULL COMMENT '补差月数',
  `compensation_details` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '补差明细JSON',
  `company_total` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '单位补差合计',
  `personal_total` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '个人补差合计',
  `total_amount` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '补差总计',
  `base_adjustment_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '关联的基数调整记录ID',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'pending' COMMENT '状态',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_compensation`(`employee_id`, `project_id`, `compensation_type`, `compensation_start_month`, `compensation_end_month`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_project`(`project_id`) USING BTREE,
  INDEX `idx_employee`(`employee_id`) USING BTREE,
  INDEX `idx_compensation_month`(`compensation_start_month`, `compensation_end_month`) USING BTREE,
  INDEX `idx_base_adjustment`(`base_adjustment_id`) USING BTREE,
  INDEX `idx_created_at`(`created_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 28 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '保险补差记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for insurance_detail_records
-- ----------------------------
DROP TABLE IF EXISTS `insurance_detail_records`;
CREATE TABLE `insurance_detail_records`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `insurance_personnel_id` bigint(0) UNSIGNED NOT NULL COMMENT '参保人员信息ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `employee_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '员工姓名',
  `employee_id_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '员工身份证号',
  `employee_gender` tinyint(0) NULL DEFAULT NULL COMMENT '员工性别',
  `employee_birth_date` date NULL DEFAULT NULL COMMENT '员工出生日期',
  `employee_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '员工电话',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `project_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '项目名称',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `record_year` int(0) NOT NULL COMMENT '记录年份',
  `record_month` int(0) NOT NULL COMMENT '记录月份',
  `employee_social_security_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工社保基数',
  `employee_medical_insurance_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工医保基数',
  `employee_housing_fund_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工公积金基数',
  `employee_large_medical_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工大额医疗保险基数',
  `social_security_types` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `medical_insurance_types` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `housing_fund_params` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `other_insurance_policies` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `large_medical_insurance_config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `social_security_company_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '社保公司缴纳金额',
  `social_security_employee_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '社保员工缴纳金额',
  `medical_insurance_company_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '医保公司缴纳金额',
  `medical_insurance_employee_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '医保员工缴纳金额',
  `housing_fund_company_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '公积金公司缴纳金额',
  `housing_fund_employee_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '公积金员工缴纳金额',
  `large_medical_company_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '大额医疗保险公司缴纳金额',
  `large_medical_employee_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '大额医疗保险员工缴纳金额',
  `other_insurance_total_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '其他保险总金额',
  `status` enum('generated','confirmed','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'generated' COMMENT '状态',
  `generated_at` timestamp(0) NULL DEFAULT NULL COMMENT '生成时间',
  `confirmed_at` timestamp(0) NULL DEFAULT NULL COMMENT '确认时间',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_employee_project_month`(`employee_id`, `project_id`, `account_set_id`, `record_year`, `record_month`) USING BTREE,
  INDEX `idx_insurance_personnel_id`(`insurance_personnel_id`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_record_date`(`record_year`, `record_month`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 39 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '参保明细记录表（按月存储）' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for insurance_personnel
-- ----------------------------
DROP TABLE IF EXISTS `insurance_personnel`;
CREATE TABLE `insurance_personnel`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `employee_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '员工姓名',
  `employee_id_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '员工身份证号',
  `employee_gender` tinyint(0) NULL DEFAULT NULL COMMENT '员工性别',
  `employee_birth_date` date NULL DEFAULT NULL COMMENT '员工出生日期',
  `employee_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '员工电话',
  `employee_status` tinyint(0) NULL DEFAULT NULL COMMENT '员工状态',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `social_security_region_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '社保地区ID',
  `social_security_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '社保编号',
  `medical_insurance_region_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '医保地区ID',
  `medical_insurance_code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '医保编号',
  `housing_fund_region_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '公积金地区ID',
  `housing_fund_account_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '公积金账号',
  `housing_fund_config_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '公积金配置ID',
  `large_medical_insurance_config_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '大额医疗保险配置ID',
  `large_medical_insurance_enabled` tinyint(1) NOT NULL DEFAULT 0 COMMENT '大额医疗保险是否启用',
  `employee_social_security_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工社保基数',
  `employee_medical_insurance_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工医保基数',
  `employee_housing_fund_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工公积金基数',
  `employee_large_medical_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工大额医疗保险基数',
  `social_security_types` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `medical_insurance_types` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `housing_fund_params` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `other_insurance_policies` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '其他保险配置快照',
  `is_compensation` tinyint(1) NULL DEFAULT 0 COMMENT '是否为补差记录：0-正常，1-补差',
  `compensation_months` int(0) NULL DEFAULT NULL COMMENT '补差月数',
  `compensation_start_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '补差开始月份 YYYY-MM',
  `compensation_end_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '补差结束月份 YYYY-MM',
  `old_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '旧基数',
  `new_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '新基数',
  `other_insurance_enrolled_at` timestamp(0) NULL DEFAULT NULL COMMENT '其他保险参保时间（首次生成明细的时间）',
  `other_insurance_policy_versions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `large_medical_insurance_config` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `used_quotas` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '已用名额信息',
  `status` enum('active','inactive') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '状态',
  `first_confirmation_date` date NULL DEFAULT NULL COMMENT '首次确认处理日期',
  `payment_period` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '费款所属期（如：202507）',
  `enrollment_period` varchar(6) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '参保日期（入职日期）',
  `employee_type` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '正常' COMMENT '员工类型：正常/补交',
  `last_updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '最后更新时间',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `large_medical_payment_start_month` int(0) NULL DEFAULT NULL COMMENT '大额医疗保险支付起始月份(1-12)，年付模式下使用',
  `large_medical_payment_start_year` int(0) NULL DEFAULT NULL COMMENT '大额医疗保险支付起始年份，年付模式下使用',
  `large_medical_last_payment_month` int(0) NULL DEFAULT NULL COMMENT '上次支付月份(1-12)，用于跟踪支付历史',
  `large_medical_last_payment_year` int(0) NULL DEFAULT NULL COMMENT '上次支付年份，用于跟踪支付历史',
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_employee_project_compensation`(`employee_id`, `project_id`, `is_compensation`, `compensation_start_month`, `compensation_end_month`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_large_medical_payment`(`large_medical_insurance_enabled`, `large_medical_payment_start_month`, `large_medical_payment_start_year`) USING BTREE,
  INDEX `idx_employee_type`(`employee_type`) USING BTREE,
  INDEX `idx_first_confirmation_date`(`first_confirmation_date`) USING BTREE,
  INDEX `idx_payment_period`(`payment_period`) USING BTREE,
  INDEX `idx_enrollment_period`(`enrollment_period`) USING BTREE,
  INDEX `idx_is_compensation`(`is_compensation`) USING BTREE,
  INDEX `idx_compensation_month`(`compensation_start_month`, `compensation_end_month`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 29 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '参保人员信息表（确认后的参保信息）' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for insurance_records
-- ----------------------------
DROP TABLE IF EXISTS `insurance_records`;
CREATE TABLE `insurance_records`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL,
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `insurance_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险类型',
  `base_amount` decimal(10, 2) NOT NULL COMMENT '缴费基数',
  `company_rate` decimal(6, 4) NOT NULL COMMENT '公司缴费比例',
  `personal_rate` decimal(6, 4) NOT NULL COMMENT '个人缴费比例',
  `company_amount` decimal(10, 2) NOT NULL COMMENT '公司缴费',
  `personal_amount` decimal(10, 2) NOT NULL COMMENT '个人缴费',
  `total_amount` decimal(10, 2) NOT NULL COMMENT '总缴费',
  `payment_date` date NULL DEFAULT NULL COMMENT '缴费日期',
  `due_date` date NULL DEFAULT NULL COMMENT '到期日期',
  `status` enum('pending','paid','overdue') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'pending',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `employee_id`(`employee_id`) USING BTREE,
  INDEX `project_id`(`project_id`) USING BTREE,
  INDEX `idx_insurance_records_type`(`insurance_type`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_due_date`(`due_date`) USING BTREE,
  INDEX `idx_insurance_records_account_set_id`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for invoice_applications
-- ----------------------------
DROP TABLE IF EXISTS `invoice_applications`;
CREATE TABLE `invoice_applications`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `application_no` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '申请单号',
  `task_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '任务名称/描述',
  `year` int(0) NOT NULL COMMENT '年度',
  `month` int(0) NOT NULL COMMENT '月份',
  `project_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '项目名称',
  `period_year` int(0) NULL DEFAULT NULL COMMENT '所属期-年份',
  `period_month` int(0) NULL DEFAULT NULL COMMENT '所属期-月份',
  `company_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '单位名称',
  `application_date` date NULL DEFAULT NULL COMMENT '申请日期',
  `invoice_method` enum('full','partial','none') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '开票方式：full-全额，partial-缺额，none-无',
  `invoice_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '普票' COMMENT '开票种类：普票、专票等',
  `deduction_amount` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '扣除额',
  `tax_rate` decimal(5, 4) NOT NULL DEFAULT 0.0000 COMMENT '税率（如0.06表示6%）',
  `amount_excluding_tax` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '不含税金额',
  `invoice_tax_amount` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '开票税额',
  `tax_amount` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '税金',
  `invoice_date` date NULL DEFAULT NULL COMMENT '开票日期',
  `is_completed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否完成',
  `invoicer` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '开票人',
  `invoice_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '发票号码',
  `invoice_remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '开票备注',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '状态：draft-草稿，normal-正常，pending-审批中，approved-已通过，rejected-已驳回，red_flushed-红冲',
  `approval_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '审批状态：pending-审批中，approved-已通过，rejected-已驳回',
  `has_resubmitted` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已重新发起（红冲后）',
  `new_application_id` bigint(0) NULL DEFAULT NULL COMMENT '重新发起后的新申请ID',
  `approval_instance_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批实例ID',
  `submitter_id` bigint(0) UNSIGNED NOT NULL COMMENT '提交人ID（业务人员）',
  `submitted_at` timestamp(0) NULL DEFAULT NULL COMMENT '提交时间',
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '驳回原因',
  `attachments` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '附件列表',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `uk_application_no`(`application_no`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_year_month`(`year`, `month`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_submitter`(`submitter_id`) USING BTREE,
  INDEX `idx_approval`(`approval_instance_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 20 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '发票申请表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for invoice_items
-- ----------------------------
DROP TABLE IF EXISTS `invoice_items`;
CREATE TABLE `invoice_items`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `application_id` bigint(0) UNSIGNED NOT NULL COMMENT '发票申请ID',
  `invoice_project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目配置ID',
  `project_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称（冗余，用于生成PDF）',
  `sequence` int(0) NOT NULL DEFAULT 1 COMMENT '序号',
  `item_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '名称（应发工资/劳保费/商业险）',
  `amount` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '金额',
  `remark` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '备注',
  `created_at` timestamp(0) NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_application`(`application_id`) USING BTREE,
  INDEX `idx_invoice_project`(`invoice_project_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '发票申请明细表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for invoice_projects
-- ----------------------------
DROP TABLE IF EXISTS `invoice_projects`;
CREATE TABLE `invoice_projects`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 10 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '发票项目配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for invoices
-- ----------------------------
DROP TABLE IF EXISTS `invoices`;
CREATE TABLE `invoices`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `invoice_number` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(12, 2) NOT NULL,
  `tax_amount` decimal(12, 2) NOT NULL DEFAULT 0.00,
  `tax_rate` decimal(4, 4) NOT NULL DEFAULT 0.0000,
  `total_amount` decimal(12, 2) NOT NULL,
  `type` enum('vat_special','vat_ordinary','ordinary') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `issue_date` date NULL DEFAULT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `deduction_details` varchar(0) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `status` enum('draft','submitted','approved','issued','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `applicant_id` bigint(0) UNSIGNED NOT NULL,
  `approver_id` bigint(0) UNSIGNED NULL DEFAULT NULL,
  `submitted_at` timestamp(0) NULL DEFAULT NULL,
  `approved_at` timestamp(0) NULL DEFAULT NULL,
  `issued_at` timestamp(0) NULL DEFAULT NULL,
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `invoices_invoice_number_unique`(`invoice_number`) USING BTREE,
  INDEX `invoices_project_id_foreign`(`project_id`) USING BTREE,
  INDEX `invoices_applicant_id_foreign`(`applicant_id`) USING BTREE,
  INDEX `invoices_approver_id_foreign`(`approver_id`) USING BTREE,
  INDEX `idx_invoices_account_set_id`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for large_medical_insurance_configs
-- ----------------------------
DROP TABLE IF EXISTS `large_medical_insurance_configs`;
CREATE TABLE `large_medical_insurance_configs`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `region_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '所属账套ID',
  `calculation_type` enum('base','fixed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'base' COMMENT '计算方式：base=按基数，fixed=按固定金额',
  `base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '基数金额',
  `company_ratio` decimal(5, 2) NULL DEFAULT NULL COMMENT '公司承担比例(%)',
  `employee_ratio` decimal(5, 2) NULL DEFAULT NULL COMMENT '员工承担比例(%)',
  `company_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '公司固定金额',
  `employee_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工固定金额',
  `payment_cycle` enum('month','year') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'month' COMMENT '付款周期：month=按月，year=按年',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1=启用，0=禁用',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '大额医疗保险地区配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for medical_insurance_regions
-- ----------------------------
DROP TABLE IF EXISTS `medical_insurance_regions`;
CREATE TABLE `medical_insurance_regions`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '医保编号',
  `min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '最低基数',
  `max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '最高基数',
  `old_min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '旧最低基数',
  `old_max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '旧最高基数',
  `old_limits_updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '旧上下限修改时间',
  `new_min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '新最低基数',
  `new_max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '新最高基数',
  `new_limits_updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '新上下限修改时间',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '所属账套ID',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '医保地区表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for medical_insurance_types
-- ----------------------------
DROP TABLE IF EXISTS `medical_insurance_types`;
CREATE TABLE `medical_insurance_types`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_id` bigint(0) UNSIGNED NOT NULL COMMENT '所属地区ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险名称（如：医疗保险）',
  `min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '下限基数',
  `max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '上限基数',
  `employee_ratio` decimal(6, 4) NOT NULL COMMENT '员工缴纳比例',
  `company_ratio` decimal(6, 4) NOT NULL COMMENT '公司缴纳比例',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '所属账套ID',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_region_id`(`region_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '医保类型表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for migrations
-- ----------------------------
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations`  (
  `id` int(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int(0) NOT NULL,
  PRIMARY KEY (`id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 32 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for notifications
-- ----------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(0) UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_read` tinyint(1) NULL DEFAULT 0,
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_id`(`user_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for onboarding_forms
-- ----------------------------
DROP TABLE IF EXISTS `onboarding_forms`;
CREATE TABLE `onboarding_forms`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL COMMENT '员工ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `registration_date` date NOT NULL COMMENT '登记日期',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '姓名',
  `gender` enum('male','female') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '性别',
  `ethnicity` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '民族',
  `political_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '政治面貌',
  `place_of_origin` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '籍贯',
  `birth_date` date NULL DEFAULT NULL COMMENT '出生年月',
  `graduated_school` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '毕业学校',
  `graduation_date` date NULL DEFAULT NULL COMMENT '毕业时间',
  `education_level` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文化程度',
  `major` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '所学专业',
  `degree` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '学位',
  `technical_title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '技术职称',
  `health_status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '健康状况',
  `height` int(0) NULL DEFAULT NULL COMMENT '身高(cm)',
  `weight` decimal(5, 2) NULL DEFAULT NULL COMMENT '体重(kg)',
  `marital_status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '婚姻状况',
  `id_number` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '身份证号码',
  `current_residence` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '现居住地',
  `household_registration` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '户口所在地',
  `position` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '岗位',
  `desired_location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '求职地区',
  `accept_assignment` tinyint(1) NULL DEFAULT NULL COMMENT '是否服从调配',
  `contact_address` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系地址',
  `contact_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系电话',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `declaration_agreed` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否同意声明',
  `signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '本人签名图片路径',
  `education_background` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '学习简历',
  `work_experience` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '工作经历',
  `family_info` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '家庭情况',
  `created_at` timestamp(0) NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_id_number`(`id_number`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '入职登记表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for other_insurance_policies
-- ----------------------------
DROP TABLE IF EXISTS `other_insurance_policies`;
CREATE TABLE `other_insurance_policies`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `type_id` bigint(0) UNSIGNED NOT NULL COMMENT '保险种类ID',
  `policy_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保单号',
  `policy_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保单名称',
  `insurance_company` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险公司',
  `coverage_amount` decimal(15, 2) NOT NULL COMMENT '保险金额',
  `employee_per_capita_cost` decimal(10, 2) NULL DEFAULT NULL COMMENT '员工人均参保费用',
  `quota` int(0) UNSIGNED NOT NULL DEFAULT 0 COMMENT '名额',
  `contact_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系人姓名',
  `contact_phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '联系人电话',
  `personnel_name_list` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `endorsement_number` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '批单号',
  `policy_end_date` date NULL DEFAULT NULL COMMENT '保单结束时间',
  `premium_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '保费金额',
  `start_date` date NOT NULL COMMENT '保险开始日期',
  `end_date` date NOT NULL COMMENT '保险结束日期',
  `status` enum('active','expired','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '保单状态',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '保单描述',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '所属账套ID',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_policy_number`(`policy_number`, `account_set_id`) USING BTREE,
  INDEX `idx_type_id`(`type_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_dates`(`start_date`, `end_date`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 14 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '其他保险保单表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for other_insurance_types
-- ----------------------------
DROP TABLE IF EXISTS `other_insurance_types`;
CREATE TABLE `other_insurance_types`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险种类名称（如：安责险）',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '保险种类描述',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '所属账套ID',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_type_per_account`(`name`, `account_set_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '其他保险种类表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for payment_applications
-- ----------------------------
DROP TABLE IF EXISTS `payment_applications`;
CREATE TABLE `payment_applications`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL,
  `process_approval_id` bigint(0) UNSIGNED NOT NULL,
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `project_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `initiator_id` bigint(0) UNSIGNED NOT NULL,
  `approval_instance_id` bigint(0) UNSIGNED NULL DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_process_approval_id`(`process_approval_id`) USING BTREE,
  INDEX `idx_initiator_id`(`initiator_id`) USING BTREE,
  INDEX `idx_approval_instance_id`(`approval_instance_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_month`(`month`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for payment_attachments
-- ----------------------------
DROP TABLE IF EXISTS `payment_attachments`;
CREATE TABLE `payment_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_application_id` bigint(0) UNSIGNED NOT NULL,
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `size` bigint(0) NOT NULL,
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `uploaded_by` bigint(0) UNSIGNED NOT NULL,
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_payment_application_id`(`payment_application_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for payment_form_history
-- ----------------------------
DROP TABLE IF EXISTS `payment_form_history`;
CREATE TABLE `payment_form_history`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `bank_account` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '银行账号（唯一标识）',
  `department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL COMMENT '所在部门',
  `payee` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL COMMENT '支付对象',
  `amount_small` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL COMMENT '小写金额',
  `amount_large` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL COMMENT '大写金额',
  `payment_method` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL COMMENT '付款方式（JSON数组）',
  `bank` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL DEFAULT NULL COMMENT '开户行',
  `purpose` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL COMMENT '付款用途',
  `invoice_status` text CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci NULL COMMENT '开票情况（JSON数组）',
  `user_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '创建用户ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_bank_account`(`bank_account`) USING BTREE,
  INDEX `idx_user_id`(`user_id`) USING BTREE,
  INDEX `idx_created_at`(`created_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci COMMENT = '付款申请单历史记忆表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for payment_request_attachments
-- ----------------------------
DROP TABLE IF EXISTS `payment_request_attachments`;
CREATE TABLE `payment_request_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_request_id` bigint(0) UNSIGNED NOT NULL COMMENT '付款申请ID',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件存储路径',
  `file_size` bigint(0) NOT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件MIME类型',
  `uploaded_by` bigint(0) UNSIGNED NOT NULL COMMENT '上传人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_payment_request_id`(`payment_request_id`) USING BTREE,
  INDEX `idx_uploaded_by`(`uploaded_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 48 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '付款申请附件表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for payment_requests
-- ----------------------------
DROP TABLE IF EXISTS `payment_requests`;
CREATE TABLE `payment_requests`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'reimbursement' COMMENT '付款类型：salary/insurance/reimbursement/报销/差旅/采购/项目/其他',
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '类目：报销/差旅/采购/项目/其他',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `insurance_summary_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '保险汇总ID（当payment_type=insurance时使用）',
  `salary_approval_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '工资表审批ID（当payment_type=salary时使用）',
  `reimbursement_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '报销申请ID（当payment_type=reimbursement时使用）',
  `approval_instance_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批实例ID',
  `amount` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '付款金额',
  `status` enum('pending','approved','rejected','paid') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态',
  `submitted_by` bigint(0) UNSIGNED NOT NULL COMMENT '提交人ID',
  `submitted_at` timestamp(0) NULL DEFAULT NULL COMMENT '提交时间',
  `approved_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批人ID',
  `approved_at` timestamp(0) NULL DEFAULT NULL COMMENT '审批时间',
  `paid_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '付款人ID',
  `paid_at` timestamp(0) NULL DEFAULT NULL COMMENT '付款时间',
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '驳回原因',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `project` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '项目',
  `apply_date` date NULL DEFAULT NULL COMMENT '申请日期',
  `unit_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '单位名称',
  `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '发票号码',
  `verified` tinyint(1) NULL DEFAULT 1 COMMENT '查验（1=已查验）',
  `payment_date` date NULL DEFAULT NULL COMMENT '打款日期',
  `expenditure_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '支出金额',
  `project_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '项目名称',
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '摘要',
  `invoice_received` tinyint(1) NULL DEFAULT 0 COMMENT '收到发票（1=已收到）',
  `invoice_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '发票类型',
  `invoice_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '开票金额',
  `tax_rate` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '税率',
  `deduction_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '扣除额',
  `amount_excluding_tax` decimal(15, 2) NULL DEFAULT NULL COMMENT '不含税金额',
  `tax_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '税金',
  `is_consistent` tinyint(1) NULL DEFAULT 0 COMMENT '是否一致（1=一致）',
  `status_checked` tinyint(1) NULL DEFAULT 1 COMMENT '状态（1=已确认）',
  `selected_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '勾选月份（YYYY-MM）',
  `reimburser` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '报销人',
  `invoice_date` date NULL DEFAULT NULL COMMENT '开票日期',
  `accounted` tinyint(1) NULL DEFAULT 1 COMMENT '入账（1=已入账）',
  `company` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '公司',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_payment_type`(`payment_type`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_insurance_summary_id`(`insurance_summary_id`) USING BTREE,
  INDEX `idx_salary_approval_id`(`salary_approval_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_submitted_by`(`submitted_by`) USING BTREE,
  INDEX `idx_reimbursement_id`(`reimbursement_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 44 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '付款申请表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for payment_summaries
-- ----------------------------
DROP TABLE IF EXISTS `payment_summaries`;
CREATE TABLE `payment_summaries`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_request_id` bigint(0) UNSIGNED NOT NULL COMMENT '付款申请ID',
  `payment_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '付款类型：salary/insurance/reimbursement/报销/差旅/采购/项目/其他',
  `category` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '类目：报销/差旅/采购/项目/其他',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '月份（YYYY-MM）',
  `project` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '项目',
  `apply_date` date NULL DEFAULT NULL COMMENT '申请日期',
  `unit_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '单位名称',
  `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '发票号码',
  `verified` tinyint(1) NULL DEFAULT 1 COMMENT '查验（1=已查验）',
  `payment_date` date NULL DEFAULT NULL COMMENT '打款日期',
  `expenditure_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '支出金额',
  `project_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '项目名称',
  `summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '摘要',
  `invoice_received` tinyint(1) NULL DEFAULT 0 COMMENT '收到发票（1=已收到）',
  `invoice_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '发票类型',
  `invoice_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '开票金额',
  `tax_rate` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '税率',
  `deduction_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '扣除额',
  `amount_excluding_tax` decimal(15, 2) NULL DEFAULT NULL COMMENT '不含税金额',
  `tax_amount` decimal(15, 2) NULL DEFAULT NULL COMMENT '税金',
  `is_consistent` tinyint(1) NULL DEFAULT 0 COMMENT '是否一致（1=一致）',
  `status_checked` tinyint(1) NULL DEFAULT 1 COMMENT '状态（1=已确认）',
  `selected_month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '勾选月份（YYYY-MM）',
  `reimburser` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '报销人',
  `invoice_date` date NULL DEFAULT NULL COMMENT '开票日期',
  `accounted` tinyint(1) NULL DEFAULT 1 COMMENT '入账（1=已入账）',
  `company` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '公司',
  `amount` decimal(15, 2) NOT NULL COMMENT '付款金额',
  `approved_at` datetime(0) NULL DEFAULT NULL COMMENT '审批通过时间',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_payment_request_id`(`payment_request_id`) USING BTREE,
  INDEX `idx_payment_type`(`payment_type`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_month`(`month`) USING BTREE,
  INDEX `idx_approved_at`(`approved_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '出款汇总表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for payments
-- ----------------------------
DROP TABLE IF EXISTS `payments`;
CREATE TABLE `payments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `type` enum('salary','social_security','commercial_insurance','reimbursement') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10, 2) NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `payment_date` date NULL DEFAULT NULL,
  `status` enum('draft','pending','approved','paid','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'draft',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `attachments` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `applicant_id` bigint(0) NULL DEFAULT NULL,
  `submitted_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `project_id`(`project_id`) USING BTREE,
  INDEX `idx_payments_status`(`status`) USING BTREE,
  INDEX `idx_payments_account_set_id`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for payroll_remarks
-- ----------------------------
DROP TABLE IF EXISTS `payroll_remarks`;
CREATE TABLE `payroll_remarks`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称',
  `year` int(0) NOT NULL COMMENT '年份',
  `month` int(0) NOT NULL COMMENT '月份',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '工资表备注',
  `created_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '创建人',
  `updated_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '更新人',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_payroll_remark`(`account_set_id`, `project_name`, `year`, `month`) USING BTREE,
  INDEX `idx_payroll_remark`(`account_set_id`, `project_name`, `year`, `month`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for personal_access_tokens
-- ----------------------------
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint(0) UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `last_used_at` timestamp(0) NULL DEFAULT NULL,
  `expires_at` timestamp(0) NULL DEFAULT NULL,
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `personal_access_tokens_token_unique`(`token`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 335 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for personnel_change_request_attachments
-- ----------------------------
DROP TABLE IF EXISTS `personnel_change_request_attachments`;
CREATE TABLE `personnel_change_request_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_change_request_id` bigint(0) UNSIGNED NOT NULL COMMENT '人员变动申请ID',
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名',
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件类型',
  `file_size` bigint(0) NULL DEFAULT NULL COMMENT '文件大小（字节）',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `personnel_change_request_attachments_request_id_foreign`(`personnel_change_request_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '人员变动申请附件表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for personnel_change_requests
-- ----------------------------
DROP TABLE IF EXISTS `personnel_change_requests`;
CREATE TABLE `personnel_change_requests`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `month` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '工资期间（YYYY-MM）',
  `change_type` enum('add','remove') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '变动类型：add新增，remove减少',
  `personnel_list` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '人员列表 JSON格式',
  `remark` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：pending待审批, in_approval审批中, approved已通过, rejected已拒绝',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人',
  `approval_flow_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批流程ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  `deleted_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `personnel_change_requests_account_set_id_foreign`(`account_set_id`) USING BTREE,
  INDEX `personnel_change_requests_project_id_foreign`(`project_id`) USING BTREE,
  INDEX `personnel_change_requests_created_by_foreign`(`created_by`) USING BTREE,
  INDEX `personnel_change_requests_approval_flow_id_foreign`(`approval_flow_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '人员变动申请表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for process_approvals
-- ----------------------------
DROP TABLE IF EXISTS `process_approvals`;
CREATE TABLE `process_approvals`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `approval_instance_id` int(0) UNSIGNED NULL DEFAULT NULL COMMENT '关联的审批实例ID',
  `initiator_id` bigint(0) UNSIGNED NOT NULL COMMENT '发起人ID',
  `title` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '流程标题',
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '审批月份 (YYYY-MM)',
  `project_ids` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '关联项目IDs (JSON数组)',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '流程描述',
  `status` enum('draft','pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT '流程状态: draft-草稿, pending-待审批, approved-已通过, rejected-已驳回',
  `current_approver_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '当前审批人ID',
  `approved_at` datetime(0) NULL DEFAULT NULL COMMENT '审批通过时间',
  `rejected_at` datetime(0) NULL DEFAULT NULL COMMENT '审批驳回时间',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  `deleted_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `process_approvals_account_set_id_foreign`(`account_set_id`) USING BTREE,
  INDEX `process_approvals_initiator_id_foreign`(`initiator_id`) USING BTREE,
  INDEX `process_approvals_current_approver_id_foreign`(`current_approver_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_month`(`month`) USING BTREE,
  INDEX `idx_approval_instance_id`(`approval_instance_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '流程审批表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for process_attachments
-- ----------------------------
DROP TABLE IF EXISTS `process_attachments`;
CREATE TABLE `process_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `process_approval_id` bigint(0) UNSIGNED NOT NULL COMMENT '流程审批ID',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名',
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件存储路径',
  `file_size` bigint(0) NOT NULL COMMENT '文件大小 (bytes)',
  `mime_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT 'MIME类型',
  `uploaded_by` bigint(0) UNSIGNED NOT NULL COMMENT '上传人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `process_attachments_process_approval_id_foreign`(`process_approval_id`) USING BTREE,
  INDEX `process_attachments_uploaded_by_foreign`(`uploaded_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '流程附件表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for project_delivery_configs
-- ----------------------------
DROP TABLE IF EXISTS `project_delivery_configs`;
CREATE TABLE `project_delivery_configs`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `delivery_cycle` enum('monthly','quarterly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'monthly' COMMENT '交付周期：monthly-按月，quarterly-按季度',
  `delivery_method` enum('express','electronic') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'electronic' COMMENT '交付方式：express-快递，electronic-电子推送',
  `required_documents` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '需交付的资料清单（JSON格式存储）',
  `is_active` tinyint(1) NULL DEFAULT 1 COMMENT '是否启用：1-启用，0-禁用',
  `created_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '创建人ID',
  `updated_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '更新人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_project`(`project_id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_delivery_cycle`(`delivery_cycle`) USING BTREE,
  INDEX `idx_is_active`(`is_active`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '项目资料交付配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for project_document_configs
-- ----------------------------
DROP TABLE IF EXISTS `project_document_configs`;
CREATE TABLE `project_document_configs`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `document_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '资料名称（如：身份证照片、驾驶证等）',
  `document_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all' COMMENT '文件类型限制(image/pdf/document/all)',
  `is_required` tinyint(1) NULL DEFAULT 1 COMMENT '是否必填：1=必填，0=选填',
  `sort_order` int(0) NULL DEFAULT 0 COMMENT '排序',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_sort_order`(`sort_order`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '项目资料配置表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for project_large_medical_insurance
-- ----------------------------
DROP TABLE IF EXISTS `project_large_medical_insurance`;
CREATE TABLE `project_large_medical_insurance`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `config_id` bigint(0) UNSIGNED NOT NULL COMMENT '大额医疗保险配置ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '所属账套ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_project_config`(`project_id`, `config_id`) USING BTREE,
  INDEX `idx_project`(`project_id`) USING BTREE,
  INDEX `idx_config`(`config_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '项目-大额医疗保险关联表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for project_medical_insurance
-- ----------------------------
DROP TABLE IF EXISTS `project_medical_insurance`;
CREATE TABLE `project_medical_insurance`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `region_id` bigint(0) UNSIGNED NOT NULL COMMENT '医保地区ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '所属账套ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_project_region`(`project_id`, `region_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_region_id`(`region_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 3 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '项目医保关联表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for project_other_insurance_policies
-- ----------------------------
DROP TABLE IF EXISTS `project_other_insurance_policies`;
CREATE TABLE `project_other_insurance_policies`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `policy_id` bigint(0) UNSIGNED NOT NULL COMMENT '保单ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '所属账套ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_project_policy`(`project_id`, `policy_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_policy_id`(`policy_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 16 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '项目其他保险保单关联表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for projects
-- ----------------------------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `salary_payment_month` enum('current','next') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'current' COMMENT '工资发放月份：current-本月，next-次月',
  `insurance_import_month` enum('current','next','none') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'current' COMMENT '保险导入设置：current-当月，next-次月，none-不导入',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `code` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive','completed') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'active',
  `require_attendance` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否需要考勤：1-需要，0-不需要',
  `contract_notice_file_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '劳动合同须知文件ID（单个文件）',
  `social_security_regions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '社保地区ID列表，JSON格式',
  `housing_fund_regions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '公积金地区ID列表，JSON格式',
  `contract_notice_files` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '劳动合同须知文件ID列表，逗号分隔格式: 1,2,3',
  `social_security_location` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '参保地',
  `insurance_types` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT '参保项目',
  `salary_payment_date` int(0) NULL DEFAULT NULL COMMENT '工资发放日期（每月几号，1-31）',
  `requires_attendance` tinyint(1) NULL DEFAULT 1 COMMENT '是否需要考勤表',
  `requires_salary_basis` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要上传工资依据',
  `requires_attendance_basis` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要上传考勤依据',
  `delivery_requirements` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL COMMENT '交付资料要求',
  `delivery_frequency` enum('monthly','quarterly') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'monthly' COMMENT '交付频率',
  `delivery_method` enum('express','electronic') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'electronic' COMMENT '交付方式',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `labor_contract_notice_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '劳动合同须知文件名称',
  `labor_contract_notice_file` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '劳动合同须知文件路径',
  `medical_insurance_regions` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '医保地区ID列表',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_created_at`(`created_at`) USING BTREE,
  INDEX `idx_projects_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_notice_file`(`contract_notice_file_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for recruitment
-- ----------------------------
DROP TABLE IF EXISTS `recruitment`;
CREATE TABLE `recruitment`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `required_count` int(0) NOT NULL,
  `requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `salary_min` decimal(10, 2) NULL DEFAULT NULL,
  `salary_max` decimal(10, 2) NULL DEFAULT NULL,
  `status` enum('pending','active','completed','paused','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：pending-待分配, active-进行中, completed-已完成, paused-已暂停, cancelled-已取消',
  `assigned_to` bigint(0) UNSIGNED NULL DEFAULT NULL,
  `progress_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `candidates` varchar(0) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `hired_count` int(0) NOT NULL DEFAULT 0,
  `completion_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '完成情况说明',
  `candidates_summary` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '候选人信息汇总',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注说明',
  `deadline` date NULL DEFAULT NULL,
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  `department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '部门',
  `salary_range` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '薪资范围',
  `work_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '工作地点',
  `education` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '学历要求',
  `experience` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '工作经验',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '职位描述',
  `start_date` date NULL DEFAULT NULL COMMENT '开始日期',
  `end_date` date NULL DEFAULT NULL COMMENT '结束日期',
  `assigned_at` datetime(0) NULL DEFAULT NULL,
  `applied_count` int(0) NOT NULL DEFAULT 0 COMMENT '申请人数',
  `interviewed_count` int(0) NOT NULL DEFAULT 0 COMMENT '面试人数',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `recruitment_project_id_foreign`(`project_id`) USING BTREE,
  INDEX `recruitment_assigned_to_foreign`(`assigned_to`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for recruitment_candidates
-- ----------------------------
DROP TABLE IF EXISTS `recruitment_candidates`;
CREATE TABLE `recruitment_candidates`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `recruitment_id` bigint(0) UNSIGNED NOT NULL COMMENT '招聘需求ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '姓名',
  `gender` enum('male','female') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '性别：male-男，female-女',
  `age` int(0) NULL DEFAULT NULL COMMENT '年龄',
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '联系电话',
  `email` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '邮箱',
  `education` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '学历：high_school, college, bachelor, master, doctor',
  `experience` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '工作经验',
  `status` enum('pending','interviewing','to_be_hired','hired','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态：pending-待面试, interviewing-面试中, to_be_hired-待录用, hired-已录用, rejected-已拒绝',
  `interview_date` datetime(0) NULL DEFAULT NULL COMMENT '面试时间',
  `interview_result` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '面试结果',
  `resume_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '简历文件URL',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0) COMMENT '更新时间',
  `created_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '创建人ID',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_recruitment`(`recruitment_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_phone`(`phone`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '招聘候选人表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for recruitment_progress_logs
-- ----------------------------
DROP TABLE IF EXISTS `recruitment_progress_logs`;
CREATE TABLE `recruitment_progress_logs`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `recruitment_id` bigint(0) UNSIGNED NOT NULL COMMENT '招聘需求ID',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `applied_count` int(0) NOT NULL DEFAULT 0 COMMENT '申请人数',
  `interviewed_count` int(0) NOT NULL DEFAULT 0 COMMENT '面试人数',
  `hired_count` int(0) NOT NULL DEFAULT 0 COMMENT '录用人数',
  `progress_notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '进度说明',
  `updated_by` bigint(0) UNSIGNED NOT NULL COMMENT '更新人ID',
  `updated_by_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '更新人姓名',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_recruitment`(`recruitment_id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '招聘进度记录表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for recruitments
-- ----------------------------
DROP TABLE IF EXISTS `recruitments`;
CREATE TABLE `recruitments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `position` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `recruitment_count` int(0) NOT NULL DEFAULT 1,
  `applied_count` int(0) NULL DEFAULT 0,
  `interviewed_count` int(0) NULL DEFAULT 0,
  `hired_count` int(0) NULL DEFAULT 0,
  `salary_range` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `work_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `education` enum('high_school','college','bachelor','master','doctor') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `experience` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `requirements` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `start_date` date NULL DEFAULT NULL,
  `end_date` date NULL DEFAULT NULL,
  `status` enum('active','completed','paused','cancelled') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'active',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `required_count` bigint(0) NULL DEFAULT NULL,
  `salary_min` decimal(10, 0) NULL DEFAULT NULL,
  `salary_max` decimal(10, 0) NULL DEFAULT NULL,
  `deadline` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `assigned_to` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `progress_notes` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `candidates` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `project_id`(`project_id`) USING BTREE,
  INDEX `idx_recruitment_status`(`status`) USING BTREE,
  INDEX `idx_recruitments_account_set_id`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for region_portals
-- ----------------------------
DROP TABLE IF EXISTS `region_portals`;
CREATE TABLE `region_portals`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `region_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `business_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '业务类型（如：社保、公积金、税务等）',
  `portal_name` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '网站名称',
  `portal_url` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '网站地址',
  `login_account` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '登录账号（可选）',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注说明',
  `sort_order` int(0) NULL DEFAULT 0 COMMENT '排序序号',
  `is_active` tinyint(1) NULL DEFAULT 1 COMMENT '是否启用：1-启用，0-禁用',
  `created_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '创建人ID',
  `updated_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '更新人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_region_name`(`region_name`) USING BTREE,
  INDEX `idx_business_type`(`business_type`) USING BTREE,
  INDEX `idx_is_active`(`is_active`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 2 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '地区网页入口管理表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for reimbursement_attachments
-- ----------------------------
DROP TABLE IF EXISTS `reimbursement_attachments`;
CREATE TABLE `reimbursement_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `reimbursement_id` bigint(0) UNSIGNED NOT NULL COMMENT '报销申请ID',
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件名',
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件类型',
  `file_size` bigint(0) NULL DEFAULT NULL COMMENT '文件大小(字节)',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_reimbursement_id`(`reimbursement_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 35 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '报销附件表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for reimbursements
-- ----------------------------
DROP TABLE IF EXISTS `reimbursements`;
CREATE TABLE `reimbursements`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `company_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '单位名称',
  `invoice_number` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '发票号码',
  `payment_date` date NULL DEFAULT NULL COMMENT '打款日期',
  `applicant` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '报销人',
  `amount` decimal(10, 2) NOT NULL COMMENT '报销金额',
  `category` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '类目',
  `project` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '项目',
  `received_invoice` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '收到发票(是/否)',
  `invoice_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '发票类型',
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '报销事由',
  `invoice_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '开票金额',
  `tax_rate` decimal(5, 2) NULL DEFAULT NULL COMMENT '税率(%)',
  `tax_deduction` decimal(10, 2) NULL DEFAULT NULL COMMENT '扣税额',
  `amount_excluding_tax` decimal(10, 2) NULL DEFAULT NULL COMMENT '不含税金额',
  `tax_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '税金',
  `invoice_date` date NULL DEFAULT NULL COMMENT '开票日期',
  `record_status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '状态(是/否)',
  `accounting_status` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '入账(是/否)',
  `verification` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '查验(是/否)',
  `is_consistent` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '是否一致(是/否)',
  `check_date` date NULL DEFAULT NULL COMMENT '勾选日期',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `status` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '状态: pending-待审批, approved-已通过, rejected-已拒绝',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `approval_flow_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批流程ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  `deleted_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_created_at`(`created_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 20 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '报销申请表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for salaries
-- ----------------------------
DROP TABLE IF EXISTS `salaries`;
CREATE TABLE `salaries`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `seq_number` int(0) NULL DEFAULT NULL COMMENT '序号',
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `employee_id` bigint(0) UNSIGNED NOT NULL,
  `id_card` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '身份证号',
  `employee_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '员工姓名',
  `department` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '所在部门',
  `position` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '岗位',
  `insurance_import_setting` enum('current','next','none') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'current' COMMENT '保险导入设置（生成时的设置）：current-当月，next-次月，none-不导入',
  `salary_approval_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '工资表审批ID',
  `project_id` bigint(0) UNSIGNED NOT NULL,
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '月份 YYYY-MM',
  `period_start` int(0) NULL DEFAULT NULL COMMENT '工资周期开始日期',
  `period_end` int(0) NULL DEFAULT NULL COMMENT '工资周期结束日期',
  `work_days` int(0) NULL DEFAULT 0 COMMENT '应出勤天数',
  `actual_work_days` decimal(5, 1) NULL DEFAULT 0.0 COMMENT '实际出勤天数',
  `absent_days` decimal(5, 1) NULL DEFAULT 0.0 COMMENT '缺勤天数',
  `absent_deduction` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '缺勤扣款',
  `basic_salary` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '基本工资',
  `allowance` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '津贴',
  `overtime_pay` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '加班费',
  `bonus` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '奖金',
  `gross_salary` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '应发工资',
  `cumulative_income` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '累计收入（今年1月至当前月应发工资总和）',
  `cumulative_basic_deduction` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '累计减除费用（5000×月数）',
  `cumulative_special_deduction_insurance` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '累计专项扣除（社保公积金个人部分）',
  `cumulative_taxable_income` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '累计应纳税所得额',
  `tax_rate` decimal(5, 2) NULL DEFAULT 0.00 COMMENT '税率(%)',
  `quick_deduction` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '速算扣除数',
  `cumulative_tax_payable` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '累计应扣缴税额（累计应纳税所得额×税率-速算扣除数）',
  `tax_already_withheld` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '已扣缴税额（1月到上月的累计应扣缴税额之和）',
  `social_security` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '社保个人部分',
  `housing_fund` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '公积金个人部分',
  `special_deduction` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '专项扣除',
  `special_deduction_monthly` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '当月专项附加扣除（6项扣除合计）',
  `taxable_income` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '应纳税所得额',
  `cumulative_other_taxable` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '累计其他应纳税项（合并扣税）',
  `tax_payable_or_refundable` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '应补（退）税额',
  `employee_signature` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '本人签字',
  `personal_tax` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '个人所得税',
  `actual_tax` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '实际个税',
  `deductions` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '扣款',
  `net_salary` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '实发工资',
  `paid_salary` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '实际发放金额',
  `status` enum('draft','submitted','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'draft',
  `submitted_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '提交人',
  `approved_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批人',
  `submitted_at` timestamp(0) NULL DEFAULT NULL COMMENT '提交时间',
  `approved_at` timestamp(0) NULL DEFAULT NULL COMMENT '审批时间',
  `paid_at` timestamp(0) NULL DEFAULT NULL COMMENT '发放时间',
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '拒绝原因',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `company_insurance_total` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '单位保险合计',
  `personal_insurance_total` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '个人保险合计',
  `compensation_total` decimal(10, 2) NULL DEFAULT 0.00 COMMENT '补差合计',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `employee_id`(`employee_id`) USING BTREE,
  INDEX `project_id`(`project_id`) USING BTREE,
  INDEX `idx_salaries_month`(`month`) USING BTREE,
  INDEX `idx_employee_id`(`employee_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_month`(`month`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_salaries_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_salary_approval_id`(`salary_approval_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 80 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for salary_approval_attachments
-- ----------------------------
DROP TABLE IF EXISTS `salary_approval_attachments`;
CREATE TABLE `salary_approval_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `salary_approval_id` bigint(0) UNSIGNED NOT NULL COMMENT '工资表审批ID',
  `filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '文件路径',
  `file_size` bigint(0) UNSIGNED NOT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '文件类型',
  `uploaded_by` bigint(0) UNSIGNED NOT NULL COMMENT '上传人ID',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_salary_approval_id`(`salary_approval_id`) USING BTREE,
  INDEX `idx_uploaded_by`(`uploaded_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 14 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '工资表审批附件' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for salary_approvals
-- ----------------------------
DROP TABLE IF EXISTS `salary_approvals`;
CREATE TABLE `salary_approvals`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `approval_instance_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批流程实例ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '工资期间（YYYY-MM）',
  `approval_type` enum('online','offline') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'online' COMMENT '审批方式：线上/线下',
  `status` enum('pending','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT '审批状态',
  `submitted_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '提交人ID',
  `submitted_at` datetime(0) NULL DEFAULT NULL COMMENT '提交时间',
  `approved_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '审批人ID',
  `approved_at` datetime(0) NULL DEFAULT NULL COMMENT '审批时间',
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '拒绝原因',
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `unique_project_month`(`project_id`, `month`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_month`(`month`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_approval_instance_id`(`approval_instance_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 17 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '工资表审批记录' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for salary_sheets
-- ----------------------------
DROP TABLE IF EXISTS `salary_sheets`;
CREATE TABLE `salary_sheets`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `month` varchar(7) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '月份 (YYYY-MM)',
  `attendance_sheet_id` bigint(0) UNSIGNED NOT NULL COMMENT '考勤表ID',
  `total_employees` int(0) NOT NULL DEFAULT 0 COMMENT '员工总数',
  `total_amount` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '总金额',
  `status` enum('draft','submitted','approved','rejected') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft' COMMENT '状态',
  `notes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '备注',
  `created_by` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '创建人ID',
  `submitted_at` timestamp(0) NULL DEFAULT NULL COMMENT '提交时间',
  `approved_at` timestamp(0) NULL DEFAULT NULL COMMENT '审批时间',
  `rejected_at` timestamp(0) NULL DEFAULT NULL COMMENT '拒绝时间',
  `rejection_reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '拒绝原因',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_project_id`(`project_id`) USING BTREE,
  INDEX `idx_month`(`month`) USING BTREE,
  INDEX `idx_attendance_sheet_id`(`attendance_sheet_id`) USING BTREE,
  INDEX `idx_status`(`status`) USING BTREE,
  INDEX `idx_created_by`(`created_by`) USING BTREE,
  INDEX `idx_project_month`(`project_id`, `month`) USING BTREE,
  INDEX `idx_status_created`(`status`, `created_at`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '工资表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for salary_summaries
-- ----------------------------
DROP TABLE IF EXISTS `salary_summaries`;
CREATE TABLE `salary_summaries`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(0) UNSIGNED NOT NULL COMMENT '项目ID',
  `project_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '项目名称',
  `month` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '工资期间（YYYY-MM）',
  `period_start` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '工资周期开始',
  `period_end` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '工资周期结束',
  `employee_count` int(0) NOT NULL DEFAULT 0 COMMENT '员工人数',
  `insurance_import_setting` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '保险导入设置（current/next/none）',
  `social_security_location` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '参保地',
  `salary_payment_day` int(0) NULL DEFAULT NULL COMMENT '工资发放日（几号）',
  `requires_salary_basis` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要上传工资依据',
  `salary_basis_uploaded` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已上传工资依据',
  `total_work_days` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '应出勤天数合计',
  `total_actual_work_days` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '实际出勤天数合计',
  `total_absent_days` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '缺勤天数合计',
  `total_absent_deduction` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '缺勤扣款合计',
  `total_basic_salary` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '基本工资合计',
  `total_gross_salary` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '应发工资合计',
  `total_cumulative_income` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '累计收入合计',
  `total_cumulative_basic_deduction` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '累计减除费用合计',
  `total_cumulative_special_deduction_insurance` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '累计专项扣除合计',
  `avg_tax_rate` decimal(10, 2) NULL DEFAULT NULL COMMENT '平均税率',
  `avg_quick_deduction` decimal(12, 2) NULL DEFAULT NULL COMMENT '平均速算扣除数',
  `total_cumulative_tax_payable` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '累计应扣缴税额合计',
  `total_tax_already_withheld` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '已扣缴税额合计',
  `total_pension_company` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '养老保险单位合计',
  `total_medical_company` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '医疗保险单位合计',
  `total_unemployment_company` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '失业保险单位合计',
  `total_work_injury_company` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '工伤保险单位合计',
  `total_maternity_company` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '生育保险单位合计',
  `total_pension_personal` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '养老保险个人合计',
  `total_medical_personal` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '医疗保险个人合计',
  `total_unemployment_personal` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '失业保险个人合计',
  `total_housing_fund_company` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '公积金单位合计',
  `total_housing_fund_personal` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '公积金个人合计',
  `total_large_medical_company` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '大额医疗单位合计',
  `total_large_medical_personal` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '大额医疗个人合计（不计入个人合计）',
  `total_social_security_compensation` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '社保补差合计',
  `total_housing_fund_compensation` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '公积金补差合计',
  `total_company_insurance_total` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '单位保险合计',
  `total_personal_insurance_total` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '个人保险合计',
  `total_special_deduction_monthly` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '当月专项附加扣除合计',
  `total_special_deduction` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '累计专项附加扣除合计',
  `total_taxable_income` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '应纳税所得额合计',
  `total_cumulative_other_taxable` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '累计其他应纳税项合计',
  `total_tax_payable_or_refundable` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '应补退税额合计',
  `total_deductions` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '其他扣款合计',
  `total_net_salary` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '实发工资合计',
  `total_paid_salary` decimal(12, 2) NOT NULL DEFAULT 0.00 COMMENT '已发放工资合计',
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'approved' COMMENT '状态',
  `salary_approval_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '工资表审批ID',
  `approved_at` timestamp(0) NULL DEFAULT NULL COMMENT '审批时间',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `salary_summaries_account_set_id_foreign`(`account_set_id`) USING BTREE,
  INDEX `salary_summaries_project_id_foreign`(`project_id`) USING BTREE,
  INDEX `salary_summaries_salary_approval_id_foreign`(`salary_approval_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '工资汇总表（完整版）' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for shared_files
-- ----------------------------
DROP TABLE IF EXISTS `shared_files`;
CREATE TABLE `shared_files`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '所属账套ID',
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `path` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_category` enum('shared','notice') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shared' COMMENT '文件分类：shared=共享文件, notice=须知文件',
  `notice_type` enum('signing_notice','employee_handbook','regulations','confidentiality') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '须知类型：signing_notice=签订须知, employee_handbook=员工手册, regulations=规章制度, confidentiality=保密协议',
  `size` bigint(0) NOT NULL,
  `uploader_id` bigint(0) UNSIGNED NOT NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `uploader_id`(`uploader_id`) USING BTREE,
  INDEX `idx_shared_files_account_set_id`(`account_set_id`) USING BTREE,
  INDEX `idx_file_category`(`file_category`) USING BTREE,
  INDEX `idx_notice_type`(`notice_type`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 18 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for social_security_regions
-- ----------------------------
DROP TABLE IF EXISTS `social_security_regions`;
CREATE TABLE `social_security_regions`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地区名称',
  `code` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '社保编号',
  `min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '最低基数',
  `max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '最高基数',
  `old_min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '旧最低基数',
  `old_max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '旧最高基数',
  `old_limits_updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '旧上下限修改时间',
  `new_min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '新最低基数',
  `new_max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '新最高基数',
  `new_limits_updated_at` timestamp(0) NULL DEFAULT NULL COMMENT '新上下限修改时间',
  `account_set_id` bigint(0) UNSIGNED NOT NULL COMMENT '账套ID',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  `adjustment_base` decimal(10, 2) NULL DEFAULT NULL COMMENT '调整基数（预设值）',
  `effective_date` date NULL DEFAULT NULL COMMENT '生效时间',
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `social_security_regions_account_set_id_foreign`(`account_set_id`) USING BTREE,
  INDEX `social_security_regions_created_by_foreign`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 4 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '社保地区表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for social_security_types
-- ----------------------------
DROP TABLE IF EXISTS `social_security_types`;
CREATE TABLE `social_security_types`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `region_id` bigint(0) UNSIGNED NOT NULL COMMENT '地区ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '保险名称',
  `base_amount` decimal(10, 2) NOT NULL DEFAULT 0.00 COMMENT '基数',
  `min_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '下限基数',
  `max_base_amount` decimal(10, 2) NULL DEFAULT NULL COMMENT '上限基数',
  `employee_ratio` decimal(5, 4) NOT NULL DEFAULT 0.0000 COMMENT '员工缴纳比例',
  `company_ratio` decimal(5, 4) NOT NULL DEFAULT 0.0000 COMMENT '公司缴纳比例',
  `created_by` bigint(0) UNSIGNED NOT NULL COMMENT '创建人ID',
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `social_security_types_region_id_foreign`(`region_id`) USING BTREE,
  INDEX `social_security_types_created_by_foreign`(`created_by`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 32 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '社保细分种类表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for special_deduction_items
-- ----------------------------
DROP TABLE IF EXISTS `special_deduction_items`;
CREATE TABLE `special_deduction_items`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '账套ID',
  `project_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '项目ID',
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '专项扣除名称',
  `amount` decimal(10, 2) NOT NULL COMMENT '扣除金额',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '说明描述',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `sort_order` int(0) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set`(`account_set_id`) USING BTREE,
  INDEX `idx_project`(`project_id`) USING BTREE,
  INDEX `idx_active`(`is_active`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '专项扣除项目表' ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for system_settings
-- ----------------------------
DROP TABLE IF EXISTS `system_settings`;
CREATE TABLE `system_settings`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '账套ID',
  `key` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for travel_application_attachments
-- ----------------------------
DROP TABLE IF EXISTS `travel_application_attachments`;
CREATE TABLE `travel_application_attachments`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `travel_application_id` bigint(0) UNSIGNED NOT NULL,
  `file_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_type` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `file_size` bigint(0) NULL DEFAULT NULL,
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `travel_application_attachments_travel_application_id_foreign`(`travel_application_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for travel_applications
-- ----------------------------
DROP TABLE IF EXISTS `travel_applications`;
CREATE TABLE `travel_applications`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(0) UNSIGNED NOT NULL,
  `department` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `apply_date` date NULL DEFAULT NULL,
  `applicant` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `reason` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_time` datetime(0) NOT NULL,
  `end_time` datetime(0) NOT NULL,
  `days` int(0) NOT NULL DEFAULT 0,
  `advance_amount` decimal(10, 2) NOT NULL DEFAULT 0.00,
  `payment_date` date NULL DEFAULT NULL,
  `remarks` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL,
  `status` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_by` bigint(0) UNSIGNED NOT NULL,
  `approval_flow_id` bigint(0) UNSIGNED NULL DEFAULT NULL,
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  `deleted_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `travel_applications_account_set_id_foreign`(`account_set_id`) USING BTREE,
  INDEX `travel_applications_created_by_foreign`(`created_by`) USING BTREE,
  INDEX `travel_applications_approval_flow_id_foreign`(`approval_flow_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for user_seals
-- ----------------------------
DROP TABLE IF EXISTS `user_seals`;
CREATE TABLE `user_seals`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(0) UNSIGNED NOT NULL,
  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '印章名称',
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '印章图片路径',
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '原始文件名',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认印章',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `user_seals_user_id_index`(`user_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for user_signatures
-- ----------------------------
DROP TABLE IF EXISTS `user_signatures`;
CREATE TABLE `user_signatures`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` bigint(0) UNSIGNED NOT NULL,
  `image_path` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '签名图片路径',
  `original_filename` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '原始文件名',
  `created_at` timestamp(0) NULL DEFAULT NULL,
  `updated_at` timestamp(0) NULL DEFAULT NULL,
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE INDEX `user_signatures_user_id_unique`(`user_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for users
-- ----------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users`  (
  `id` bigint(0) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '昵称/显示名称',
  `email` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号',
  `avatar` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '头像URL',
  `email_verified_at` timestamp(0) NULL DEFAULT NULL,
  `password` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` enum('admin','hr','manager','employee') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT 'employee',
  `account_set_id` bigint(0) UNSIGNED NULL DEFAULT NULL COMMENT '账套ID',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用',
  `remember_token` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `created_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0),
  `updated_at` timestamp(0) NULL DEFAULT CURRENT_TIMESTAMP(0) ON UPDATE CURRENT_TIMESTAMP(0),
  PRIMARY KEY (`id`) USING BTREE,
  INDEX `idx_account_set_id`(`account_set_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- View structure for v_large_medical_payment_status
-- ----------------------------
DROP VIEW IF EXISTS `v_large_medical_payment_status`;
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_large_medical_payment_status` AS select `ip`.`employee_id` AS `employee_id`,`ip`.`employee_name` AS `employee_name`,`ip`.`large_medical_insurance_enabled` AS `large_medical_insurance_enabled`,`ip`.`large_medical_payment_start_month` AS `large_medical_payment_start_month`,`ip`.`large_medical_payment_start_year` AS `large_medical_payment_start_year`,`ip`.`large_medical_last_payment_month` AS `large_medical_last_payment_month`,`ip`.`large_medical_last_payment_year` AS `large_medical_last_payment_year`,json_unquote(json_extract(`ip`.`large_medical_insurance_config`,'$.payment_cycle')) AS `payment_cycle`,json_unquote(json_extract(`ip`.`large_medical_insurance_config`,'$.calculation_type')) AS `calculation_type`,(case when (json_unquote(json_extract(`ip`.`large_medical_insurance_config`,'$.payment_cycle')) = 'yearly') then '年付' else '月付' end) AS `payment_cycle_text`,(case when (json_unquote(json_extract(`ip`.`large_medical_insurance_config`,'$.calculation_type')) = 'fixed') then '固定金额' else '按基数' end) AS `calculation_type_text` from `insurance_personnel` `ip` where (`ip`.`large_medical_insurance_enabled` = 1);

-- ----------------------------
-- Function structure for CalculateLargeMedicalAmount
-- ----------------------------
DROP FUNCTION IF EXISTS `CalculateLargeMedicalAmount`;
delimiter ;;
CREATE FUNCTION `CalculateLargeMedicalAmount`(p_employee_id INT,
    p_year INT,
    p_month INT)
 RETURNS decimal(10,2)
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
    
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for drop_all_indexes
-- ----------------------------
DROP PROCEDURE IF EXISTS `drop_all_indexes`;
delimiter ;;
CREATE PROCEDURE `drop_all_indexes`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE tname VARCHAR(255);
    DECLARE iname VARCHAR(255);
    DECLARE itype VARCHAR(32);
    
    -- 创建游标获取所有索引
    DECLARE cur CURSOR FOR 
        SELECT TABLE_NAME, INDEX_NAME, INDEX_TYPE 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE();
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- 打开游标
    OPEN cur;
    
    -- 循环处理每个索引
    read_loop: LOOP
        FETCH cur INTO tname, iname, itype;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- 构建并执行删除索引的SQL
        SET @sql = '';
        IF iname = 'PRIMARY' THEN
            SET @sql = CONCAT('ALTER TABLE `', tname, '` DROP PRIMARY KEY');
        ELSE
            SET @sql = CONCAT('ALTER TABLE `', tname, '` DROP INDEX `', iname, '`');
        END IF;
        
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    
    -- 关闭游标
    CLOSE cur;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for drop_foreign_keys
-- ----------------------------
DROP PROCEDURE IF EXISTS `drop_foreign_keys`;
delimiter ;;
CREATE PROCEDURE `drop_foreign_keys`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE tname VARCHAR(255);
    DECLARE cname VARCHAR(255);
    
    -- 创建游标获取所有外键
    DECLARE cur CURSOR FOR 
        SELECT TABLE_NAME, CONSTRAINT_NAME 
        FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = DATABASE()
        AND CONSTRAINT_TYPE = 'FOREIGN KEY';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- 打开游标
    OPEN cur;
    
    -- 循环处理每个外键
    read_loop: LOOP
        FETCH cur INTO tname, cname;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- 构建并执行删除外键的SQL
        SET @sql = CONCAT('ALTER TABLE `', tname, '` DROP FOREIGN KEY `', cname, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    
    -- 关闭游标
    CLOSE cur;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for drop_indexes
-- ----------------------------
DROP PROCEDURE IF EXISTS `drop_indexes`;
delimiter ;;
CREATE PROCEDURE `drop_indexes`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE tname VARCHAR(255);
    DECLARE iname VARCHAR(255);
    DECLARE itype VARCHAR(32);
    
    -- 创建游标获取所有索引
    DECLARE cur CURSOR FOR 
        SELECT TABLE_NAME, INDEX_NAME, INDEX_TYPE 
        FROM INFORMATION_SCHEMA.STATISTICS 
        WHERE TABLE_SCHEMA = DATABASE();
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- 打开游标
    OPEN cur;
    
    -- 循环处理每个索引
    read_loop: LOOP
        FETCH cur INTO tname, iname, itype;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        -- 构建并执行删除索引的SQL
        SET @sql = '';
        IF iname = 'PRIMARY' THEN
            SET @sql = CONCAT('ALTER TABLE `', tname, '` DROP PRIMARY KEY');
        ELSE
            SET @sql = CONCAT('ALTER TABLE `', tname, '` DROP INDEX `', iname, '`');
        END IF;
        
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    
    -- 关闭游标
    CLOSE cur;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for execute_drop_fk
-- ----------------------------
DROP PROCEDURE IF EXISTS `execute_drop_fk`;
delimiter ;;
CREATE PROCEDURE `execute_drop_fk`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE sql_text TEXT;
    DECLARE cur CURSOR FOR SELECT sql_text FROM temp_drop_fk;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO sql_text;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET @sql = sql_text;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    
    CLOSE cur;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for execute_drop_indexes
-- ----------------------------
DROP PROCEDURE IF EXISTS `execute_drop_indexes`;
delimiter ;;
CREATE PROCEDURE `execute_drop_indexes`()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE sql_text TEXT;
    DECLARE cur CURSOR FOR SELECT sql_text FROM temp_drop_indexes;
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    
    read_loop: LOOP
        FETCH cur INTO sql_text;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET @sql = sql_text;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END LOOP;
    
    CLOSE cur;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for IsLargeMedicalPaymentMonth
-- ----------------------------
DROP PROCEDURE IF EXISTS `IsLargeMedicalPaymentMonth`;
delimiter ;;
CREATE PROCEDURE `IsLargeMedicalPaymentMonth`(IN p_employee_id INT,
    IN p_year INT,
    IN p_month INT,
    OUT p_is_payment_month BOOLEAN)
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
    
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for safe_modify_column
-- ----------------------------
DROP PROCEDURE IF EXISTS `safe_modify_column`;
delimiter ;;
CREATE PROCEDURE `safe_modify_column`(IN p_db_name VARCHAR(100), 
    IN p_table_name VARCHAR(100), 
    IN p_column_name VARCHAR(100), 
    IN p_new_definition TEXT, 
    IN p_after_column VARCHAR(100))
BEGIN
    DECLARE v_column_exists INT;
    DECLARE v_table_exists INT;
    
    -- 检查表是否存在
    SELECT COUNT(*) INTO v_table_exists 
    FROM INFORMATION_SCHEMA.TABLES 
    WHERE TABLE_SCHEMA = p_db_name 
    AND TABLE_NAME = p_table_name;
    
    IF v_table_exists > 0 THEN
        -- 检查字段是否存在
        SELECT COUNT(*) INTO v_column_exists 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = p_db_name 
          AND TABLE_NAME = p_table_name 
          AND COLUMN_NAME = p_column_name;
        
        -- 如果字段存在，则修改它
        IF v_column_exists > 0 THEN
            SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` MODIFY COLUMN `', p_column_name, '` ', p_new_definition);
            
            -- 添加AFTER子句（如果指定）
            IF p_after_column IS NOT NULL AND p_after_column != '' THEN
                SET @sql = CONCAT(@sql, ' AFTER `', p_after_column, '`');
            END IF;
            
            SET @sql = CONCAT(@sql, ';');
            
            PREPARE stmt FROM @sql;
            EXECUTE stmt;
            DEALLOCATE PREPARE stmt;
            
            SELECT CONCAT('✓ 已修复: ', p_table_name, '.', p_column_name) AS message;
        ELSE
            SELECT CONCAT('ℹ️  跳过: ', p_table_name, '.', p_column_name, ' (字段不存在)') AS message;
        END IF;
    ELSE
        SELECT CONCAT('ℹ️  跳过: 表 ', p_table_name, ' 不存在') AS message;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table insurance_personnel
-- ----------------------------
DROP TRIGGER IF EXISTS `tr_insurance_personnel_update_payment_history`;
delimiter ;;
CREATE TRIGGER `tr_insurance_personnel_update_payment_history` AFTER UPDATE ON `insurance_personnel` FOR EACH ROW BEGIN
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
END
;;
delimiter ;

SET FOREIGN_KEY_CHECKS = 1;
