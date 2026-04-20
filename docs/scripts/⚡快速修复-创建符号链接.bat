@echo off
chcp 65001 >nul
echo ========================================
echo 创建 Laravel Storage 符号链接
echo ========================================
echo.

cd /d "%~dp0"

echo 当前目录: %CD%
echo.

echo 正在创建符号链接...
php artisan storage:link

echo.
echo ========================================
echo 完成！
echo ========================================
echo.
echo 如果出现错误，请：
echo 1. 以管理员身份运行此脚本
echo 2. 或手动执行：php artisan storage:link
echo.

pause

