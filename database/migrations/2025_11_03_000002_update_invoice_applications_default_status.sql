-- 修改发票申请表的默认状态为 'normal'
-- 执行时间: 2025-11-03

-- 1. 修改status字段的默认值
ALTER TABLE `invoice_applications` 
MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'normal' 
COMMENT '状态：draft-草稿，normal-正常，pending-审批中，approved-已通过，rejected-已驳回，red_flushed-红冲';

-- 2. 更新现有的draft状态为normal（可选，如果需要的话）
-- UPDATE `invoice_applications` SET `status` = 'normal' WHERE `status` = 'draft';

