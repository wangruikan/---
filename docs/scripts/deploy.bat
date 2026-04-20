@echo off
echo 开始编译前端项目...

REM 1. 编译前端到 dist 目录
call yarn build
if %errorlevel% neq 0 (
    echo 编译失败！
    pause
    exit /b 1
)

echo 编译成功！开始复制文件...

REM 2. 创建目标目录（如果不存在）
if not exist "public\assets" mkdir "public\assets"

REM 3. 复制编译后的文件到 public 目录
echo 复制 index.html...
copy "dist\index.html" "public\index.html" /Y

echo 复制静态资源...
xcopy "dist\assets\*" "public\assets\" /E /Y /I

echo 部署完成！
echo.
echo 文件已复制到：
echo - public\index.html
echo - public\assets\
echo.
pause
