-- ========================================
-- 更新工资表结构 - 添加序号、部门、岗位等字段
-- ========================================

-- 添加序号字段（用于显示排序）
ALTER TABLE salaries 
ADD COLUMN seq_number INT NULL COMMENT '序号' AFTER id;

-- 添加部门字段
ALTER TABLE salaries 
ADD COLUMN department VARCHAR(100) NULL COMMENT '所在部门' AFTER employee_id;

-- 添加岗位字段
ALTER TABLE salaries 
ADD COLUMN position VARCHAR(100) NULL COMMENT '岗位' AFTER department;

-- 添加单位保险合计字段
ALTER TABLE salaries 
ADD COLUMN company_insurance_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '单位保险合计' AFTER housing_fund;

-- 添加个人保险合计字段
ALTER TABLE salaries 
ADD COLUMN personal_insurance_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '个人保险合计' AFTER company_insurance_total;

-- 添加补差合计字段
ALTER TABLE salaries 
ADD COLUMN compensation_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '补差合计' AFTER personal_insurance_total;

-- 验证表结构
DESC salaries;

-- 查看字段顺序
SHOW FULL COLUMNS FROM salaries;

