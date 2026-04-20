-- 修复现有的工资表审批流程，跳过第一步（发起人自己审批）

-- 1. 查看需要修复的审批实例
SELECT 
    ai.id AS instance_id,
    ai.business_type,
    ai.business_id AS salary_approval_id,
    ai.current_step,
    ai.total_steps,
    ai.created_by AS creator_id,
    ar1.approver_id AS step1_approver_id,
    ar1.status AS step1_status,
    ar2.approver_id AS step2_approver_id,
    ar2.status AS step2_status
FROM approval_instances ai
LEFT JOIN approval_records ar1 ON ai.id = ar1.instance_id AND ar1.step_order = 1
LEFT JOIN approval_records ar2 ON ai.id = ar2.instance_id AND ar2.step_order = 2
WHERE ai.business_type = '工资表审批'
AND ai.status = 'pending'
AND ai.created_by = ar1.approver_id  -- 第一步审批人就是发起人
AND ar1.status = 'pending';

-- 2. 修复这些审批实例（将第一步标记为已审批，推进到第二步）
-- 注意：先确认上面的查询结果正确后再执行

-- 2.1 标记第一步为已审批（自动通过）
UPDATE approval_records ar
JOIN approval_instances ai ON ar.instance_id = ai.id
SET 
    ar.status = 'approved',
    ar.approved_at = NOW(),
    ar.comment = '系统自动通过（发起人）'
WHERE ai.business_type = '工资表审批'
AND ai.status = 'pending'
AND ar.step_order = 1
AND ar.status = 'pending'
AND ai.created_by = ar.approver_id;

-- 2.2 更新审批实例，推进到第二步
UPDATE approval_instances ai
SET 
    ai.current_step = 2,
    ai.updated_at = NOW()
WHERE ai.business_type = '工资表审批'
AND ai.status = 'pending'
AND ai.current_step = 1
AND EXISTS (
    SELECT 1 FROM approval_records ar 
    WHERE ar.instance_id = ai.id 
    AND ar.step_order = 1 
    AND ar.approver_id = ai.created_by
);

-- 2.3 将第二步状态改为 pending（进入待审批）
UPDATE approval_records ar
JOIN approval_instances ai ON ar.instance_id = ai.id
SET ar.status = 'pending'
WHERE ai.business_type = '工资表审批'
AND ai.status = 'pending'
AND ar.step_order = 2
AND ar.status = 'waiting';

-- 3. 验证修复结果
SELECT 
    ai.id AS instance_id,
    ai.business_type,
    ai.current_step,
    ai.total_steps,
    CONCAT(u1.name, ' (ID:', ar1.approver_id, ')') AS step1_approver,
    ar1.status AS step1_status,
    ar1.approved_at AS step1_approved_at,
    CONCAT(u2.name, ' (ID:', ar2.approver_id, ')') AS step2_approver,
    ar2.status AS step2_status
FROM approval_instances ai
LEFT JOIN approval_records ar1 ON ai.id = ar1.instance_id AND ar1.step_order = 1
LEFT JOIN users u1 ON ar1.approver_id = u1.id
LEFT JOIN approval_records ar2 ON ai.id = ar2.instance_id AND ar2.step_order = 2
LEFT JOIN users u2 ON ar2.approver_id = u2.id
WHERE ai.business_type = '工资表审批'
ORDER BY ai.id DESC;

