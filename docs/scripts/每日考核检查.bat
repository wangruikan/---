@echo off
echo 开始检查参保入职超期情况...
cd /d "E:\project\re_li_zi_yuan\re_li_zi_yuan(1)\re_li_zi_yuan"
php artisan assessment:check-insurance-deadlines
echo 检查完成！
pause
