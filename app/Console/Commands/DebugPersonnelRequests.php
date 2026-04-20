<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\PersonnelChangeRequest;
use App\Models\Employee;

class DebugPersonnelRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:personnel-requests';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '调试人员汇总申请数据';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info("=== 开始调试人员汇总申请数据 ===");
        
        // 查询人员汇总申请
        $requests = PersonnelChangeRequest::latest()->take(5)->get();
        
        $this->info("找到 {$requests->count()} 个人员汇总申请");
        
        if ($requests->count() === 0) {
            $this->warn("没有找到人员汇总申请数据！");
            return;
        }
        
        foreach ($requests as $index => $request) {
            $this->info("\n=== 人员汇总申请 #{$index} ===");
            $this->info("ID: {$request->id}");
            $this->info("申请人: {$request->applicant_name}");
            $this->info("申请时间: {$request->application_date}");
            
            // 解析人员列表
            $personnelList = is_string($request->personnel_list) 
                ? json_decode($request->personnel_list, true) 
                : $request->personnel_list;
                
            if (is_array($personnelList)) {
                $this->info("人员列表数量: " . count($personnelList));
                
                foreach ($personnelList as $pIndex => $person) {
                    $this->line("  人员 #{$pIndex}:");
                    $this->line("    姓名: " . ($person['name'] ?? 'N/A'));
                    $this->line("    身份证号: " . ($person['id_card'] ?? 'N/A'));
                    
                    // 尝试匹配员工
                    $idCard = $person['id_card'] ?? null;
                    if ($idCard) {
                        $employee = Employee::where('id_number', $idCard)->first();
                        if ($employee) {
                            $this->line("    ✅ 匹配到员工: {$employee->name} (ID: {$employee->id})");
                            $this->line("    员工详细信息:");
                            $this->line("      国籍: " . ($employee->country_region ?? 'NULL'));
                            $this->line("      性别: " . ($employee->gender ?? 'NULL'));
                            $this->line("      出生日期: " . ($employee->birth_date ?? 'NULL'));
                            $this->line("      人员状态: " . ($employee->personnel_status ?? 'NULL'));
                            $this->line("      任职类型: " . ($employee->employment_type ?? 'NULL'));
                        } else {
                            $this->line("    ❌ 未匹配到员工");
                        }
                    }
                }
            } else {
                $this->warn("人员列表格式错误或为空");
            }
        }
        
        $this->info("\n=== 调试完成 ===");
    }
}
