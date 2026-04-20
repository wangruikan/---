-- 验证重新发起标记字段是否添加成功

-- 1. 查看字段信息
SHOW COLUMNS FROM `invoice_applications` WHERE Field IN ('has_resubmitted', 'new_application_id');

-- 2. 查看最近的申请记录
SELECT 
    id, 
    application_no, 
    status, 
    has_resubmitted, 
    new_application_id,
    created_at
FROM invoice_applications 
ORDER BY created_at DESC 
LIMIT 10;

-- 3. 查看所有红冲状态的申请
SELECT 
    id, 
    application_no, 
    status, 
    has_resubmitted, 
    new_application_id
FROM invoice_applications 
WHERE status = 'red_flushed';

