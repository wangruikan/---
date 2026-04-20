-- 更新已审批通过的工资表审批状态

-- 1. 查看需要更新的记录
SELECT 
    sa.id AS salary_approval_id,
    sa.status AS current_status,
    ai.id AS instance_id,
    ai.status AS instance_status,
    ai.completed_at
FROM salary_approvals sa
JOIN approval_instances ai ON sa.approval_instance_id = ai.id
WHERE ai.business_type = '工资表审批'
AND ai.status = 'approved'
AND sa.status != 'approved';

-- 2. 更新工资表审批状态为已批准
UPDATE salary_approvals sa
JOIN approval_instances ai ON sa.approval_instance_id = ai.id
SET 
    sa.status = 'approved',
    sa.approved_at = ai.completed_at
WHERE ai.business_type = '工资表审批'
AND ai.status = 'approved'
AND sa.status != 'approved';

-- 3. 验证更新结果
SELECT 
    sa.id,
    sa.project_id,
    sa.month,
    sa.status,
    sa.approved_at,
    ai.status AS instance_status
FROM salary_approvals sa
JOIN approval_instances ai ON sa.approval_instance_id = ai.id
WHERE ai.business_type = '工资表审批'
ORDER BY sa.id DESC;



