-- =============================================
-- weiqing9 精简执行脚本（不改动原始 weiqing9.sql 文件）
-- 用途：
--   1）先执行原始 weiqing9.sql（建表 / 视图 / 函数 / 存储过程 / 触发器等）
--   2）再删除你不需要的视图 / 函数 / 存储过程 / 触发器
--   3）索引暂时保留（避免复杂权限和循环），如确实要全部删除，可在导入成功后再单独生成 DROP INDEX 脚本
-- 使用方式（示例）：
--   在 Navicat / 命令行中：
--     1）先选择目标数据库（例如 weiqing）：USE weiqing;
--     2）执行本脚本：SOURCE weiqing9_slim.sql;
-- =============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 尽量降低严格模式，避免部分 SQL 模式导致的额外报错
SET SESSION sql_mode = '';
-- 关闭 InnoDB 严格模式，避免某些“索引过长”直接报错（会以警告方式截断）
SET SESSION innodb_strict_mode = 0;

-- =============================================
-- 1. 执行原始完整脚本（建表 / 视图 / 函数 / 存储过程 / 触发器等）
-- =============================================
SOURCE weiqing9.sql;

-- =============================================
-- 2. 删除你不需要的对象
--    注意：这里只删除视图 / 函数 / 存储过程 / 触发器，不删除任何表和主键
-- =============================================

-- 2.1 视图
DROP VIEW IF EXISTS `v_large_medical_payment_status`;

-- 2.2 函数
DROP FUNCTION IF EXISTS `CalculateLargeMedicalAmount`;

-- 2.3 存储过程（工具类 / JSON 修复 / 大额医疗相关等）
DROP PROCEDURE IF EXISTS `drop_all_indexes`;
DROP PROCEDURE IF EXISTS `drop_foreign_keys`;
DROP PROCEDURE IF EXISTS `drop_indexes`;
DROP PROCEDURE IF EXISTS `execute_drop_fk`;
DROP PROCEDURE IF EXISTS `execute_drop_indexes`;
DROP PROCEDURE IF EXISTS `IsLargeMedicalPaymentMonth`;
DROP PROCEDURE IF EXISTS `safe_modify_column`;

-- 2.4 触发器（大额医疗支付历史相关）
DROP TRIGGER IF EXISTS `tr_insurance_personnel_update_payment_history`;

-- =============================================
-- 3. 结束
--    如需进一步“删除所有非主键索引”，可以在导入成功后，让我根据数据库实际索引列表
--    帮你生成一个专门的 DROP INDEX 脚本
-- =============================================

SET FOREIGN_KEY_CHECKS = 1;
