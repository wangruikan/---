@echo off
echo 检查编译后的文件...
echo.

echo === 本地 index.html 内容 ===
type "public\index.html"
echo.
echo.

echo === 检查主要文件是否存在 ===
if exist "public\assets\app.js" (
    echo ✓ app.js 存在 - 大小: 
    dir "public\assets\app.js" | find "app.js"
) else (
    echo ✗ app.js 不存在
)

if exist "public\assets\index.css" (
    echo ✓ index.css 存在 - 大小:
    dir "public\assets\index.css" | find "index.css"
) else (
    echo ✗ index.css 不存在
)

echo.
echo === 需要上传到服务器的文件 ===
echo 1. public\index.html
echo 2. public\.htaccess
echo 3. public\assets\app.js
echo 4. public\assets\index.css
echo 5. public\assets\ 目录下的所有其他文件
echo.
pause
