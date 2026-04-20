#!/bin/bash

# ============================================
# 项目定时任务配置脚本
# 一键配置所有定时任务
# ============================================

echo "=================================="
echo "Laravel 定时任务配置脚本"
echo "=================================="
echo ""

# 请修改为您的实际项目路径
PROJECT_PATH="/var/www/html/re_li_zi_yuan"

echo "当前配置的项目路径: $PROJECT_PATH"
echo ""
echo "如果路径不正确，请修改此脚本的 PROJECT_PATH 变量"
echo ""
read -p "按回车继续，或 Ctrl+C 取消..."

# 添加 Cron 任务
echo ""
echo "正在配置 Crontab..."

# 检查是否已经配置过
if crontab -l 2>/dev/null | grep -q "schedule:run"; then
    echo "⚠️  检测到已存在 schedule:run 定时任务"
    echo ""
    crontab -l | grep "schedule:run"
    echo ""
    read -p "是否覆盖现有配置？(y/n): " confirm
    if [ "$confirm" != "y" ]; then
        echo "已取消配置"
        exit 0
    fi
    # 删除旧的配置
    crontab -l | grep -v "schedule:run" | crontab -
fi

# 添加新配置
(crontab -l 2>/dev/null; echo "* * * * * cd $PROJECT_PATH && php artisan schedule:run >> /dev/null 2>&1") | crontab -

echo ""
echo "✅ Crontab 配置完成！"
echo ""
echo "当前配置："
echo "=================================="
crontab -l | grep "schedule:run"
echo "=================================="
echo ""

# 测试执行
echo "正在测试定时任务..."
cd "$PROJECT_PATH"
php artisan schedule:run

echo ""
echo "✅ 配置完成！"
echo ""
echo "📋 项目中的所有定时任务："
echo "  1. 检查参保入职超期（每天 08:00）"
echo "  2. 生成参保明细（每月最后一天 23:00）"
echo "  3. 处理员工调基（每天 09:00）"
echo "  4. 处理基数补差（每天 09:30）"
echo "  5. 生成资料交付记录（每月1日 08:00）"
echo "  6. 检查未交付记录（每月最后一天 20:00）"
echo ""
echo "🔍 查看定时任务列表："
echo "  php artisan schedule:list"
echo ""
echo "📖 查看日志："
echo "  tail -f $PROJECT_PATH/storage/logs/laravel.log"
echo ""
echo "=================================="

