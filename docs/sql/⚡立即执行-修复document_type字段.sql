-- 修复 project_document_configs 表的 document_type 字段
-- 如果字段不存在，先创建表
CREATE TABLE IF NOT EXISTS `project_document_configs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '项目ID',
    `document_name` varchar(100) NOT NULL COMMENT '资料名称',
    `document_type` varchar(50) NOT NULL DEFAULT 'all' COMMENT '文件类型限制',
    `is_required` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否必填',
    `sort_order` int(11) NOT NULL DEFAULT 0 COMMENT '排序',
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `project_id` (`project_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目资料配置表';

-- 如果表已存在但字段类型不对，修改字段类型
ALTER TABLE `project_document_configs` 
MODIFY COLUMN `document_type` varchar(50) NOT NULL DEFAULT 'all' COMMENT '文件类型限制(image/pdf/document/all)';

-- 查看表结构
DESC project_document_configs;

-- 查看现有数据
SELECT * FROM project_document_configs ORDER BY project_id, sort_order;
