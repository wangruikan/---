@echo off
echo ========================================
echo 修复投标项目管理模块加载问题
echo ========================================
echo.

cd /d E:\project\re_li_zi_yuan\re_li_zi_yuan(1)\re_li_zi_yuan

echo [1/4] 删除 Vite 缓存...
if exist node_modules\.vite (
    rd /s /q node_modules\.vite
    echo ✓ Vite 缓存已清除
) else (
    echo - Vite 缓存不存在
)

echo.
echo [2/4] 清理后端缓存...
php artisan cache:clear
php artisan config:clear
php artisan route:clear

echo.
echo [3/4] 删除临时文件...
if exist .cache (
    rd /s /q .cache
    echo ✓ 临时文件已清除
)

echo.
echo [4/4] 完成！
echo.
echo ========================================
echo 下一步操作：
echo ========================================
echo 1. 重启前端服务（Ctrl+C 停止，然后 npm run dev）
echo 2. 清理浏览器缓存（Ctrl+Shift+Delete）
echo 3. 硬性刷新页面（Ctrl+F5）
echo.
echo 菜单位置已更新：
echo 项目管理 → 投标项目管理
echo ========================================
echo.
pause

