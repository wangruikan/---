-- ===================================================================
-- 同步 requires_attendance 和 require_attendance 字段
-- ===================================================================
-- 问题说明：
-- 前端使用的是 requires_attendance（旧字段）
-- 后端优先读取 require_attendance（新字段）
-- 导致修改不生效
-- ===================================================================

-- 第1步：查看当前两个字段的值
SELECT 
    id,
    name,
    requires_attendance AS '旧字段(前端使用)',
    require_attendance AS '新字段(后端读取)',
    CASE 
        WHEN requires_attendance = require_attendance THEN '✅ 一致'
        ELSE '❌ 不一致'
    END AS '是否一致'
FROM projects
ORDER BY id;

-- ===================================================================

-- 第2步：将 requires_attendance 的值同步到 require_attendance
-- 这样前端修改的值就能被后端正确读取
UPDATE projects 
SET require_attendance = requires_attendance;

-- ===================================================================

-- 第3步：验证同步结果
SELECT 
    id,
    name,
    requires_attendance AS '旧字段',
    require_attendance AS '新字段',
    CASE 
        WHEN require_attendance = 1 THEN '✅ 需要考勤'
        WHEN require_attendance = 0 THEN '❌ 无需考勤'
        ELSE '⚠️ 未设置'
    END AS '考勤设置'
FROM projects
ORDER BY id;

-- ===================================================================
-- 说明：
-- 执行后，两个字段的值会保持一致
-- 以后通过前端修改时，后端会自动同步两个字段
-- ===================================================================

