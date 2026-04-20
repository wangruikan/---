-- ========================================
-- 创建工资表（salaries）
-- ========================================

-- 如果表已存在则删除（谨慎使用）
-- DROP TABLE IF EXISTS salaries;

CREATE TABLE IF NOT EXISTS salaries (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '主键',
    seq_number INT NULL COMMENT '序号',
    account_set_id BIGINT UNSIGNED NOT NULL COMMENT '账套ID',
    employee_id BIGINT UNSIGNED NOT NULL COMMENT '员工ID',
    department VARCHAR(100) NULL COMMENT '所在部门',
    position VARCHAR(100) NULL COMMENT '岗位',
    project_id BIGINT UNSIGNED NOT NULL COMMENT '项目ID',
    month VARCHAR(7) NOT NULL COMMENT '工资期间（格式：2025-10）',
    
    -- 工资构成
    basic_salary DECIMAL(10, 2) DEFAULT 0.00 COMMENT '基本工资',
    allowance DECIMAL(10, 2) DEFAULT 0.00 COMMENT '津贴',
    overtime_pay DECIMAL(10, 2) DEFAULT 0.00 COMMENT '加班费',
    bonus DECIMAL(10, 2) DEFAULT 0.00 COMMENT '奖金',
    gross_salary DECIMAL(10, 2) DEFAULT 0.00 COMMENT '应发工资',
    
    -- 扣款项
    social_security DECIMAL(10, 2) DEFAULT 0.00 COMMENT '社保个人部分',
    housing_fund DECIMAL(10, 2) DEFAULT 0.00 COMMENT '公积金个人部分',
    company_insurance_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '单位保险合计',
    personal_insurance_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '个人保险合计',
    compensation_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '补差合计',
    special_deduction DECIMAL(10, 2) DEFAULT 0.00 COMMENT '专项扣除',
    
    -- 税务相关
    taxable_income DECIMAL(10, 2) DEFAULT 0.00 COMMENT '应纳税所得额',
    personal_tax DECIMAL(10, 2) DEFAULT 0.00 COMMENT '个人所得税',
    actual_tax DECIMAL(10, 2) DEFAULT 0.00 COMMENT '实际个税',
    
    -- 实发工资
    net_salary DECIMAL(10, 2) DEFAULT 0.00 COMMENT '实发工资',
    paid_salary DECIMAL(10, 2) DEFAULT 0.00 COMMENT '实际发放金额',
    
    -- 状态管理
    status VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT '状态（draft-草稿, submitted-已提交, approved-已审批, paid-已发放, rejected-已拒绝）',
    
    -- 审批流程
    submitted_by BIGINT UNSIGNED NULL COMMENT '提交人ID',
    approved_by BIGINT UNSIGNED NULL COMMENT '审批人ID',
    submitted_at TIMESTAMP NULL COMMENT '提交时间',
    approved_at TIMESTAMP NULL COMMENT '审批时间',
    paid_at TIMESTAMP NULL COMMENT '发放时间',
    rejection_reason TEXT NULL COMMENT '拒绝原因',
    
    -- 时间戳
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    
    -- 唯一约束：同一账套下，同一员工在同一项目的同一期间只能有一条记录
    UNIQUE KEY uk_salary (account_set_id, employee_id, project_id, month),
    
    -- 外键约束（如果需要的话）
    -- FOREIGN KEY (account_set_id) REFERENCES account_sets(id) ON DELETE CASCADE,
    -- FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    -- FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    
    KEY idx_account_set_id (account_set_id),
    KEY idx_employee_id (employee_id),
    KEY idx_project_id (project_id),
    KEY idx_month (month),
    KEY idx_status (status),
    KEY idx_project_month (project_id, month)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资表';

-- ========================================
-- 验证表结构
-- ========================================

-- 查看表结构
DESC salaries;

-- 查看索引
SHOW INDEX FROM salaries;

