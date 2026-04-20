-- 修复SQL兼容性的脚本
-- 1. 将 utf8mb4_0900_ai_ci 替换为 utf8mb4_unicode_ci
-- 2. 将 json 类型替换为 text 类型
-- 3. 修复索引长度问题

-- 设置字符集
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 如果需要修改现有表的排序规则
ALTER DATABASE weiqing_new1 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 修复 personnel_change_requests 表的 JSON 字段
ALTER TABLE personnel_change_requests MODIFY COLUMN personnel_list TEXT NOT NULL COMMENT '人员列表 JSON格式';

-- 修复可能的索引长度问题
-- 如果 salary_summaries 表的索引过长，可以这样修复：
-- ALTER TABLE salary_summaries DROP INDEX IF EXISTS some_long_index;
-- ALTER TABLE salary_summaries ADD INDEX idx_project_month (project_id, month(50));

SET FOREIGN_KEY_CHECKS = 1;
