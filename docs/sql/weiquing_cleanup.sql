-- =============================================
-- weiqing 清理脚本（精简版）
-- 作用：在导入原始 Navicat 导出 SQL 之后，删除不需要的视图/函数/存储过程/触发器
-- 使用方式：
--   1）先导入原始 weiqing*.sql（包含所有表结构/数据）
--   2）切换到对应数据库：USE weiqing;
--   3）执行本脚本：SOURCE weiquing_cleanup.sql;
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. 删除视图（如不需要大额医疗状态视图）
DROP VIEW IF EXISTS `v_large_medical_payment_status`;

-- 2. 删除函数（大额医疗金额计算函数）
DROP FUNCTION IF EXISTS `CalculateLargeMedicalAmount`;

-- 3. 删除存储过程（索引/外键工具、JSON 修改工具、大额医疗支付月份判断等）
DROP PROCEDURE IF EXISTS `drop_all_indexes`;
DROP PROCEDURE IF EXISTS `drop_foreign_keys`;
DROP PROCEDURE IF EXISTS `drop_indexes`;
DROP PROCEDURE IF EXISTS `execute_drop_fk`;
DROP PROCEDURE IF EXISTS `execute_drop_indexes`;
DROP PROCEDURE IF EXISTS `IsLargeMedicalPaymentMonth`;
DROP PROCEDURE IF EXISTS `safe_modify_column`;

-- 4. 删除触发器（大额医疗支付历史相关）
DROP TRIGGER IF EXISTS `tr_insurance_personnel_update_payment_history`;

-- 如需进一步删除索引，可以在确认无 1071 报错后，按需手动写：
--   ALTER TABLE `某表` DROP INDEX `某索引名`;
-- 这里不自动删除索引，避免误删业务需要的索引。

SET FOREIGN_KEY_CHECKS = 1;
