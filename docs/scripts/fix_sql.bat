@echo off
chcp 65001 >nul
echo === SQL文件兼容性修复工具 ===
echo.

set /p sql_file="请输入SQL文件的完整路径: "

if not exist "%sql_file%" (
    echo 错误: 文件不存在!
    pause
    exit /b 1
)

echo.
echo 开始修复SQL文件...
python fix_sql_file.py "%sql_file%"

echo.
echo 修复完成! 请查看生成的 *_fixed.sql 文件
pause
