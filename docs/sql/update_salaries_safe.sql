-- ========================================
-- 安全更新工资表结构
-- 先检查字段是否存在，再添加
-- ========================================

-- 方法：先添加到表末尾，避免 AFTER 引用不存在的字段

-- 1. 添加序号字段
ALTER TABLE salaries 
ADD COLUMN IF NOT EXISTS seq_number INT NULL COMMENT '序号';

-- 2. 添加部门字段
ALTER TABLE salaries 
ADD COLUMN IF NOT EXISTS department VARCHAR(100) NULL COMMENT '所在部门';

-- 3. 添加岗位字段
ALTER TABLE salaries 
ADD COLUMN IF NOT EXISTS position VARCHAR(100) NULL COMMENT '岗位';

-- 4. 添加单位保险合计字段
ALTER TABLE salaries 
ADD COLUMN IF NOT EXISTS company_insurance_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '单位保险合计';

-- 5. 添加个人保险合计字段
ALTER TABLE salaries 
ADD COLUMN IF NOT EXISTS personal_insurance_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '个人保险合计';

-- 6. 添加补差合计字段
ALTER TABLE salaries 
ADD COLUMN IF NOT EXISTS compensation_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '补差合计';

-- 验证表结构
DESC salaries;

-- 查看所有字段
SHOW FULL COLUMNS FROM salaries;

