-- 为 roles 表添加菜单显示权限字段
ALTER TABLE `roles` ADD COLUMN `visible_menus` TEXT NULL COMMENT '可见菜单ID列表(JSON格式)' AFTER `description`;

-- 为超级管理员和管理员设置所有菜单可见（默认值）
UPDATE `roles` SET `visible_menus` = NULL WHERE `name` IN ('super_admin', 'admin');

-- 为其他角色设置默认可见菜单（可以根据需要调整）
-- NULL 表示可以看到所有菜单
