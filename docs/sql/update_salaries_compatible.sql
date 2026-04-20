-- ========================================
-- 兼容性更新工资表结构
-- 移除 AFTER 子句，避免引用不存在的字段
-- ========================================

-- 如果字段已存在会报错，可以忽略或删除对应行

-- 1. 添加序号字段
ALTER TABLE salaries 
ADD COLUMN seq_number INT NULL COMMENT '序号';

-- 2. 添加部门字段
ALTER TABLE salaries 
ADD COLUMN department VARCHAR(100) NULL COMMENT '所在部门';

-- 3. 添加岗位字段
ALTER TABLE salaries 
ADD COLUMN position VARCHAR(100) NULL COMMENT '岗位';

-- 4. 添加单位保险合计字段
ALTER TABLE salaries 
ADD COLUMN company_insurance_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '单位保险合计';

-- 5. 添加个人保险合计字段
ALTER TABLE salaries 
ADD COLUMN personal_insurance_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '个人保险合计';

-- 6. 添加补差合计字段
ALTER TABLE salaries 
ADD COLUMN compensation_total DECIMAL(10, 2) DEFAULT 0.00 COMMENT '补差合计';

-- 验证表结构
DESC salaries;

