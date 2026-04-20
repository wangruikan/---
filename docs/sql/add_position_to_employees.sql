-- ========================================
-- 添加岗位字段到员工档案表
-- ========================================

-- 添加岗位字段
ALTER TABLE employees 
ADD COLUMN position VARCHAR(100) NULL COMMENT '岗位' AFTER name;

-- 验证
DESC employees;

-- 查看添加结果
SELECT id, name, position, department FROM employees LIMIT 10;

