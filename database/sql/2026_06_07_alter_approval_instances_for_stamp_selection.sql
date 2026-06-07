-- 审批发起时选择公司印章：approval_instances 新增字段（MySQL 5.6 兼容）
-- 请在业务低峰期执行

ALTER TABLE `approval_instances`
  ADD COLUMN `stamp_selection_mode` varchar(20) DEFAULT NULL COMMENT '印章选择模式(stamp/none)' AFTER `stamp_method`,
  ADD COLUMN `stamp_company` varchar(100) DEFAULT NULL COMMENT '发起审批选择的用章公司' AFTER `stamp_selection_mode`,
  ADD COLUMN `stamp_type` varchar(50) DEFAULT NULL COMMENT '发起审批选择的印章类型' AFTER `stamp_company`,
  ADD COLUMN `stamp_id` bigint unsigned DEFAULT NULL COMMENT '发起审批选择的公司印章ID' AFTER `stamp_type`;
