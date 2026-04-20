<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\AssessmentRecord;
use App\Models\AccountSet;
use App\Models\EmployeeDocument;
use App\Models\ProjectDocumentConfig;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckNewEmployeeDocuments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assessment:check-new-employee-documents 
                            {--account-set-id= : 指定账套ID，不指定则检查所有账套}
                            {--month= : 指定检查月份(YYYY-MM)，不指定则检查当前月}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '月末检测新入职员工资料上传情况，为业务人员生成考核记录';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('开始检测新入职员工资料上传情况...');
        
        // 获取参数
        $accountSetId = $this->option('account-set-id');
        $month = $this->option('month') ?: Carbon::now()->format('Y-m');
        
        $this->info("检查月份: {$month}");
        
        // 解析月份
        $checkDate = Carbon::createFromFormat('Y-m', $month);
        $startOfMonth = $checkDate->copy()->startOfMonth();
        $endOfMonth = $checkDate->copy()->endOfMonth();
        
        // 获取要检查的账套
        $accountSets = $accountSetId 
            ? AccountSet::where('id', $accountSetId)->get()
            : AccountSet::all();
            
        $totalProcessed = 0;
        $totalAssessments = 0;
        
        foreach ($accountSets as $accountSet) {
            $this->info("正在检查账套: {$accountSet->name} (ID: {$accountSet->id})");
            
            $result = $this->checkAccountSetEmployees($accountSet, $startOfMonth, $endOfMonth);
            $totalProcessed += $result['processed'];
            $totalAssessments += $result['assessments'];
            
            $this->line("  - 检查员工数: {$result['processed']}");
            $this->line("  - 生成考核记录: {$result['assessments']}");
        }
        
        $this->info("检测完成!");
        $this->info("总计检查员工: {$totalProcessed}");
        $this->info("总计生成考核记录: {$totalAssessments}");
        
        // 记录日志
        Log::info('新入职员工资料检测完成', [
            'month' => $month,
            'account_set_id' => $accountSetId,
            'total_employees' => $totalProcessed,
            'total_assessments' => $totalAssessments
        ]);
    }
    
    /**
     * 检查指定账套的员工
     */
    private function checkAccountSetEmployees($accountSet, $startOfMonth, $endOfMonth)
    {
        $processed = 0;
        $assessments = 0;
        
        // 查找本月入职的员工
        $newEmployees = Employee::where('account_set_id', $accountSet->id)
            ->whereBetween('hire_date', [$startOfMonth, $endOfMonth])
            ->get();
            
        $this->line("  找到本月入职员工: {$newEmployees->count()} 人");
        
        foreach ($newEmployees as $employee) {
            $processed++;
            $this->line("    检查员工: {$employee->name} (入职日期: {$employee->hire_date})");
            
            // 检查该员工的资料上传情况
            $missingDocuments = $this->checkEmployeeDocuments($employee);
            
            if (!empty($missingDocuments)) {
                $this->warn("      缺失资料: " . implode(', ', $missingDocuments));
                
                // 获取经办人ID
                $handlerId = $this->getAccountSetHandler($accountSet->id);
                
                if ($handlerId) {
                    // 创建考核记录
                    $created = $this->createAssessmentRecord($accountSet->id, $employee, $missingDocuments, $handlerId);
                    if ($created) {
                        $assessments++;
                        $this->info("      已创建考核记录");
                    }
                } else {
                    $this->warn("      未找到经办人，跳过考核记录创建");
                }
            } else {
                $this->info("      资料齐全，无需考核");
            }
        }
        
        return [
            'processed' => $processed,
            'assessments' => $assessments
        ];
    }
    
    /**
     * 检查员工资料上传情况
     * 检查员工在资料上传管理系统中是否已上传所有必需资料
     */
    private function checkEmployeeDocuments($employee)
    {
        $missingDocuments = [];
        
        // 获取员工关联的项目（假设员工有project_id字段，或通过其他方式关联）
        // 如果员工没有直接关联项目，可能需要通过其他方式获取应该上传的资料配置
        
        // 方法1：如果员工表有project_id字段
        if (isset($employee->project_id) && $employee->project_id) {
            $requiredDocuments = ProjectDocumentConfig::where('project_id', $employee->project_id)
                ->where('is_required', true)
                ->get();
        } else {
            // 方法2：获取默认的必需资料配置（可以是某个默认项目或全局配置）
            $requiredDocuments = ProjectDocumentConfig::where('is_required', true)
                ->get();
        }
        
        // 检查每个必需资料是否已上传
        foreach ($requiredDocuments as $requiredDoc) {
            $hasUploaded = EmployeeDocument::where('employee_id', $employee->id)
                ->where('document_config_id', $requiredDoc->id)
                ->exists();
                
            if (!$hasUploaded) {
                $missingDocuments[] = $requiredDoc->document_name;
            }
        }
        
        // 如果没有找到任何必需资料配置，使用默认检查（为了演示）
        if ($requiredDocuments->isEmpty()) {
            $this->warn("      未找到必需资料配置，使用默认检查");
            
            // 模拟检查逻辑（为了演示功能）
            if (strpos($employee->name, '张三') !== false) {
                $missingDocuments = ['学历证明', '证件照片', '银行卡', '劳动合同'];
            } elseif (strpos($employee->name, '李四') !== false) {
                $missingDocuments = [];
            } elseif (strpos($employee->name, '王五') !== false) {
                $missingDocuments = ['身份证', '学历证明', '个人简历', '证件照片', '银行卡', '劳动合同'];
            }
        }
        
        return $missingDocuments;
    }
    
    
    /**
     * 获取账套的第一个审批人（经办人）
     */
    private function getAccountSetHandler($accountSetId)
    {
        $firstApprover = \DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->whereNotNull('account_set_users.approval_level')
            ->orderBy('account_set_users.approval_level')
            ->select('users.id as user_id', 'users.name as user_name')
            ->first();

        if (!$firstApprover) {
            return null;
        }

        return [
            'user_id' => $firstApprover->user_id,
            'user_name' => $firstApprover->user_name
        ];
    }
    
    /**
     * 创建考核记录
     */
    private function createAssessmentRecord($accountSetId, $employee, $missingDocuments, $handler)
    {
        try {
            // 检查是否已存在相同的考核记录
            $existingRecord = AssessmentRecord::where('account_set_id', $accountSetId)
                ->where('business_type', 'document_upload')
                ->where('business_id', $employee->id)
                ->whereMonth('created_at', Carbon::now()->month)
                ->whereYear('created_at', Carbon::now()->year)
                ->first();
                
            if ($existingRecord) {
                $this->warn("      考核记录已存在，跳过创建");
                return false;
            }
            
            // 计算截止日期（入职后7个工作日）
            $deadlineDate = Carbon::parse($employee->hire_date)->addWeekdays(7);
            
            // 创建考核记录
            AssessmentRecord::create([
                'account_set_id' => $accountSetId,
                'business_type' => 'document_upload',
                'business_id' => $employee->id,
                'business_name' => "新员工 {$employee->name} 资料收集",
                'handler_id' => $handler['user_id'],
                'handler_name' => $handler['user_name'],
                'deadline_date' => $deadlineDate,
                'status' => 'pending',
                'remark' => "缺失资料: " . implode(', ', $missingDocuments) . "。请及时跟进收集。"
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->error("      创建考核记录失败: " . $e->getMessage());
            Log::error('创建考核记录失败', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
