-- ================================================================
-- 发票管理模块 - 添加任务名称字段
-- 执行时间：2025-11-02
-- ================================================================

-- 在 invoice_applications 表中添加 task_name 字段
ALTER TABLE `invoice_applications` 
ADD COLUMN `task_name` VARCHAR(100) NULL COMMENT '任务名称/描述' 
AFTER `application_no`;

-- 验证字段是否添加成功
SELECT 
    COLUMN_NAME as '字段名',
    COLUMN_TYPE as '类型',
    COLUMN_COMMENT as '注释'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'invoice_applications' 
AND COLUMN_NAME = 'task_name';
