<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TaxDeclarationConfig;
use App\Models\TaxDeclarationTask;
use App\Models\User;
use App\Services\PendingTaskService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckTaxDeclarationReminders extends Command
{
    protected $signature = 'tax:check-reminders';
    protected $description = '检查税费申报提醒，生成申报任务';

    public function handle()
    {
        $this->info('开始检查税费申报提醒...');
        
        $today = now()->format('m-d');
        $currentYear = now()->year;
        
        // 获取所有配置
        $configs = TaxDeclarationConfig::all();
        
        $createdCount = 0;
        
        foreach ($configs as $config) {
            // 检查是否到了申报日期
            if ($config->declaration_date === $today) {
                $this->info("配置 #{$config->id} ({$config->company_name}) 到期，开始生成任务...");
                
                // 检查今年是否已生成任务
                $existingTask = TaxDeclarationTask::where('config_id', $config->id)
                    ->where('year', $currentYear)
                    ->where('declaration_date', now()->format('Y-m-d'))
                    ->first();
                
                if ($existingTask) {
                    $this->warn("  任务已存在，跳过");
                    continue;
                }
                
                // 获取第一个审批节点的人员（操作员）
                $handler = $this->getFirstApprover($config->account_set_id);
                
                if (!$handler) {
                    $this->error("  未找到操作员，跳过");
                    Log::warning('税费申报任务创建失败：未找到操作员', [
                        'config_id' => $config->id,
                        'account_set_id' => $config->account_set_id
                    ]);
                    continue;
                }
                
                try {
                    DB::beginTransaction();
                    
                    // 创建申报任务
                    $task = TaxDeclarationTask::create([
                        'account_set_id' => $config->account_set_id,
                        'config_id' => $config->id,
                        'company_name' => $config->company_name,
                        'tax_category_ids' => $config->tax_category_ids,
                        'declaration_date' => now(),
                        'year' => $currentYear,
                        'handler_id' => $handler->id,
                        'handler_name' => $handler->name,
                        'status' => 'pending',
                    ]);
                    
                    // 创建待办任务
                    PendingTaskService::createTaxDeclarationTask($task);
                    
                    DB::commit();
                    
                    $this->info("  ✓ 任务创建成功 (ID: {$task->id})");
                    $createdCount++;
                    
                    Log::info('税费申报任务创建成功', [
                        'task_id' => $task->id,
                        'config_id' => $config->id,
                        'company_name' => $config->company_name,
                        'handler_id' => $handler->id
                    ]);
                } catch (\Exception $e) {
                    DB::rollBack();
                    
                    $this->error("  ✗ 任务创建失败: " . $e->getMessage());
                    Log::error('税费申报任务创建失败', [
                        'config_id' => $config->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
        
        $this->info("检查完成，共创建 {$createdCount} 个任务");
        
        return 0;
    }

    /**
     * 获取账套的第一个审批节点人员
     */
    private function getFirstApprover($accountSetId)
    {
        $firstApprover = DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->whereNotNull('account_set_users.approval_level')
            ->where('users.is_active', true)
            ->orderBy('account_set_users.approval_level')
            ->select('users.*')
            ->first();

        return $firstApprover ? User::find($firstApprover->id) : null;
    }
}
