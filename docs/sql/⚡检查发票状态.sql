-- 检查发票申请的状态字段

-- 1. 查看最近创建的记录的状态
SELECT 
    id,
    application_no,
    task_name,
    status,
    created_at
FROM invoice_applications
ORDER BY created_at DESC
LIMIT 10;

-- 2. 查看所有状态的分布
SELECT 
    status,
    COUNT(*) as count
FROM invoice_applications
GROUP BY status;

-- 3. 如果发现status为NULL，执行修复
-- UPDATE invoice_applications SET status = 'normal' WHERE status IS NULL;

-- 4. 如果status字段本身不存在或有问题，查看表结构
SHOW COLUMNS FROM invoice_applications LIKE 'status';

