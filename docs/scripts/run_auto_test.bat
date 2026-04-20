@echo off
chcp 65001 >nul
echo ====================================
echo 自动化测试 - 修改上下限补差功能
echo ====================================
echo.

php auto_test_limit_compensation.php wrk

echo.
pause

