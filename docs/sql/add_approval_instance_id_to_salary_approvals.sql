-- 给工资表审批添加审批流程实例ID字段
ALTER TABLE `salary_approvals` 
ADD COLUMN `approval_instance_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '审批流程实例ID' AFTER `account_set_id`,
ADD INDEX `idx_approval_instance_id` (`approval_instance_id`);

-- 添加外键约束（可选）
-- ALTER TABLE `salary_approvals` 
-- ADD CONSTRAINT `fk_salary_approvals_approval_instance` 
-- FOREIGN KEY (`approval_instance_id`) REFERENCES `approval_instances` (`id`) ON DELETE SET NULL;

