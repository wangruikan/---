-- ============================================
-- 修改 payment_requests 表，支持报销类目作为付款类型
-- 执行时间：请立即执行
-- ============================================

-- 第一步：将 payment_type 从 enum 改为 VARCHAR（如果还没执行）
ALTER TABLE `payment_requests` 
MODIFY COLUMN `payment_type` VARCHAR(50) NOT NULL DEFAULT 'reimbursement' COMMENT '付款类型：salary/insurance/reimbursement/报销/差旅/采购/项目/其他';

-- 第二步：检查 reimbursement_id 字段是否存在
-- 执行以下查询，如果返回 0 则表示字段不存在，需要执行第三步
-- SELECT COUNT(*) as field_exists FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
--   AND TABLE_NAME = 'payment_requests' 
--   AND COLUMN_NAME = 'reimbursement_id';

-- 第三步：如果字段不存在，执行以下语句添加字段
-- 注意：如果字段已存在，执行此语句会报错 "Duplicate column name 'reimbursement_id'"，可以忽略
ALTER TABLE `payment_requests` 
ADD COLUMN `reimbursement_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '报销申请ID（当payment_type为报销相关类型时使用）' AFTER `salary_approval_id`;

-- 第四步：检查索引是否存在
-- 执行以下查询，如果返回 0 则表示索引不存在，需要执行第五步
-- SELECT COUNT(*) as index_exists FROM INFORMATION_SCHEMA.STATISTICS 
-- WHERE TABLE_SCHEMA = DATABASE() 
--   AND TABLE_NAME = 'payment_requests' 
--   AND INDEX_NAME = 'idx_reimbursement_id';

-- 第五步：如果索引不存在，执行以下语句添加索引
-- 注意：如果索引已存在，执行此语句会报错，可以忽略
ALTER TABLE `payment_requests` 
ADD KEY `idx_reimbursement_id` (`reimbursement_id`);

-- ============================================
-- 执行完成！
-- ============================================
-- 
-- 说明：
-- 1. payment_type 字段已改为 VARCHAR(50)，可以存储任意字符串
-- 2. 支持的值包括：salary, insurance, reimbursement, 报销, 差旅, 采购, 项目, 其他
-- 3. 如果报销的类目是"报销"，则 payment_type 也是"报销"
-- 4. 如果报销的类目是"差旅"，则 payment_type 也是"差旅"
-- 以此类推

