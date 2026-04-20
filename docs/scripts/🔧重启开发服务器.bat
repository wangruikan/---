@echo off
echo ========================================
echo 清除缓存并重启开发服务器
echo ========================================
echo.

cd /d "%~dp0"

echo [1/3] 删除 node_modules/.vite 缓存...
if exist "node_modules\.vite" (
    rmdir /s /q "node_modules\.vite"
    echo ✓ 缓存已清除
) else (
    echo - 缓存目录不存在，跳过
)

echo.
echo [2/3] 删除 dist 目录...
if exist "dist" (
    rmdir /s /q "dist"
    echo ✓ dist 目录已删除
) else (
    echo - dist 目录不存在，跳过
)

echo.
echo [3/3] 启动开发服务器...
echo ========================================
npm run dev

pause

