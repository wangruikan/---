-- 添加"是否需要考勤"字段到项目表
ALTER TABLE `projects` 
ADD COLUMN `require_attendance` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否需要考勤：1-需要，0-不需要' AFTER `status`;

-- 更新说明
-- 默认值为 1（需要考勤），保持向后兼容
-- 可以在项目设置中修改此选项

