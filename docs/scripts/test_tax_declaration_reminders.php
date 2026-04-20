<?php

/**
 * 测试税费申报定时任务
 * 
 * 用法：
 * php docs/scripts/test_tax_declaration_reminders.php
 * 
 * 可以修改 $testDate 变量来测试不同的日期
 */

require __DIR__ . '/../../vendor/autoload.php';

$app = require_once __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TaxDeclarationConfig;
use App\Models\TaxDeclarationTask;
use App\Models\User;
use App\Services\PendingTaskService;
use Illuminate\Support\Facades\DB;

// ==================== 配置测试日期 ====================
// 修改这里的日期来测试不同的申报日期
// 格式：'Y-m-d' 例如 '2026-03-15'
$testDate = '2026-03-01'; // 修改为你想测试的日期（改为03-01以匹配配置）
// ====================================================

echo "========================================\n";
echo "税费申报定时任务测试\n";
echo "========================================\n";
echo "测试日期: {$testDate}\n";
echo "----------------------------------------\n\n";

try {
    $testDateTime = new DateTime($testDate);
    $today = $testDateTime->format('m-d');
    $currentYear = $testDateTime->format('Y');
    
    echo "检查日期: {$today}\n";
    echo "年份: {$currentYear}\n\n";
    
    // 获取所有配置
    $configs = TaxDeclarationConfig::with('accountSet')->get();
    
    echo "找到 " . $configs->count() . " 个申报配置\n\n";
    
    if ($configs->isEmpty()) {
        echo "⚠️  没有找到任何申报配置\n";
        echo "请先在系统中创建税费申报配置\n";
        exit;
    }
    
    $createdCount = 0;
    $skippedCount = 0;
    
    foreach ($configs as $config) {
        echo "----------------------------------------\n";
        echo "配置 #{$config->id}\n";
        echo "  公司名称: {$config->company_name}\n";
        echo "  账套: {$config->accountSet->name}\n";
        echo "  申报日期: {$config->declaration_date}\n";
        echo "  申报周期: {$config->period_type}\n";
        
        // 检查是否到了申报日期
        if ($config->declaration_date === $today) {
            echo "  ✓ 日期匹配！\n";
            
            // 检查今年是否已生成任务
            $existingTask = TaxDeclarationTask::where('config_id', $config->id)
                ->where('year', $currentYear)
                ->whereDate('declaration_date', $testDate)
                ->first();
            
            if ($existingTask) {
                echo "  ⚠️  任务已存在 (ID: {$existingTask->id})，跳过\n";
                $skippedCount++;
                continue;
            }
            
            // 获取第一个审批节点的人员（操作员）
            $handler = DB::table('account_set_users')
                ->join('users', 'account_set_users.user_id', '=', 'users.id')
                ->where('account_set_users.account_set_id', $config->account_set_id)
                ->whereNotNull('account_set_users.approval_level')
                ->where('users.is_active', true)
                ->orderBy('account_set_users.approval_level')
                ->select('users.*')
                ->first();
            
            if (!$handler) {
                echo "  ✗ 未找到操作员，跳过\n";
                echo "  提示: 请确保账套中有设置 approval_level 的用户\n";
                continue;
            }
            
            $handler = User::find($handler->id);
            echo "  操作员: {$handler->name} (ID: {$handler->id})\n";
            
            try {
                DB::beginTransaction();
                
                // 创建申报任务
                $task = TaxDeclarationTask::create([
                    'account_set_id' => $config->account_set_id,
                    'config_id' => $config->id,
                    'company_name' => $config->company_name,
                    'tax_category_ids' => $config->tax_category_ids,
                    'declaration_date' => $testDate,
                    'year' => $currentYear,
                    'handler_id' => $handler->id,
                    'handler_name' => $handler->name,
                    'status' => 'pending',
                ]);
                
                echo "  ✓ 申报任务创建成功 (ID: {$task->id})\n";
                
                // 创建待办任务
                PendingTaskService::createTaxDeclarationTask($task);
                
                echo "  ✓ 待办任务创建成功\n";
                
                DB::commit();
                
                $createdCount++;
                
            } catch (\Exception $e) {
                DB::rollBack();
                echo "  ✗ 创建失败: " . $e->getMessage() . "\n";
                echo "  错误详情: " . $e->getTraceAsString() . "\n";
            }
        } else {
            echo "  - 日期不匹配 (需要: {$today}, 配置: {$config->declaration_date})\n";
        }
    }
    
    echo "\n========================================\n";
    echo "测试完成\n";
    echo "----------------------------------------\n";
    echo "创建任务数: {$createdCount}\n";
    echo "跳过任务数: {$skippedCount}\n";
    echo "========================================\n";
    
    // 显示创建的任务
    if ($createdCount > 0) {
        echo "\n创建的任务列表:\n";
        echo "----------------------------------------\n";
        
        $tasks = TaxDeclarationTask::whereDate('declaration_date', $testDate)
            ->with(['handler'])
            ->get();
        
        foreach ($tasks as $task) {
            echo "任务 #{$task->id}\n";
            echo "  公司: {$task->company_name}\n";
            echo "  操作员: {$task->handler_name}\n";
            echo "  状态: {$task->status}\n";
            echo "  申报日期: {$task->declaration_date}\n";
            echo "\n";
        }
        
        // 显示待办任务
        echo "待办任务列表:\n";
        echo "----------------------------------------\n";
        
        $pendingTasks = DB::table('pending_tasks')
            ->where('task_type', 'tax_declaration')
            ->whereDate('created_at', '>=', $testDate)
            ->get();
        
        foreach ($pendingTasks as $pendingTask) {
            echo "待办 #{$pendingTask->id}\n";
            echo "  标题: {$pendingTask->title}\n";
            echo "  负责人ID: {$pendingTask->handler_id}\n";
            echo "  状态: {$pendingTask->status}\n";
            echo "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "\n✗ 发生错误: " . $e->getMessage() . "\n";
    echo "错误详情:\n";
    echo $e->getTraceAsString() . "\n";
}
