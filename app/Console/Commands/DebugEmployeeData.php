<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;

class DebugEmployeeData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:employee-data {--account-set-id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '调试员工数据，查看员工表中的字段信息';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $accountSetId = $this->option('account-set-id');
        
        $this->info("=== 开始调试员工数据 (账套ID: {$accountSetId}) ===");
        
        // 查询员工数据
        $query = Employee::query();
        if ($accountSetId) {
            $query->where('account_set_id', $accountSetId);
        }
        
        $employees = $query->get();
        
        $this->info("找到 {$employees->count()} 个员工记录");
        
        if ($employees->count() === 0) {
            $this->warn("没有找到员工数据！");
            return;
        }
        
        // 显示第一个员工的所有字段
        $firstEmployee = $employees->first();
        $this->info("\n=== 第一个员工的所有字段 ===");
        $this->info("ID: {$firstEmployee->id}");
        $this->info("姓名: {$firstEmployee->name}");
        $this->info("身份证号: {$firstEmployee->id_number}");
        
        $attributes = $firstEmployee->getAttributes();
        $this->info("\n=== 所有字段详情 ===");
        foreach ($attributes as $key => $value) {
            $displayValue = $value === null ? 'NULL' : ($value === '' ? 'EMPTY' : $value);
            $this->line("{$key}: {$displayValue}");
        }
        
        // 检查特定字段
        $this->info("\n=== 关键字段检查 ===");
        $keyFields = [
            'country_region', 'gender', 'birth_date', 'personnel_status', 
            'employment_type', 'phone', 'education', 'bank_name', 'bank_account'
        ];
        
        foreach ($keyFields as $field) {
            $value = $firstEmployee->$field;
            $status = $value === null ? '❌ NULL' : ($value === '' ? '⚠️ EMPTY' : '✅ 有值');
            $this->line("{$field}: {$status} ({$value})");
        }
        
        // 显示所有员工的身份证号，用于匹配测试
        $this->info("\n=== 所有员工身份证号列表 ===");
        foreach ($employees as $index => $employee) {
            $this->line("员工 #{$index}: {$employee->name} - {$employee->id_number}");
        }
        
        $this->info("\n=== 调试完成 ===");
    }
}
