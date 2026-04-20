<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Reimbursement;
use App\Models\ReimbursementAttachment;
use App\Models\SalaryApproval;
use App\Models\SalaryApprovalAttachment;
use App\Models\ProcessApproval;
use App\Models\ProcessAttachment;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GenerateTestPaymentData extends Command
{
    protected $signature = 'test:generate-payment-data {account_set_id}';
    protected $description = '生成用于测试付款审批的已审批数据（包含附件）';

    public function handle()
    {
        $accountSetId = $this->argument('account_set_id');
        
        $this->info("开始生成测试数据，账套ID: {$accountSetId}");
        
        DB::beginTransaction();
        
        try {
            // 1. 创建已审批通过的报销申请（带附件）
            $this->createReimbursement($accountSetId);
            
            // 2. 创建已审批通过的工资表审批（带附件）
            $this->createSalaryApproval($accountSetId);
            
            // 3. 创建已审批通过的保险汇总（带附件）
            $this->createInsuranceSummary($accountSetId);
            
            DB::commit();
            
            $this->info('✓ 测试数据生成完成！');
            $this->info('请刷新页面，在各模块中查看已审批通过的记录，并尝试发起付款审批。');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('生成失败: ' . $e->getMessage());
            $this->error($e->getTraceAsString());
        }
    }
    
    private function createReimbursement($accountSetId)
    {
        $this->info('创建报销申请...');
        
        // 创建报销记录
        $reimbursement = Reimbursement::create([
            'account_set_id' => $accountSetId,
            'company_name' => '测试公司',
            'applicant' => '测试员工',
            'amount' => 1500.50,
            'category' => '差旅',
            'project' => '测试项目',
            'reason' => '出差报销-附件测试',
            'status' => 'approved',  // 已审批通过
            'created_by' => 1,
        ]);
        
        // 创建测试附件文件
        $testContent = "这是报销申请的测试附件\n报销ID: {$reimbursement->id}\n金额: 1500.50";
        $filePath = "reimbursements/{$reimbursement->id}/test_attachment.txt";
        Storage::disk('public')->put($filePath, $testContent);
        
        // 创建附件记录
        ReimbursementAttachment::create([
            'reimbursement_id' => $reimbursement->id,
            'file_name' => '报销凭证_测试.txt',
            'file_path' => $filePath,
            'file_type' => 'text/plain',
            'file_size' => strlen($testContent),
        ]);
        
        $this->info("  ✓ 报销申请已创建 ID: {$reimbursement->id}，附件: 报销凭证_测试.txt");
    }
    
    private function createSalaryApproval($accountSetId)
    {
        $this->info('创建工资表审批...');
        
        // 获取第一个项目
        $project = \App\Models\Project::where('account_set_id', $accountSetId)->first();
        
        if (!$project) {
            $this->warn('  ! 未找到项目，跳过工资表审批创建');
            return;
        }
        
        // 创建工资表审批记录
        $salaryApproval = SalaryApproval::create([
            'account_set_id' => $accountSetId,
            'project_id' => $project->id,
            'month' => date('Y-m'),
            'status' => 'approved',  // 已审批通过
            'submitted_by' => 1,
            'submitted_at' => now(),
        ]);
        
        // 创建测试附件文件
        $testContent = "这是工资表审批的测试附件\n项目: {$project->name}\n月份: " . date('Y-m');
        $filePath = "salary_approvals/{$salaryApproval->id}/test_attachment.txt";
        Storage::disk('public')->put($filePath, $testContent);
        
        // 创建附件记录
        SalaryApprovalAttachment::create([
            'salary_approval_id' => $salaryApproval->id,
            'filename' => '工资表_测试.txt',
            'file_path' => $filePath,
            'mime_type' => 'text/plain',
            'file_size' => strlen($testContent),
            'uploaded_by' => 1,
        ]);
        
        $this->info("  ✓ 工资表审批已创建 ID: {$salaryApproval->id}，项目: {$project->name}，附件: 工资表_测试.txt");
    }
    
    private function createInsuranceSummary($accountSetId)
    {
        $this->info('创建保险汇总...');
        
        // 创建保险汇总记录
        $processApproval = ProcessApproval::create([
            'account_set_id' => $accountSetId,
            'initiator_id' => 1,
            'title' => '保险汇总-附件测试 ' . date('Y-m'),
            'month' => date('Y-m'),
            'status' => 'approved',  // 已审批通过
            'project_ids' => json_encode([]),
        ]);
        
        // 创建测试附件文件
        $testContent = "这是保险汇总的测试附件\n标题: {$processApproval->title}\n月份: " . date('Y-m');
        $filePath = "process_approvals/{$processApproval->id}/test_attachment.txt";
        Storage::disk('public')->put($filePath, $testContent);
        
        // 创建附件记录
        ProcessAttachment::create([
            'process_approval_id' => $processApproval->id,
            'filename' => '保险汇总_测试.txt',
            'file_path' => $filePath,
            'mime_type' => 'text/plain',
            'file_size' => strlen($testContent),
            'uploaded_by' => 1,
        ]);
        
        $this->info("  ✓ 保险汇总已创建 ID: {$processApproval->id}，附件: 保险汇总_测试.txt");
    }
}
