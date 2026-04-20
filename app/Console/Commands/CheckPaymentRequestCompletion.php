<?php

namespace App\Console\Commands;

use App\Models\AccountSet;
use App\Models\AssessmentRecord;
use App\Models\PaymentRequest;
use App\Models\ProcessApproval;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckPaymentRequestCompletion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payment:check-completion {--month= : 指定检查的月份，格式：YYYY-MM}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查本月付款申请中的社保和公积金汇总是否都已完成审批';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $month = $this->option('month') ?: Carbon::now()->format('Y-m');
        
        $this->info("开始检查 {$month} 的付款申请完成情况...");
        Log::info("开始检查付款申请完成情况", ['month' => $month]);

        // 获取所有账套
        $accountSets = AccountSet::all();
        
        $totalMissing = 0;
        $assessmentRecords = [];

        foreach ($accountSets as $accountSet) {
            $this->info("\n检查账套: {$accountSet->name}");
            
            // 获取该账套的第一个审批人（业务人员）
            $firstApprover = $this->getFirstApprover($accountSet->id);
            
            $result = $this->checkAccountSetPayments($accountSet, $month, $firstApprover);
            
            if (!empty($result['missing'])) {
                $totalMissing += count($result['missing']);
                $assessmentRecords = array_merge($assessmentRecords, $result['assessments']);
                
                foreach ($result['missing'] as $missing) {
                    $this->warn("  - 缺失: 项目[{$missing['project_name']}] {$missing['category_name']}汇总付款申请");
                }
            } else {
                $this->info("  ✓ 所有项目的社保和公积金汇总付款申请都已完成");
            }
        }

        // 创建考核记录
        foreach ($assessmentRecords as $record) {
            $this->createAssessmentRecord($record);
        }

        $this->info("\n检查完成！共发现 {$totalMissing} 个缺失的付款申请");
        Log::info("付款申请检查完成", [
            'month' => $month,
            'total_missing' => $totalMissing,
            'assessment_records_created' => count($assessmentRecords)
        ]);

        return 0;
    }
    
    /**
     * 获取账套的第一个审批人（业务人员）
     */
    private function getFirstApprover(int $accountSetId): ?object
    {
        return DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->where('account_set_users.approval_level', 1) // 第一级审批人就是业务人员
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                'account_set_users.approval_level',
                'account_set_users.approval_level_name as level_name'
            )
            ->first();
    }

    /**
     * 检查单个账套的付款申请完成情况
     */
    private function checkAccountSetPayments(AccountSet $accountSet, string $month, ?object $firstApprover): array
    {
        $missing = [];
        $assessments = [];

        // 获取该账套下所有活跃的项目
        $projects = Project::where('account_set_id', $accountSet->id)
            ->where('status', 'active')
            ->get();

        if ($projects->isEmpty()) {
            return ['missing' => [], 'assessments' => []];
        }

        foreach ($projects as $project) {
            // 检查该项目本月是否有社保汇总付款申请（已审批通过）
            $socialInsuranceCompleted = $this->checkPaymentRequestExists(
                $accountSet->id,
                $project->id,
                $month,
                'social_insurance'
            );

            // 检查该项目本月是否有公积金汇总付款申请（已审批通过）
            $housingFundCompleted = $this->checkPaymentRequestExists(
                $accountSet->id,
                $project->id,
                $month,
                'housing_fund'
            );

            // 记录缺失的付款申请
            if (!$socialInsuranceCompleted) {
                $missing[] = [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'category' => 'social_insurance',
                    'category_name' => '社保'
                ];
                
                $assessments[] = [
                    'account_set_id' => $accountSet->id,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'category' => 'social_insurance',
                    'category_name' => '社保',
                    'month' => $month,
                    'handler_id' => $firstApprover ? $firstApprover->user_id : 0,
                    'handler_name' => $firstApprover ? $firstApprover->user_name : '待分配'
                ];
            }

            if (!$housingFundCompleted) {
                $missing[] = [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'category' => 'housing_fund',
                    'category_name' => '公积金'
                ];
                
                $assessments[] = [
                    'account_set_id' => $accountSet->id,
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'category' => 'housing_fund',
                    'category_name' => '公积金',
                    'month' => $month,
                    'handler_id' => $firstApprover ? $firstApprover->user_id : 0,
                    'handler_name' => $firstApprover ? $firstApprover->user_name : '待分配'
                ];
            }
        }

        return ['missing' => $missing, 'assessments' => $assessments];
    }

    /**
     * 检查指定项目的付款申请是否存在且已审批通过
     */
    private function checkPaymentRequestExists(
        int $accountSetId,
        int $projectId,
        string $month,
        string $category
    ): bool {
        // 查找该月份、该类型的已审批通过的付款申请
        // 付款申请关联的是 ProcessApproval（保险汇总），需要通过 insurance_summary_id 关联
        $paymentRequest = PaymentRequest::where('account_set_id', $accountSetId)
            ->where('payment_type', 'insurance')
            ->where('status', 'approved')
            ->whereHas('insuranceSummary', function ($query) use ($month, $category, $projectId) {
                $query->where('month', $month)
                    ->where('category', $category)
                    ->where(function($q) use ($projectId) {
                        // 使用 LIKE 查询 JSON 数组中的值，兼容低版本 MySQL
                        $q->where('project_ids', 'LIKE', '%"' . $projectId . '"%')
                          ->orWhere('project_ids', 'LIKE', '%[' . $projectId . ']%')
                          ->orWhere('project_ids', 'LIKE', '%[' . $projectId . ',%')
                          ->orWhere('project_ids', 'LIKE', '%,' . $projectId . ']%')
                          ->orWhere('project_ids', 'LIKE', '%,' . $projectId . ',%');
                    });
            })
            ->first();

        return $paymentRequest !== null;
    }

    /**
     * 创建考核记录
     */
    private function createAssessmentRecord(array $data): void
    {
        // 检查是否已存在相同的考核记录
        $exists = AssessmentRecord::where('account_set_id', $data['account_set_id'])
            ->where('business_type', 'payment_request_missing')
            ->where('business_name', "like", "%{$data['project_name']}%{$data['category_name']}%{$data['month']}%")
            ->exists();

        if ($exists) {
            $this->info("  考核记录已存在，跳过: {$data['project_name']} - {$data['category_name']}");
            return;
        }

        // 截止日期为当月18号
        $deadlineDate = Carbon::parse($data['month'] . '-18');

        AssessmentRecord::create([
            'account_set_id' => $data['account_set_id'],
            'business_type' => 'payment_request_missing',
            'business_id' => $data['project_id'],
            'business_name' => "{$data['month']} {$data['project_name']} {$data['category_name']}汇总付款申请未完成",
            'handler_id' => $data['handler_id'], // 第一个审批人（业务人员）
            'handler_name' => $data['handler_name'],
            'deadline_date' => $deadlineDate,
            'actual_complete_date' => null,
            'overdue_days' => Carbon::now()->gt($deadlineDate) ? Carbon::now()->diffInDays($deadlineDate) : 0,
            'status' => Carbon::now()->gt($deadlineDate) ? 'overdue' : 'pending',
            'remark' => "项目[{$data['project_name']}]的{$data['month']}月{$data['category_name']}汇总付款申请未完成审批，责任人：{$data['handler_name']}"
        ]);

        $this->info("  已创建考核记录: {$data['project_name']} - {$data['category_name']} (责任人: {$data['handler_name']})");
        
        Log::info("创建付款申请缺失考核记录", [
            'project_id' => $data['project_id'],
            'project_name' => $data['project_name'],
            'category' => $data['category'],
            'month' => $data['month'],
            'handler_id' => $data['handler_id'],
            'handler_name' => $data['handler_name']
        ]);
    }
}
