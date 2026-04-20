-- ========================================
-- 只添加 position 字段到 employees 表
-- 部门字段不需要，因为部门就是项目
-- ========================================

-- 1. 先检查当前表结构
DESC employees;

-- 2. 添加 position 字段
ALTER TABLE employees 
ADD COLUMN position VARCHAR(100) NULL COMMENT '岗位' AFTER name;

-- 3. 验证字段是否添加成功
SHOW COLUMNS FROM employees WHERE Field = 'position';

-- 4. 查看示例数据
SELECT id, name, position, id_number FROM employees LIMIT 5;

