-- 创建测试数据验证月末检查功能

-- 1. 创建本月入职的测试员工
INSERT INTO employees (
    account_set_id, 
    name, 
    hire_date, 
    position, 
    employee_number,
    phone,
    email,
    created_at,
    updated_at
) VALUES 
(1, '测试员工张三', '2024-11-15', '软件工程师', 'EMP001', '13800138001', 'zhangsan@test.com', NOW(), NOW()),
(1, '测试员工李四', '2024-11-20', '产品经理', 'EMP002', '13800138002', 'lisi@test.com', NOW(), NOW()),
(1, '测试员工王五', '2024-11-25', '设计师', 'EMP003', '13800138003', 'wangwu@test.com', NOW(), NOW());

-- 2. 为部分员工创建资料记录（模拟资料不全的情况）
-- 张三：只上传了身份证和简历（缺失：学历证明、证件照片、银行卡、劳动合同）
INSERT INTO document_deliveries (
    account_set_id,
    employee_id,
    document_type,
    document_name,
    status,
    created_at,
    updated_at
) VALUES 
(1, (SELECT id FROM employees WHERE name = '测试员工张三' LIMIT 1), 'id_card', '张三身份证', 'completed', NOW(), NOW()),
(1, (SELECT id FROM employees WHERE name = '测试员工张三' LIMIT 1), 'resume', '张三简历', 'completed', NOW(), NOW());

-- 李四：上传了所有资料（应该不会生成考核记录）
INSERT INTO document_deliveries (
    account_set_id,
    employee_id,
    document_type,
    document_name,
    status,
    created_at,
    updated_at
) VALUES 
(1, (SELECT id FROM employees WHERE name = '测试员工李四' LIMIT 1), 'id_card', '李四身份证', 'completed', NOW(), NOW()),
(1, (SELECT id FROM employees WHERE name = '测试员工李四' LIMIT 1), 'diploma', '李四学历证明', 'completed', NOW(), NOW()),
(1, (SELECT id FROM employees WHERE name = '测试员工李四' LIMIT 1), 'resume', '李四简历', 'completed', NOW(), NOW()),
(1, (SELECT id FROM employees WHERE name = '测试员工李四' LIMIT 1), 'photo', '李四证件照', 'completed', NOW(), NOW()),
(1, (SELECT id FROM employees WHERE name = '测试员工李四' LIMIT 1), 'bank_card', '李四银行卡', 'completed', NOW(), NOW()),
(1, (SELECT id FROM employees WHERE name = '测试员工李四' LIMIT 1), 'contract', '李四劳动合同', 'completed', NOW(), NOW());

-- 王五：没有上传任何资料（缺失所有资料）

-- 3. 创建审批记录，确保能找到业务人员
INSERT INTO approval_instances (
    account_set_id,
    business_type,
    business_id,
    current_step,
    total_steps,
    status,
    created_by,
    created_at,
    updated_at
) VALUES (1, 'test', 1, 1, 3, 'pending', 1, NOW(), NOW());

INSERT INTO approval_records (
    instance_id,
    step_order,
    step_name,
    approver_id,
    approver_name,
    status,
    created_at,
    updated_at
) VALUES (
    (SELECT id FROM approval_instances ORDER BY id DESC LIMIT 1),
    1,
    '业务审批',
    1,
    '业务经理',
    'pending',
    NOW(),
    NOW()
);

-- 查看创建的测试数据
SELECT 
    e.name as '员工姓名',
    e.hire_date as '入职日期',
    COUNT(dd.id) as '已上传资料数',
    GROUP_CONCAT(dd.document_type) as '已上传资料类型'
FROM employees e
LEFT JOIN document_deliveries dd ON e.id = dd.employee_id AND dd.status = 'completed'
WHERE e.name LIKE '测试员工%'
GROUP BY e.id, e.name, e.hire_date
ORDER BY e.hire_date;
