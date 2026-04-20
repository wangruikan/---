-- =====================================================
-- 为 document_deliveries 表添加缺失字段
-- =====================================================

-- 添加 config_id 字段（关联到配置表）
ALTER TABLE `document_deliveries` 
ADD COLUMN `config_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '配置ID' AFTER `id`,
ADD KEY `idx_config_id` (`config_id`);

-- 添加 handler_id 字段（经办人/处理人）
ALTER TABLE `document_deliveries` 
ADD COLUMN `handler_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '经办人ID（负责交付的人）' AFTER `status`;

-- =====================================================
-- ✅ 执行完成！现在可以运行"✅立即生成测试数据.sql"了
-- =====================================================

