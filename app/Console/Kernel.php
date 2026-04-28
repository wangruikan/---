<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // 每天早上8点检查参保入职超期情况
        $schedule->command('assessment:check-insurance-deadlines')
                 ->dailyAt('08:00')
                 ->timezone('Asia/Shanghai');

        // 每月最后一天23点自动生成参保明细记录
        $schedule->command('insurance:generate-monthly-details')
                 ->lastDayOfMonth('23:00')
                 ->timezone('Asia/Shanghai');
        
        // 每天早上9点处理员工调基（检查是否到达生效时间）
        $schedule->command('base:process-employee-adjustments')
                 ->dailyAt('09:00')
                 ->timezone('Asia/Shanghai');
        
        // 每天早上9点05分处理保险上下限待生效变更
        $schedule->command('insurance:process-limit-effective')
                 ->dailyAt('09:05')
                 ->timezone('Asia/Shanghai');

        // 每天早上9点30分处理基数补差
        $schedule->command('base:process-compensation')
                 ->dailyAt('09:30')
                 ->timezone('Asia/Shanghai');
        
        // 每月1日早上8点生成资料交付记录
        $schedule->command('delivery:generate')
                 ->monthlyOn(1, '08:00')
                 ->timezone('Asia/Shanghai');
        
        // 每月最后一天晚上20点检查未交付记录
        $schedule->command('delivery:check-pending')
                 ->lastDayOfMonth('20:00')
                 ->timezone('Asia/Shanghai');
        
        // 每月最后一天晚上21点检查新入职员工资料上传情况
        $schedule->command('assessment:check-new-employee-documents')
                 ->lastDayOfMonth('21:00')
                 ->timezone('Asia/Shanghai');
        
        // 每月最后一天晚上22点检查员工合同签订情况
        $schedule->command('contract:check-monthly')
                 ->lastDayOfMonth('22:00')
                 ->timezone('Asia/Shanghai');
        
        // 每月15号早上9点检查合同提醒记录，未解决的升级为考核
        $schedule->command('contract:escalate-reminders')
                 ->monthlyOn(15, '09:00')
                 ->timezone('Asia/Shanghai');
        
        // 每天早上9点检查试用期到期情况
        $schedule->command('probation:check')
                 ->dailyAt('09:00')
                 ->timezone('Asia/Shanghai');
        
        // 每月18号早上10点检查付款申请完成情况（社保和公积金汇总）
        $schedule->command('payment:check-completion')
                 ->monthlyOn(18, '10:00')
                 ->timezone('Asia/Shanghai');
        
        // 每月最后一天晚上23点检查离职合同完成情况
        $schedule->command('check:resignation-contracts')
                 ->lastDayOfMonth('23:00')
                 ->timezone('Asia/Shanghai');
        
        // 每天早上9点检查线下入职合同上传情况
        $schedule->command('contract:check-offline-onboarding')
                 ->dailyAt('09:00')
                 ->timezone('Asia/Shanghai');
        
        // 每小时检查社保公积金缴费提醒
        $schedule->command('payment:expire-supplement-window')
                 ->hourly()
                 ->timezone('Asia/Shanghai');

        $schedule->command('payment:check-reminders')
                 ->hourly()
                 ->timezone('Asia/Shanghai');
        
        // 每月1日早上8点检查工资和考勤依据上传情况
        $schedule->command('basis:check-reminders')
                 ->monthlyOn(1, '08:00')
                 ->timezone('Asia/Shanghai');
        
        // 每月1日早上8点检查考勤表和工资表提交情况
        $schedule->command('sheet:check-reminders')
                 ->monthlyOn(1, '08:00')
                 ->timezone('Asia/Shanghai');
        
        // 每天早上9点检查未开票项目
        $schedule->command('invoice:check-reminders')
                 ->dailyAt('09:00')
                 ->timezone('Asia/Shanghai');
        
        // 每天早上9点检查工资发放提醒（发放日期前一天提醒）
        $schedule->command('salary:check-payment-reminders')
                 ->dailyAt('09:00')
                 ->timezone('Asia/Shanghai');
        
        // 每天早上9点检查社保汇总表审核提醒（缴费前一天必须完成审核）
        $schedule->command('insurance:check-summary-reminders')
                 ->dailyAt('09:00')
                 ->timezone('Asia/Shanghai');
        
        // 每天早上8点检查税费申报提醒
        $schedule->command('tax:check-reminders')
                 ->dailyAt('08:00')
                 ->timezone('Asia/Shanghai');

        // 每天凌晨1点自动确认符合条件的考核记录（每月15号后生效）
        $schedule->command('assessment:auto-confirm')
                 ->dailyAt('01:00')
                 ->timezone('Asia/Shanghai');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
