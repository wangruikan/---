@echo off
chcp 65001 >nul
echo ================================================
echo 更新发票申请表默认状态为 'normal'
echo ================================================
echo.

echo 请确认数据库配置：
echo - 请检查 .env 文件中的数据库连接信息
echo.
pause

echo 正在执行SQL...
php artisan db:unprepared --force < database/migrations/2025_11_03_000002_update_invoice_applications_default_status.sql

if %errorlevel% neq 0 (
    echo.
    echo ❌ 执行失败！
    echo 请手动执行以下SQL：
    echo.
    type database\migrations\2025_11_03_000002_update_invoice_applications_default_status.sql
    echo.
    pause
    exit /b 1
)

echo.
echo ✅ 执行成功！
echo.
echo 修改内容：
echo - status字段默认值改为 'normal'
echo.
pause

