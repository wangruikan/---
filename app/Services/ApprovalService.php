<?php

namespace App\Services;

use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use App\Models\ApprovalCCUser;
use App\Models\EmployeeContract;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ApprovalService
{
    /**
     * 创建审批流程实例
     * 
     * @param int $accountSetId 账套ID
     * @param string $businessType 业务类型
     * @param int $businessId 业务数据ID
     * @param int $createdBy 发起人ID
     * @param array $attachments 附件数组 [['path' => '', 'name' => '', 'size' => 0, 'type' => '']]
     * @param bool $skipInitiator 是否跳过发起人审批
     * @param string|null $stampMethod 盖章方式 (online/offline)
     * @return ApprovalInstance
     */
    public function createApprovalInstance($accountSetId, $businessType, $businessId, $createdBy, $attachments = [], $skipInitiator = false, $stampMethod = null)
    {
        DB::beginTransaction();
        
        try {
            // 1. 获取该账套配置的审批人
            $approvers = $this->getAccountSetApprovers($accountSetId);
            
            if (empty($approvers)) {
                throw new \Exception('该账套未配置审批人，请先在账套管理中设置审批级别');
            }
            
            // 2. 创建审批实例
            $instanceData = [
                'account_set_id' => $accountSetId,
                'business_type' => $businessType,
                'business_id' => $businessId,
                'current_step' => 1,
                'total_steps' => count($approvers), // 记录实际配置的审批人数量
                'status' => 'pending',
                'created_by' => $createdBy,
            ];
            
            // 如果传入了盖章方式，则添加到实例数据中
            if ($stampMethod) {
                $instanceData['stamp_method'] = $stampMethod;
            }
            
            $instance = ApprovalInstance::create($instanceData);
            
            Log::info('创建审批实例', [
                'instance_id' => $instance->id,
                'business_type' => $businessType,
                'approvers' => $approvers,
                'attachments_count' => count($attachments)
            ]);
            
            // 2.5. 创建附件记录
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    \App\Models\ApprovalAttachment::create([
                        'instance_id' => $instance->id,
                        'file_path' => $attachment['path'],
                        'file_name' => $attachment['name'],
                        'file_size' => $attachment['size'] ?? null,
                        'file_type' => $attachment['type'] ?? null,
                        'uploaded_by' => null, // 系统自动添加
                    ]);
                }
                Log::info('附件已关联', ['count' => count($attachments)]);
            }
            
            // 3. 创建审批记录（为每个审批人创建一条记录）
            // 自动检测：如果第一个审批人就是发起人，自动跳过
            $autoSkipFirstStep = false;
            if (!empty($approvers) && $approvers[0]->user_id == $createdBy) {
                $autoSkipFirstStep = true;
                Log::info('检测到第一个审批人是发起人，将自动跳过第一步', [
                    'instance_id' => $instance->id,
                    'created_by' => $createdBy,
                    'first_approver_id' => $approvers[0]->user_id
                ]);
            }
            
            foreach ($approvers as $index => $approver) {
                $stepOrder = $index + 1;
                
                // 如果是第一步且需要自动跳过，设置为approved；否则第一步为pending，其他为waiting
                if ($stepOrder === 1 && $autoSkipFirstStep) {
                    $status = 'approved';
                } elseif ($stepOrder === 1) {
                    $status = 'pending';
                } else {
                    $status = 'waiting';
                }
                
                ApprovalRecord::create([
                    'instance_id' => $instance->id,
                    'step_order' => $stepOrder,
                    'step_name' => $approver->level_name,
                    'approver_id' => $approver->user_id,
                    'approver_name' => $approver->user_name,
                    'status' => $status,
                    'approved_at' => ($status === 'approved') ? now() : null,
                    'comment' => ($status === 'approved') ? '发起人自动通过' : null,
                ]);
            }
            
    // 如果跳过发起人，自动推进到下一步
            if ($skipInitiator || $autoSkipFirstStep) {
                // 确保审批记录已经保存到数据库
                DB::commit();
                DB::beginTransaction();
                
                $this->autoAdvanceToNextStep($instance->id);
            }
            
            // 4. 更新业务数据状态
            $this->updateBusinessStatus($businessType, $businessId, 'in_approval', $instance->id);
            
            DB::commit();
            
            return $instance;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('创建审批流程失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * 获取账套的审批人配置（按级别排序）
     */
    private function getAccountSetApprovers($accountSetId)
    {
        return DB::table('account_set_users')
            ->join('users', 'account_set_users.user_id', '=', 'users.id')
            ->where('account_set_users.account_set_id', $accountSetId)
            ->whereNotNull('account_set_users.approval_level')
            ->orderBy('account_set_users.approval_level')
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                'account_set_users.approval_level',
                'account_set_users.approval_level_name as level_name'
            )
            ->get()
            ->toArray();
    }
    
    /**
     * 审批通过
     */
    public function approve($recordId, $approverId, $comment = null, $ccUsers = [], $signatureId = null, $sealId = null)
    {
        DB::beginTransaction();
        
        try {
            $record = ApprovalRecord::findOrFail($recordId);
            $instance = $record->instance;
            
            // 验证审批人
            if ($record->approver_id != $approverId) {
                throw new \Exception('您不是当前节点的审批人');
            }
            
            if ($record->status !== 'pending') {
                throw new \Exception('该审批节点已处理');
            }
            
            // 获取签名/印章图片路径
            $signatureImagePath = null;
            $sealImagePath = null;
            
            if ($signatureId) {
                $signature = \App\Models\UserSignature::find($signatureId);
                if ($signature) {
                    $signatureImagePath = $signature->image_path;
                }
            }
            
            if ($sealId) {
                $seal = \App\Models\UserSeal::find($sealId);
                if ($seal) {
                    $sealImagePath = $seal->image_path;
                }
            }
            
            // 更新审批记录
            $record->update([
                'status' => 'approved',
                'comment' => $comment,
                'signature_image' => $signatureImagePath,
                'seal_image' => $sealImagePath,
                'approved_at' => now(),
            ]);
            
            
            // 添加抄送人
            foreach ($ccUsers as $userId) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    ApprovalCCUser::create([
                        'instance_id' => $instance->id,
                        'user_id' => $userId,
                        'user_name' => $user->name,
                        'added_by' => $approverId,
                        'added_at_step' => $record->step_order,
                    ]);
                }
            }
            
            // 判断是否还有下一步（根据实际配置的审批人数量）
            $actualApproversCount = ApprovalRecord::where('instance_id', $instance->id)->count();
            
            if ($instance->current_step < $actualApproversCount) {
                // 流转到下一步
                $instance->update(['current_step' => $instance->current_step + 1]);
                
                // 激活下一步的审批记录
                ApprovalRecord::where('instance_id', $instance->id)
                    ->where('step_order', $instance->current_step)
                    ->update(['status' => 'pending']);
                    
                Log::info('审批流转到下一步', [
                    'instance_id' => $instance->id,
                    'current_step' => $instance->current_step,
                    'actual_approvers_count' => $actualApproversCount
                ]);
            } else {
                // 所有步骤完成
                $instance->update([
                    'status' => 'approved',
                    'completed_at' => now(),
                ]);
                
                // 更新业务数据状态为已完成
                $this->updateBusinessStatus(
                    $instance->business_type,
                    $instance->business_id,
                    'completed',
                    $instance->id  // 传入审批实例ID
                );
                
                // 自动盖银行付讫章（最后一个审批人）
                $this->applyBankStampIfExists($instance, $approverId);
                
                Log::info('审批流程全部完成', [
                    'instance_id' => $instance->id,
                    'actual_approvers_count' => $actualApproversCount
                ]);
            }
            
            DB::commit();
            
            return $instance;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('审批失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * 退回审批（退回到上一级）
     */
    public function returnToPrevious($recordId, $approverId, $comment)
    {
        DB::beginTransaction();
        
        try {
            $record = ApprovalRecord::findOrFail($recordId);
            $instance = $record->instance;
            
            // 验证审批人
            if ($record->approver_id != $approverId) {
                throw new \Exception('您不是当前节点的审批人');
            }
            
            if ($record->status !== 'pending') {
                throw new \Exception('该审批节点已处理');
            }
            
            // 验证是否可以退回（第一步不能退回）
            if ($record->step_order <= 1) {
                throw new \Exception('第一步无法退回');
            }
            
            $previousStep = $record->step_order - 1;
            
            // 1) 标记当前步骤为退回，保留退回痕迹
            $record->update([
                'status' => 'returned',
                'comment' => $comment,
                'returned_to_step' => $previousStep,
                'returned_at' => now(),
            ]);
            
            // 2) 回退流程主状态，确保不会仍然显示“已完成”
            $instance->update([
                'current_step' => $previousStep,
                'status' => 'pending',
                'completed_at' => null,
            ]);
            
            // 3) 重新激活上一步审批人，清理旧审批痕迹
            ApprovalRecord::where('instance_id', $instance->id)
                ->where('step_order', $previousStep)
                ->update([
                    'status' => 'pending',
                    'approved_at' => null,
                    'comment' => null,
                    'signature_image' => null,
                    'seal_image' => null,
                    'returned_to_step' => null,
                    'returned_at' => null,
                ]);
            
            // 4) 当前步骤之后的所有步骤重置为 waiting
            // 注意：不要覆盖当前这条 returned 记录，否则前端看不到“已退回”
            ApprovalRecord::where('instance_id', $instance->id)
                ->where('step_order', '>', $record->step_order)
                ->update([
                    'status' => 'waiting',
                    'approved_at' => null,
                    'comment' => null,
                    'signature_image' => null,
                    'seal_image' => null,
                    'returned_to_step' => null,
                    'returned_at' => null,
                ]);

            // 5) 同步业务状态回到审批中（兜底避免业务侧仍显示已完成）
            $this->updateBusinessStatus(
                $instance->business_type,
                $instance->business_id,
                'in_approval',
                $instance->id
            );
            
            Log::info('审批已退回上一级', [
                'instance_id' => $instance->id,
                'from_step' => $record->step_order,
                'to_step' => $previousStep,
                'comment' => $comment
            ]);
            
            DB::commit();
            
            return $instance;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('退回失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * 驳回审批（直接拒绝整个流程）
     */
    public function reject($recordId, $approverId, $comment)
    {
        DB::beginTransaction();
        
        try {
            $record = ApprovalRecord::findOrFail($recordId);
            $instance = $record->instance;
            
            // 验证审批人
            if ($record->approver_id != $approverId) {
                throw new \Exception('您不是当前节点的审批人');
            }
            
            if ($record->status !== 'pending') {
                throw new \Exception('该审批节点已处理');
            }
            
            // 更新审批记录
            $record->update([
                'status' => 'rejected',
                'comment' => $comment,
                'approved_at' => now(),
            ]);
            
            // 更新审批实例状态
            $instance->update([
                'status' => 'rejected',
                'completed_at' => now(),
            ]);
            
            // 更新业务数据状态
            $this->updateBusinessStatus(
                $instance->business_type,
                $instance->business_id,
                'rejected'
            );
            
            // 创建考核记录
            $this->createAssessmentForRejection($instance, $record, $comment);
            
            DB::commit();
            
            return $instance;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('驳回失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * 撤回审批（发起人撤回自己发起的审批）
     * 规则：只有当流程在第二个审批人节点，且第二个审批人还未审批时，才能撤回
     */
    public function withdraw($instanceId, $userId)
    {
        DB::beginTransaction();
        
        try {
            $instance = ApprovalInstance::findOrFail($instanceId);
            
            // 验证是否是发起人
            if ($instance->created_by != $userId) {
                throw new \Exception('只有发起人才能撤回审批');
            }
            
            // 验证审批状态
            if ($instance->status !== 'pending') {
                throw new \Exception('该审批流程已结束，无法撤回');
            }
            
            // 获取所有审批记录
            $records = ApprovalRecord::where('instance_id', $instanceId)
                ->orderBy('step_order')
                ->get();
            
            // 检查是否有第二个审批人
            if ($records->count() < 2) {
                throw new \Exception('审批流程少于2个审批人，无法撤回');
            }
            
            // 获取第二个审批记录
            $secondRecord = $records->where('step_order', 2)->first();
            
            if (!$secondRecord) {
                throw new \Exception('未找到第二个审批人');
            }
            
            // 检查第二个审批人是否已经审批
            if ($secondRecord->status === 'approved') {
                throw new \Exception('第二个审批人已经审批，无法撤回');
            }
            
            // 检查当前流程是否在第二步或之前
            if ($instance->current_step > 2) {
                throw new \Exception('审批已进行到第三步或更后，无法撤回');
            }
            
            // 更新审批实例状态为已撤回
            $instance->update([
                'status' => 'withdrawn',
                'completed_at' => now(),
            ]);
            
            // 将所有待审批和等待中的记录标记为已撤回
            ApprovalRecord::where('instance_id', $instanceId)
                ->whereIn('status', ['pending', 'waiting'])
                ->update(['status' => 'withdrawn']);
            
            // 更新业务数据状态为已撤回
            $this->updateBusinessStatus(
                $instance->business_type,
                $instance->business_id,
                'withdrawn'
            );
            
            Log::info('审批已撤回', [
                'instance_id' => $instanceId,
                'created_by' => $userId,
                'current_step' => $instance->current_step,
                'business_type' => $instance->business_type
            ]);
            
            DB::commit();
            
            return $instance;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('撤回失败', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
    
    /**
     * 更新业务数据状态
     */
    private function updateBusinessStatus($businessType, $businessId, $status, $instanceId = null)
    {
        Log::info('updateBusinessStatus 被调用', [
            'businessType' => $businessType,
            'businessId' => $businessId,
            'status' => $status,
            'instanceId' => $instanceId
        ]);
        
        switch ($businessType) {
            case 'employee_contract':
                $data = ['status' => $status];
                if ($instanceId) {
                    $data['approval_instance_id'] = $instanceId;
                }
                if ($instanceId && Schema::hasColumn('employee_contracts', 'source_type')) {
                    $instance = \App\Models\ApprovalInstance::find($instanceId);
                    if ($instance && in_array($instance->stamp_method, ['online', 'offline'], true)) {
                        $data['source_type'] = $instance->stamp_method;
                    }
                }
                
                // 更新合同状态
                $contract = EmployeeContract::find($businessId);
                if ($contract) {
                    $contract->update($data);
                    
                    // 如果审批完成，根据合同类型更新员工状态（支持 complete 和 completed 两种状态）
                    if ($status === 'completed' || $status === 'complete') {
                        $employee = \App\Models\Employee::find($contract->employee_id);
                        if ($employee) {
                            // 检查数据库表是否有这些字段
                            $hasTerminationDate = Schema::hasColumn('employees', 'termination_date');
                            $hasRetirementDate = Schema::hasColumn('employees', 'retirement_date');
                            $hasIsRetired = Schema::hasColumn('employees', 'is_retired');
                            
                            // 根据合同类型更新员工状态
                            if ($contract->contract_type === 'labor') {
                                // 劳动合同：设置为在职并自动导入保险信息
                                $employee->update(['contract_status' => 'active']);
                                Log::info('员工合同状态已更新为在职', [
                                    'employee_id' => $contract->employee_id,
                                    'contract_id' => $businessId,
                                    'contract_type' => 'labor'
                                ]);
                                
                                // 自动导入保险信息到增减管理模块
                                $this->autoImportInsuranceInfo($contract);
                            } elseif ($contract->contract_type === 'termination') {
                                // 解除协议合同：设置为离职
                                $updateData = ['contract_status' => 'terminated'];
                                if ($hasTerminationDate) {
                                    $updateData['termination_date'] = now();
                                }
                                $employee->update($updateData);
                                Log::info('员工合同状态已更新为离职', [
                                    'employee_id' => $contract->employee_id,
                                    'contract_id' => $businessId,
                                    'contract_type' => 'termination',
                                    'has_termination_date_field' => $hasTerminationDate
                                ]);
                                
                                // 自动创建参保减少记录
                                $this->autoCreateDecreaseInsuranceRecord($contract, 'terminated');
                                
                                // 回退其他保险名额
                                $this->returnOtherInsuranceQuota($contract, $employee);
                                
                                // 删除该员工的基数调差记录
                                $this->deleteEmployeeCompensationRecords($employee->id);
                            } elseif ($contract->contract_type === 'retirement') {
                                // 退休解除协议合同：设置为退休
                                // 先更新 contract_status，然后更新 is_retired（如果字段存在）
                                $employee->contract_status = 'terminated';
                                
                                // 尝试更新 is_retired 字段（即使字段检查失败也尝试，因为可能字段存在但检查有问题）
                                try {
                                    if ($hasIsRetired) {
                                        $employee->is_retired = true;
                                    } else {
                                        // 如果字段检查失败，尝试直接更新（可能字段存在但检查有问题）
                                        DB::table('employees')
                                            ->where('id', $employee->id)
                                            ->update(['is_retired' => true]);
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('更新 is_retired 字段失败', [
                                        'employee_id' => $employee->id,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                                
                                // 更新 retirement_date
                                try {
                                    if ($hasRetirementDate) {
                                        $employee->retirement_date = now();
                                    } else {
                                        // 如果字段检查失败，尝试直接更新
                                        DB::table('employees')
                                            ->where('id', $employee->id)
                                            ->update(['retirement_date' => now()]);
                                    }
                                } catch (\Exception $e) {
                                    Log::warning('更新 retirement_date 字段失败', [
                                        'employee_id' => $employee->id,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                                
                                $employee->save();
                                
                                // 重新加载模型以确保访问器生效
                                $employee->refresh();
                                
                                Log::info('员工合同状态已更新为退休', [
                                    'employee_id' => $contract->employee_id,
                                    'contract_id' => $businessId,
                                    'contract_type' => 'retirement',
                                    'has_is_retired_field' => $hasIsRetired,
                                    'has_retirement_date_field' => $hasRetirementDate,
                                    'is_retired' => $employee->is_retired ?? 'N/A',
                                    'contract_status_accessor' => $employee->contract_status, // 访问器返回的值
                                    'contract_status_raw' => $employee->getAttributes()['contract_status'] ?? 'N/A' // 原始值
                                ]);
                                
                                // 自动创建参保减少记录
                                $this->autoCreateDecreaseInsuranceRecord($contract, 'retired');
                                
                                // 回退其他保险名额
                                $this->returnOtherInsuranceQuota($contract, $employee);
                                
                                // 删除该员工的基数调差记录
                                $this->deleteEmployeeCompensationRecords($employee->id);
                            }
                        }
                    } elseif ($status === 'rejected') {
                        // 如果审批被驳回，保持原状态或设为"未签署"
                        \App\Models\Employee::where('id', $contract->employee_id)
                            ->update(['contract_status' => 'unsigned']);
                    } elseif ($status === 'withdrawn') {
                        // 如果审批被撤回，将合同状态设为"草稿"或"未签署"
                        // 不影响员工状态
                        Log::info('合同审批已撤回', [
                            'contract_id' => $businessId,
                            'employee_id' => $contract->employee_id
                        ]);
                    }
                }
                break;
            
            case '工资表审批':
                // 更新工资表审批状态
                $salaryApproval = \App\Models\SalaryApproval::find($businessId);
                if ($salaryApproval) {
                    if ($status === 'in_approval') {
                        $salaryApproval->update([
                            'status' => 'pending',
                            'approved_by' => null,
                            'approved_at' => null,
                        ]);
                    } elseif ($status === 'completed') {
                        $salaryApproval->update([
                            'status' => 'approved',
                            'approved_at' => now(),
                        ]);
                        Log::info('工资表审批状态已更新为已批准', [
                            'salary_approval_id' => $businessId
                        ]);
                        
                        // 【自动生成工资汇总记录】
                        $this->generateSalarySummaries($salaryApproval);
                        
                        // 【自动生成发工资表】
                        $this->generateSalaryPaymentRecords($salaryApproval);
                        
                        // 【完成工资表待办任务】
                        try {
                            // 直接使用 SalaryApproval 的信息完成待办任务
                            PendingTaskService::checkAndCompleteSalarySheetTask($salaryApproval);
                            Log::info('已完成工资表待办任务', [
                                'salary_approval_id' => $salaryApproval->id,
                                'project_id' => $salaryApproval->project_id,
                                'month' => $salaryApproval->month
                            ]);
                        } catch (\Exception $e) {
                            Log::error('完成工资表待办任务失败', [
                                'salary_approval_id' => $businessId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    } elseif ($status === 'rejected') {
                        $salaryApproval->update([
                            'status' => 'rejected',
                            'rejection_reason' => '审批流程被驳回',
                        ]);
                    }
                }
                break;
            
            case '付款申请':
            case '工资付款申请':
            case '报销付款申请':
                // 更新付款申请状态（工资付款和报销付款使用相同逻辑）
                $paymentRequest = \App\Models\PaymentRequest::find($businessId);
                if ($paymentRequest) {
                    if ($status === 'completed') {
                        $paymentRequest->update([
                            'status' => 'approved',
                            'approved_at' => now(),
                        ]);
                        Log::info('付款申请状态已更新为已批准', [
                            'payment_request_id' => $businessId,
                            'payment_type' => $paymentRequest->payment_type
                        ]);
                        
                        // 自动生成出款汇总记录
                        try {
                            \App\Http\Controllers\PaymentSummaryController::createFromPaymentRequest($paymentRequest);
                        } catch (\Exception $e) {
                            Log::error('自动生成出款汇总记录失败', [
                                'payment_request_id' => $businessId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    } elseif ($status === 'rejected') {
                        $paymentRequest->update([
                            'status' => 'rejected',
                            'rejection_reason' => '审批流程被驳回',
                        ]);
                        Log::info('付款申请状态已更新为已驳回', [
                            'payment_request_id' => $businessId,
                            'payment_type' => $paymentRequest->payment_type ?? 'unknown'
                        ]);
                    }
                }
                break;
            
            case '保险汇总付款申请':
                // 更新保险付款申请状态
                $paymentRequest = \App\Models\PaymentRequest::find($businessId);
                if ($paymentRequest) {
                    if ($status === 'completed') {
                        $paymentRequest->update([
                            'status' => 'approved',
                            'approved_at' => now(),
                            'invoice_status' => 'pending_invoice', // 设置为待上传发票状态
                        ]);
                        Log::info('保险付款申请状态已更新为已批准', [
                            'payment_request_id' => $businessId
                        ]);
                        
                        // 创建付款回执待办任务
                        try {
                            PendingTaskService::createPaymentReceiptTask($paymentRequest);
                            Log::info('已创建付款回执待办任务', [
                                'payment_request_id' => $paymentRequest->id
                            ]);
                        } catch (\Exception $e) {
                            Log::error('创建付款回执待办任务失败', [
                                'payment_request_id' => $paymentRequest->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                        
                        // 自动生成出款汇总记录
                        try {
                            \App\Http\Controllers\PaymentSummaryController::createFromPaymentRequest($paymentRequest);
                        } catch (\Exception $e) {
                            Log::error('自动生成出款汇总记录失败', [
                                'payment_request_id' => $businessId,
                                'error' => $e->getMessage()
                            ]);
                        }
                    } elseif ($status === 'rejected') {
                        $paymentRequest->update([
                            'status' => 'rejected',
                            'rejection_reason' => '审批流程被驳回',
                        ]);
                    }
                }
                break;
            
            case '社保付款发票审批':
            case '公积金付款发票审批':
                // 发票审批完成，将发票附件并入原付款申请
                $paymentRequest = \App\Models\PaymentRequest::with('invoiceAttachments')->find($businessId);
                if ($paymentRequest) {
                    if ($status === 'completed') {
                        // 将发票附件复制到原付款申请附件表
                        foreach ($paymentRequest->invoiceAttachments as $invoiceAtt) {
                            \App\Models\PaymentRequestAttachment::create([
                                'payment_request_id' => $paymentRequest->id,
                                'filename' => $invoiceAtt->filename,
                                'file_path' => $invoiceAtt->file_path,
                                'file_size' => $invoiceAtt->file_size,
                                'mime_type' => $invoiceAtt->mime_type,
                                'uploaded_by' => $invoiceAtt->uploaded_by,
                                'attachment_type' => 'invoice', // 标记为发票类型
                            ]);
                        }
                        
                        // 删除发票凭证表中的记录（已并入原附件表）
                        \App\Models\PaymentRequestInvoiceAttachment::where('payment_request_id', $paymentRequest->id)->delete();
                        
                        // 更新发票状态为已审批
                        $paymentRequest->update([
                            'invoice_status' => 'invoice_approved',
                        ]);
                        
                        Log::info('发票审批完成，附件已并入原付款申请', [
                            'payment_request_id' => $businessId,
                            'invoice_attachments_count' => $paymentRequest->invoiceAttachments->count(),
                        ]);
                    } elseif ($status === 'rejected') {
                        $paymentRequest->update([
                            'invoice_status' => 'invoice_rejected',
                        ]);
                    }
                }
                break;
            
            case '发票申请':
            case '发票申请（重新提交）':
                // 更新发票申请的审批状态和业务状态
                $invoiceApplication = \App\Models\InvoiceApplication::find($businessId);
                if ($invoiceApplication) {
                    if ($status === 'completed') {
                        // 审批通过：审批状态=已通过，业务状态保持不变
                        $invoiceApplication->update([
                            'approval_status' => 'approved',
                        ]);
                        
                        // 【新增】自动创建发票汇总记录
                        try {
                            \App\Models\InvoiceSummary::createFromInvoiceApplication($invoiceApplication);
                            Log::info('发票申请审批通过，已创建汇总记录', [
                                'invoice_application_id' => $businessId
                            ]);
                        } catch (\Exception $e) {
                            Log::error('创建发票汇总记录失败', [
                                'invoice_application_id' => $businessId,
                                'error' => $e->getMessage()
                            ]);
                        }
                        
                        Log::info('发票申请审批已通过', [
                            'invoice_application_id' => $businessId,
                            'business_status' => $invoiceApplication->status  // 业务状态不变
                        ]);
                    } elseif ($status === 'rejected') {
                        // 驳回：审批状态=已驳回，业务状态=红冲
                        $invoiceApplication->update([
                            'approval_status' => 'rejected',
                            'status' => 'red_flushed',  // 业务状态改为红冲
                        ]);
                        Log::info('发票申请被驳回', [
                            'invoice_application_id' => $businessId,
                            'business_status' => 'red_flushed',
                            'approval_status' => 'rejected'
                        ]);
                    } elseif ($status === 'in_approval') {
                        // 审批中：审批状态=审批中，业务状态保持不变
                        $invoiceApplication->update([
                            'approval_status' => 'pending',
                        ]);
                    }
                }
                break;
            
            case '保险汇总':
                // 更新流程管理的状态
                $process = \App\Models\ProcessApproval::find($businessId);
                if ($process) {
                    $data = [];
                    
                    if ($status === 'in_approval') {
                        $data['status'] = 'pending';
                    } elseif ($status === 'completed') {
                        $data['status'] = 'approved';
                    } elseif ($status === 'rejected') {
                        $data['status'] = 'rejected';
                    }
                    
                    if ($instanceId) {
                        $data['approval_instance_id'] = $instanceId;
                    }
                    
                    if (!empty($data)) {
                        $process->update($data);
                        Log::info('流程管理状态已更新', [
                            'process_id' => $businessId,
                            'new_status' => $data['status'] ?? 'unknown'
                        ]);
                    }
                }
                break;
            
            case '付款申请':
                // 更新付款申请的状态
                $payment = \App\Models\PaymentRequest::find($businessId);
                if ($payment) {
                    $data = [];
                    
                    if ($status === 'in_approval') {
                        $data['status'] = 'pending';
                    } elseif ($status === 'completed') {
                        $data['status'] = 'approved';
                    } elseif ($status === 'rejected') {
                        $data['status'] = 'rejected';
                    }
                    
                    if ($instanceId) {
                        $data['approval_instance_id'] = $instanceId;
                    }
                    
                    if (!empty($data)) {
                        $payment->update($data);
                        Log::info('付款申请状态已更新', [
                            'payment_id' => $businessId,
                            'new_status' => $data['status'] ?? 'unknown'
                        ]);
                    }
                }
                break;
            
            case '考勤申请':
                // 更新考勤表的状态
                $sheet = \App\Models\AttendanceSheet::find($businessId);
                if ($sheet) {
                    $data = [];
                    
                    if ($status === 'in_approval') {
                        $data['status'] = 'submitted';
                    } elseif ($status === 'completed') {
                        $data['status'] = 'approved';
                        $data['approved_by'] = auth()->id();
                        $data['approved_at'] = now();
                    } elseif ($status === 'rejected') {
                        $data['status'] = 'rejected';
                    }
                    
                    if (!empty($data)) {
                        $sheet->update($data);
                        Log::info('考勤申请状态已更新', [
                            'sheet_id' => $businessId,
                            'new_status' => $data['status'] ?? 'unknown'
                        ]);
                        
                        // 【完成考勤表待办任务】
                        if ($status === 'completed') {
                            try {
                                PendingTaskService::checkAndCompleteAttendanceSheetTask($sheet);
                                Log::info('已完成考勤表待办任务', [
                                    'attendance_sheet_id' => $sheet->id
                                ]);
                            } catch (\Exception $e) {
                                Log::error('完成考勤表待办任务失败', [
                                    'attendance_sheet_id' => $businessId,
                                    'error' => $e->getMessage()
                                ]);
                            }
                        }
                    }
                }
                break;
            
            case '报销申请':
            case 'reimbursement':  // 兼容英文类型
                // 更新报销申请的状态
                $reimbursement = \App\Models\Reimbursement::find($businessId);
                if ($reimbursement) {
                    $data = [];
                    
                    if ($status === 'in_approval') {
                        $data['status'] = 'pending';
                    } elseif ($status === 'completed') {
                        $data['status'] = 'approved';
                    } elseif ($status === 'rejected') {
                        $data['status'] = 'rejected';
                    }
                    
                    if ($instanceId) {
                        $data['approval_flow_id'] = $instanceId;
                    }
                    
                    if (!empty($data)) {
                        $reimbursement->update($data);
                        Log::info('报销申请状态已更新', [
                            'reimbursement_id' => $businessId,
                            'new_status' => $data['status'] ?? 'unknown'
                        ]);
                    }
                }
                break;

            case 'material_request':
                // 资料申请（公章/营业执照等物品资料的借用归还）
                $materialRequest = \App\Models\MaterialRequest::with('items')->find($businessId);
                if ($materialRequest) {
                    if ($status === 'in_approval') {
                        // 申请中（审批中）
                        $update = ['status' => 'pending'];
                        if ($instanceId) {
                            $update['approval_instance_id'] = $instanceId;
                        }
                        $materialRequest->update($update);

                        // 明细设为申请中
                        \App\Models\MaterialRequestItem::where('material_request_id', $materialRequest->id)
                            ->update(['status' => 'pending']);

                        // 资料设为申请中
                        $assetIds = $materialRequest->items->pluck('material_asset_id')->toArray();
                        if (!empty($assetIds)) {
                            \App\Models\MaterialAsset::whereIn('id', $assetIds)->update(['status' => 'applying']);
                        }
                    } elseif ($status === 'completed') {
                        // 审批完成 -> 使用中
                        $materialRequest->update(['status' => 'in_use']);

                        \App\Models\MaterialRequestItem::where('material_request_id', $materialRequest->id)
                            ->where('status', 'pending')
                            ->update(['status' => 'in_use']);

                        $assetIds = $materialRequest->items->pluck('material_asset_id')->toArray();
                        if (!empty($assetIds)) {
                            \App\Models\MaterialAsset::whereIn('id', $assetIds)->update(['status' => 'in_use']);
                        }
                    } elseif ($status === 'rejected' || $status === 'withdrawn') {
                        // 驳回/撤回 -> 释放资料回归档
                        $materialRequest->update(['status' => $status]);

                        \App\Models\MaterialRequestItem::where('material_request_id', $materialRequest->id)
                            ->whereIn('status', ['pending', 'in_use'])
                            ->update(['status' => 'cancelled']);

                        $assetIds = $materialRequest->items->pluck('material_asset_id')->toArray();
                        if (!empty($assetIds)) {
                            \App\Models\MaterialAsset::whereIn('id', $assetIds)->update(['status' => 'archived']);
                        }
                    }
                }
                break;
            
            case 'travel_application':
            case '差旅申请':
                // 更新差旅申请的状态
                $travelApplication = \App\Models\TravelApplication::find($businessId);
                if ($travelApplication) {
                    $data = [];
                    
                    if ($status === 'in_approval') {
                        $data['status'] = 'in_approval';
                    } elseif ($status === 'completed') {
                        $data['status'] = 'approved';
                    } elseif ($status === 'rejected') {
                        $data['status'] = 'rejected';
                    } elseif ($status === 'withdrawn') {
                        $data['status'] = 'pending';
                    }
                    
                    if ($instanceId) {
                        $data['approval_flow_id'] = $instanceId;
                    }
                    
                    if (!empty($data)) {
                        $travelApplication->update($data);
                        Log::info('差旅申请状态已更新', [
                            'travel_application_id' => $businessId,
                            'new_status' => $data['status'] ?? 'unknown'
                        ]);
                    }
                }
                break;
            
            case 'employee_deletion':
                // 员工删除审批
                if ($status === 'completed') {
                    // 审批通过，执行删除
                    $employee = \App\Models\Employee::find($businessId);
                    if ($employee) {
                        $employeeName = $employee->name;
                        $employee->delete();
                        Log::info('员工删除审批通过，已删除员工', [
                            'employee_id' => $businessId,
                            'employee_name' => $employeeName
                        ]);
                    }
                } elseif ($status === 'rejected') {
                    // 审批驳回，不执行删除
                    Log::info('员工删除审批被驳回', [
                        'employee_id' => $businessId
                    ]);
                }
                break;

            case 'employee_salary_adjustment':
                $employee = \App\Models\Employee::find($businessId);
                if ($employee) {
                    $instance = $instanceId ? \App\Models\ApprovalInstance::find($instanceId) : null;
                    if ($status === 'completed' && $instance) {
                        $employee->update([
                            'basic_salary' => $instance->new_basic_salary,
                            'salary_items' => $instance->new_salary_items,
                        ]);
                        Log::info('员工工资调整审批通过，已更新工资', [
                            'employee_id' => $businessId,
                            'instance_id' => $instanceId,
                            'new_basic_salary' => $instance->new_basic_salary,
                        ]);
                    } elseif ($status === 'rejected') {
                        Log::info('员工工资调整审批被驳回', [
                            'employee_id' => $businessId,
                            'instance_id' => $instanceId,
                        ]);
                    }
                }
                break;

            case 'offline_onboarding':
                Log::info('进入 offline_onboarding case', [
                    'businessId' => $businessId,
                    'status' => $status
                ]);
                
                // 线下入职审批
                $employee = \App\Models\Employee::find($businessId);
                
                Log::info('查找员工结果', [
                    'employee_found' => $employee ? 'yes' : 'no',
                    'employee_id' => $employee ? $employee->id : null
                ]);
                
                if ($employee) {
                    Log::info('检查状态', [
                        'status' => $status,
                        'is_completed' => $status === 'completed' ? 'yes' : 'no'
                    ]);
                    
                    if ($status === 'completed') {
                        // 审批通过：执行入职操作（与劳动合同审批通过逻辑一致）
                        $onboardingDate = now();
                        $contractUploadDeadline = $onboardingDate->copy()->addDays(30);
                        
                        $employee->update([
                            'contract_status' => 'active',  // 设置为在职
                            'hire_date' => $onboardingDate,  // 设置入职日期（必填字段）
                            'is_offline_onboarding' => true,  // 标记为线下入职
                            'offline_onboarding_date' => $onboardingDate,  // 记录线下入职日期
                            'contract_upload_deadline' => $contractUploadDeadline,  // 30天后的截止日期
                            'contract_uploaded' => false,  // 合同未上传
                        ]);
                        
                        Log::info('线下入职审批通过，员工已入职', [
                            'employee_id' => $businessId,
                            'employee_name' => $employee->name,
                            'onboarding_date' => $onboardingDate->toDateTimeString(),
                            'contract_upload_deadline' => $contractUploadDeadline->toDateString(),
                        ]);
                        
                        // 获取审批实例以获取创建人信息
                        $instance = null;
                        if ($instanceId) {
                            $instance = ApprovalInstance::find($instanceId);
                        }
                        
                        // 创建线下合同上传待办任务
                        try {
                            PendingTaskService::createOfflineContractTask($employee);
                            Log::info('已创建线下合同上传待办任务', [
                                'employee_id' => $employee->id
                            ]);
                        } catch (\Exception $e) {
                            Log::error('创建线下合同上传待办任务失败', [
                                'employee_id' => $employee->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                        
                        // 自动导入保险信息到增减管理模块（与劳动合同审批通过逻辑一致）
                        // 创建一个虚拟合同对象用于调用autoImportInsuranceInfo
                        $virtualContract = new \stdClass();
                        $virtualContract->employee_id = $employee->id;
                        $virtualContract->contract_type = 'labor';
                        $virtualContract->id = null;  // 线下入职没有合同ID
                        $virtualContract->account_set_id = $employee->account_set_id;  // 使用员工的账套ID
                        $virtualContract->created_by = $instance ? $instance->created_by : null;  // 使用审批实例的创建人
                        
                        try {
                            $this->autoImportInsuranceInfo($virtualContract);
                            Log::info('线下入职：保险信息已自动导入', [
                                'employee_id' => $employee->id
                            ]);
                        } catch (\Exception $e) {
                            Log::error('线下入职：自动导入保险信息失败', [
                                'employee_id' => $employee->id,
                                'error' => $e->getMessage(),
                                'trace' => $e->getTraceAsString()
                            ]);
                        }
                        
                    } elseif ($status === 'rejected') {
                        // 审批驳回
                        Log::info('线下入职审批被驳回', [
                            'employee_id' => $businessId,
                            'employee_name' => $employee->name
                        ]);
                    } elseif ($status === 'withdrawn') {
                        // 审批撤回
                        Log::info('线下入职审批已撤回', [
                            'employee_id' => $businessId,
                            'employee_name' => $employee->name
                        ]);
                    }
                }
                break;
            
            // 后续添加其他业务类型
        }
    }
    
    /**
     * 自动推进到下一步审批
     * @param int $instanceId 审批实例ID
     */
    private function autoAdvanceToNextStep($instanceId)
    {
        $instance = ApprovalInstance::find($instanceId);
        if (!$instance) {
            Log::warning('自动推进失败：未找到审批实例', ['instance_id' => $instanceId]);
            return;
        }
        
        // 找到当前已审批的记录
        $currentRecord = ApprovalRecord::where('instance_id', $instanceId)
            ->where('status', 'approved')
            ->orderBy('step_order', 'desc')
            ->first();
            
        if (!$currentRecord) {
            Log::warning('自动推进失败：未找到已审批的记录', ['instance_id' => $instanceId]);
            return;
        }
        
        Log::info('找到已审批的记录', [
            'instance_id' => $instanceId,
            'step_order' => $currentRecord->step_order,
            'approver_name' => $currentRecord->approver_name
        ]);
        
        // 找到下一步待审批的记录
        $nextRecord = ApprovalRecord::where('instance_id', $instanceId)
            ->where('step_order', $currentRecord->step_order + 1)
            ->where('status', 'waiting')
            ->first();
            
        if ($nextRecord) {
            // 将下一步设为待审批
            $nextRecord->update(['status' => 'pending']);
            $instance->update(['current_step' => $nextRecord->step_order]);
            
            Log::info('自动推进到下一步审批', [
                'instance_id' => $instanceId,
                'current_step' => $nextRecord->step_order,
                'approver_id' => $nextRecord->approver_id,
                'approver_name' => $nextRecord->approver_name
            ]);
        } else {
            Log::warning('自动推进失败：未找到下一步待审批的记录', [
                'instance_id' => $instanceId,
                'current_step' => $currentRecord->step_order
            ]);
        }
    }
    
    /**
     * 自动导入保险信息到增减管理模块
     * @param EmployeeContract $contract 员工合同
     */
    private function autoImportInsuranceInfo($contract)
    {
        try {
            Log::info('开始自动导入保险信息', [
                'contract_id' => $contract->id,
                'employee_id' => $contract->employee_id,
                'account_set_id' => $contract->account_set_id
            ]);
            
            // 获取员工信息
            $employee = \App\Models\Employee::find($contract->employee_id);
            if (!$employee) {
                Log::warning('员工不存在，无法导入保险信息', ['employee_id' => $contract->employee_id]);
                return;
            }
            
            Log::info('找到员工信息', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_gender' => $employee->gender,
                'employee_birth_date' => $employee->birth_date,
                'employee_phone' => $employee->phone,
                'employee_status' => $employee->status
            ]);
            
            // 获取项目信息（取第一个项目）
            $project = $employee->projects->first();
            if (!$project) {
                Log::warning('员工未关联项目，无法导入保险信息', ['employee_id' => $contract->employee_id]);
                return;
            }
            
            Log::info('找到项目信息', [
                'project_id' => $project->id,
                'project_name' => $project->name
            ]);
            
            // 劳动合同审批通过后，自动创建参保人员信息（但不进行任何更新操作）
            Log::info('劳动合同审批通过，开始创建参保人员信息', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'contract_id' => $contract->id,
                'account_set_id' => $contract->account_set_id
            ]);
            
            // 创建新的保险变更记录
            $this->createNewInsuranceChange($employee, $project, $contract->account_set_id, $contract->created_by);
            
            // 自动创建基数调差记录（将员工创建时填写的基数作为新基数，直接生效）
            $this->autoCreateBaseAdjustment($employee, $contract->account_set_id, $contract->created_by);
            
        } catch (\Exception $e) {
            Log::error('自动导入保险信息失败', [
                'employee_id' => $contract->employee_id,
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 创建新的保险变更记录
     */
    private function createNewInsuranceChange($employee, $project, $accountSetId, $createdBy = null)
    {
        try {
            Log::info('开始创建新的保险变更记录', [
                'employee_id' => $employee->id,
                'project_id' => $project->id,
                'account_set_id' => $accountSetId
            ]);
            
            // 转换性别格式
            $genderValue = null;
            if ($employee->gender) {
                if (is_numeric($employee->gender)) {
                    $genderValue = (int)$employee->gender;
                } else {
                    // 字符串转数字：male/男 -> 1, female/女 -> 2
                    $genderStr = strtolower($employee->gender);
                    if (in_array($genderStr, ['male', '男', '1'])) {
                        $genderValue = 1;
                    } elseif (in_array($genderStr, ['female', '女', '2'])) {
                        $genderValue = 2;
                    }
                }
            }
            
            // 获取大额医疗保险基数（从员工表中获取）
            $largeMedicalBase = $employee->large_medical_base;
            
            $insuranceChange = \App\Models\InsuranceChange::create([
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,  // 员工姓名
                'employee_id_number' => $employee->id_number,  // 员工身份证号
                'employee_gender' => $genderValue,  // 员工性别（转换为数字）
                'employee_birth_date' => $employee->birth_date,  // 员工出生日期
                'employee_phone' => $employee->phone,  // 员工联系电话
                'employee_status' => $employee->status,  // 员工状态
                'project_id' => $project->id,
                'account_set_id' => $accountSetId,
                'change_type' => 'increase',  // 标识为新增记录
                'status' => 'pending', // 待处理状态
                'created_by' => $createdBy,
                // 大额医疗保险配置ID（如果员工有设置）
                'large_medical_insurance_config_id' => $employee->large_medical_insurance_config_id,
                // 大额医疗保险开关（默认关闭，需要手动开启）
                'large_medical_insurance_enabled' => false,
                // 员工基数字段
                'employee_social_security_base' => $employee->social_security_base,  // 员工社保基数
                'employee_medical_insurance_base' => $employee->medical_insurance_base,  // 员工医保基数
                'employee_housing_fund_base' => $employee->housing_fund_base,  // 员工公积金基数
                'employee_large_medical_base' => $largeMedicalBase,  // 员工大额医疗保险基数
            ]);
            
            // 自动计算并更新其他保险的保险金额，并记录使用的名额
            $usedQuotas = $this->updateOtherInsuranceAmounts($project, $accountSetId, $insuranceChange);
            
            // 如果有使用的名额，更新保险变更记录（与手动使用名额功能保持一致）
            if (!empty($usedQuotas)) {
                $insuranceChange->update(['used_quotas' => json_encode($usedQuotas)]);
                
                Log::info('保险变更记录已标记使用的名额', [
                    'insurance_change_id' => $insuranceChange->id,
                    'used_quotas' => $usedQuotas
                ]);
            }
            
            // 保存完整的保险配置快照
            $insuranceChange->saveCompleteInsuranceConfig();
            
            // 禁用保险配置变更检测
            // $insuranceChange->checkAndRecordChanges();
            
            Log::info('新的保险变更记录创建成功', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'project_id' => $project->id,
                'project_name' => $project->name,
                'insurance_change_id' => $insuranceChange->id,
                'account_set_id' => $accountSetId
            ]);
            
            return $insuranceChange;
            
        } catch (\Exception $e) {
            Log::error('创建新的保险变更记录失败', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    /**
     * 更新现有记录，显示保险信息变动
     */
    private function updateExistingChange($existingChange, $employee, $project, $accountSetId)
    {
        try {
            Log::info('开始更新现有记录', [
                'change_id' => $existingChange->id,
                'employee_id' => $employee->id,
                'current_status' => $existingChange->status
            ]);
            
            // 如果记录是"已处理"状态，不能修改，需要创建新记录
            if ($existingChange->status === 'completed') {
                Log::info('现有记录为已处理状态，将创建新的变更记录', [
                    'existing_change_id' => $existingChange->id,
                    'existing_status' => $existingChange->status,
                    'existing_created_at' => $existingChange->created_at,
                    'employee_id' => $employee->id,
                    'current_month' => date('Y-m')
                ]);
                
                // 创建新的变更记录
                $this->createNewInsuranceChange($employee, $project, $accountSetId, $existingChange->created_by);
                return;
            }
            
            // 获取当前的保险配置信息
            $currentInsuranceInfo = $this->getCurrentInsuranceInfo($employee, $project);
            
            // 比较保险信息是否有变动
            $changeDetails = $this->compareInsuranceChanges($existingChange, $currentInsuranceInfo);
            
            if (!empty($changeDetails)) {
                // 有变动，更新记录并标记为有变更
                $existingChange->update([
                    'change_summary' => $this->generateChangeSummary($changeDetails),
                    'parsed_change_details' => $changeDetails,
                    'last_snapshot' => json_encode($currentInsuranceInfo),
                    'updated_at' => now()
                ]);
                
                Log::info('记录已更新，显示保险信息变动', [
                    'change_id' => $existingChange->id,
                    'change_summary' => $existingChange->change_summary
                ]);
            } else {
                Log::info('保险信息无变动，无需更新', [
                    'change_id' => $existingChange->id
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('更新现有记录失败', [
                'change_id' => $existingChange->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 更新其他保险的保险金额和名额
     * 逻辑：
     * 1. 如果名额 > 0：名额减1，保险金额不变，记录使用的名额ID
     * 2. 如果名额 = 0：保险金额 = 当前金额 + 员工人均参保费用
     * 
     * @param Project $project 项目
     * @param int $accountSetId 账套ID
     * @param InsuranceChange $insuranceChange 保险变更记录
     * @return array 使用的名额ID列表（与手动使用名额功能保持一致）
     */
    private function updateOtherInsuranceAmounts($project, $accountSetId, $insuranceChange)
    {
        $usedQuotas = [];
        
        try {
            // 获取项目绑定的其他保险保单
            $otherInsurancePolicies = $project->otherInsurancePolicies()
                ->where('other_insurance_policies.account_set_id', $accountSetId)
                ->get();
                
            foreach ($otherInsurancePolicies as $policy) {
                $currentQuota = $policy->quota ?? 0;
                $currentAmount = $policy->coverage_amount ?? 0;
                $perCapitaCost = $policy->employee_per_capita_cost ?? 0;
                
                if ($currentQuota > 0) {
                    // 名额大于0：名额减1
                    $newQuota = $currentQuota - 1;
                    
                    // 处理人员姓名列表
                    $personnelNameList = $policy->personnel_name_list ?? [];
                    $removedPersonName = null;
                    
                    if (!empty($personnelNameList)) {
                        // 从人员姓名列表中删除第一个人员姓名
                        $removedPersonName = array_shift($personnelNameList);
                    }
                    
                    // 更新保单：名额减1，更新人员姓名列表
                    $policy->update([
                        'quota' => $newQuota,
                        'personnel_name_list' => $personnelNameList
                    ]);
                    
                    // 记录使用的名额ID和删除的人员姓名（与手动使用名额功能保持一致）
                    $usedQuotas[] = [
                        'policy_id' => $policy->id,
                        'removed_person_name' => $removedPersonName
                    ];
                    
                    Log::info('其他保险名额已减少', [
                        'policy_id' => $policy->id,
                        'policy_name' => $policy->policy_name,
                        'old_quota' => $currentQuota,
                        'new_quota' => $newQuota,
                        'coverage_amount' => $currentAmount,
                        'removed_person_name' => $removedPersonName,
                        'remaining_personnel_count' => count($personnelNameList),
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                        'insurance_change_id' => $insuranceChange->id
                    ]);
                }
                // 名额为0时不做任何处理，保险金额保持不变
            }
            
        } catch (\Exception $e) {
            Log::error('更新其他保险金额/名额失败', [
                'project_id' => $project->id,
                'account_set_id' => $accountSetId,
                'insurance_change_id' => $insuranceChange->id,
                'error' => $e->getMessage()
            ]);
        }
        
        return $usedQuotas;
    }
    
    /**
     * 获取当前保险配置信息
     */
    private function getCurrentInsuranceInfo($employee, $project)
    {
        $insuranceInfo = [];
        
        // 获取社保配置
        if ($employee->social_security_region_id) {
            $region = \App\Models\SocialSecurityRegion::with('socialSecurityTypes')->find($employee->social_security_region_id);
            if ($region) {
                $insuranceInfo['social_security_types'] = $region->socialSecurityTypes->map(function($type) {
                    return [
                        'id' => $type->id,
                        'name' => $type->name,
                        'base_amount' => $type->base_amount,
                        'employee_ratio' => $type->employee_ratio,
                        'company_ratio' => $type->company_ratio
                    ];
                })->toArray();
            }
        }
        
        // 获取医保配置
        if ($employee->medical_insurance_region_id) {
            $region = \App\Models\MedicalInsuranceRegion::with('medicalInsuranceTypes')->find($employee->medical_insurance_region_id);
            if ($region) {
                $insuranceInfo['medical_insurance_types'] = $region->medicalInsuranceTypes->map(function($type) {
                    return [
                        'id' => $type->id,
                        'name' => $type->name,
                        'base_amount' => $type->base_amount,
                        'employee_ratio' => $type->employee_ratio,
                        'company_ratio' => $type->company_ratio
                    ];
                })->toArray();
            }
        }
        
        // 获取公积金配置
        if ($employee->housing_fund_region_id) {
            $region = \App\Models\HousingFundRegion::find($employee->housing_fund_region_id);
            if ($region) {
                $insuranceInfo['housing_fund_params'] = [
                    'id' => $region->id,
                    'config_name' => $region->config_name,
                    'region_name' => $region->region_name,
                    'base_amount' => $region->base_amount,
                    'employee_ratio' => $region->employee_ratio,
                    'company_ratio' => $region->company_ratio
                ];
            }
        }
        
        // 获取其他保险配置
        $otherInsurancePolicies = $project->otherInsurancePolicies()->get();
        if ($otherInsurancePolicies->isNotEmpty()) {
            $insuranceInfo['other_insurance_policies'] = $otherInsurancePolicies->map(function($policy) {
                return [
                    'id' => $policy->id,
                    'name' => $policy->policy_name,
                    'type' => $policy->insurance_type,
                    'coverage' => $policy->coverage,
                    'employee_per_capita_cost' => $policy->employee_per_capita_cost,
                    'quota' => $policy->quota,
                    'available_quota' => $policy->quota
                ];
            })->toArray();
        }
        
        return $insuranceInfo;
    }
    
    /**
     * 比较保险信息变动
     */
    private function compareInsuranceChanges($existingChange, $currentInsuranceInfo)
    {
        $changeDetails = [];
        
        // 获取之前的快照
        $lastSnapshot = $existingChange->last_snapshot ? json_decode($existingChange->last_snapshot, true) : [];
        
        // 比较社保配置
        if (isset($currentInsuranceInfo['social_security_types']) || isset($lastSnapshot['social_security_types'])) {
            $oldTypes = $lastSnapshot['social_security_types'] ?? [];
            $newTypes = $currentInsuranceInfo['social_security_types'] ?? [];
            
            if ($oldTypes !== $newTypes) {
                $changeDetails[] = [
                    'category' => 'social_security',
                    'action' => 'modified',
                    'item' => '社保配置已变更'
                ];
            }
        }
        
        // 比较医保配置
        if (isset($currentInsuranceInfo['medical_insurance_types']) || isset($lastSnapshot['medical_insurance_types'])) {
            $oldTypes = $lastSnapshot['medical_insurance_types'] ?? [];
            $newTypes = $currentInsuranceInfo['medical_insurance_types'] ?? [];
            
            if ($oldTypes !== $newTypes) {
                $changeDetails[] = [
                    'category' => 'medical_insurance',
                    'action' => 'modified',
                    'item' => '医保配置已变更'
                ];
            }
        }
        
        // 比较公积金配置
        if (isset($currentInsuranceInfo['housing_fund_params']) || isset($lastSnapshot['housing_fund_params'])) {
            $oldParams = $lastSnapshot['housing_fund_params'] ?? [];
            $newParams = $currentInsuranceInfo['housing_fund_params'] ?? [];
            
            if ($oldParams !== $newParams) {
                $changeDetails[] = [
                    'category' => 'housing_fund',
                    'action' => 'modified',
                    'item' => '公积金配置已变更'
                ];
            }
        }
        
        // 比较其他保险配置
        if (isset($currentInsuranceInfo['other_insurance_policies']) || isset($lastSnapshot['other_insurance_policies'])) {
            $oldPolicies = $lastSnapshot['other_insurance_policies'] ?? [];
            $newPolicies = $currentInsuranceInfo['other_insurance_policies'] ?? [];
            
            if ($oldPolicies !== $newPolicies) {
                $changeDetails[] = [
                    'category' => 'other_insurance',
                    'action' => 'modified',
                    'item' => '其他保险配置已变更'
                ];
            }
        }
        
        return $changeDetails;
    }
    
    /**
     * 生成变更摘要
     */
    private function generateChangeSummary($changeDetails)
    {
        if (empty($changeDetails)) {
            return '';
        }
        
        $categories = [];
        foreach ($changeDetails as $detail) {
            $categoryMap = [
                'social_security' => '社保',
                'medical_insurance' => '医保',
                'housing_fund' => '公积金',
                'other_insurance' => '其他保险'
            ];
            
            $category = $categoryMap[$detail['category']] ?? $detail['category'];
            if (!in_array($category, $categories)) {
                $categories[] = $category;
            }
        }
        
        return implode('、', $categories) . '配置已变更';
    }
    
    /**
     * 生成工资汇总记录
     * 当工资表审批通过时，自动生成汇总记录
     */
    private function generateSalarySummaries($salaryApproval)
    {
        try {
            // 获取该工资表审批对应的所有工资记录
            $salaries = \App\Models\Salary::where('project_id', $salaryApproval->project_id)
                ->where('month', $salaryApproval->month)
                ->where('account_set_id', $salaryApproval->account_set_id)
                ->get();
            
            if ($salaries->isEmpty()) {
                Log::warning('工资表审批通过，但未找到工资记录', [
                    'salary_approval_id' => $salaryApproval->id,
                    'project_id' => $salaryApproval->project_id,
                    'month' => $salaryApproval->month
                ]);
                return;
            }
            
            // 按项目分组（因为可能是多项目工资表）
            $projectGroups = [];
            foreach ($salaries as $salary) {
                $projectId = $salary->project_id;
                if (!isset($projectGroups[$projectId])) {
                    $projectGroups[$projectId] = [];
                }
                $projectGroups[$projectId][] = $salary;
            }
            
            // 为每个项目生成一条汇总记录
            foreach ($projectGroups as $projectId => $projectSalaries) {
                $firstSalary = $projectSalaries[0];
                
                // 获取项目信息
                $project = \App\Models\Project::find($projectId);
                $projectName = $project ? $project->name : $firstSalary->department;
                
                // 获取项目设置信息
                $insuranceImportSetting = $project ? $project->insurance_import_month : 'current';
                
                // 获取参保地（从工资表关联的员工中获取）
                $socialSecurityLocation = '';
                $firstSalaryWithEmployee = \App\Models\Salary::where('project_id', $projectId)
                    ->where('month', $salaryApproval->month)
                    ->where('account_set_id', $salaryApproval->account_set_id)
                    ->whereNotNull('id_card')
                    ->first();
                
                if ($firstSalaryWithEmployee && $firstSalaryWithEmployee->id_card) {
                    $employee = \App\Models\Employee::where('id_number', $firstSalaryWithEmployee->id_card)->first();
                    if ($employee && $employee->socialSecurityRegion) {
                        $socialSecurityLocation = $employee->socialSecurityRegion->name;
                    }
                }
                
                $salaryPaymentDate = $project ? $project->salary_payment_date : null;
                $requiresSalaryBasis = $project ? $project->requires_salary_basis : false;
                
                // 检查是否上传了工资依据
                $salaryBasisUploaded = false;
                if ($requiresSalaryBasis) {
                    $salaryBasisUploaded = \App\Models\BasisRecord::where('project_id', $projectId)
                        ->where('month', $salaryApproval->month)
                        ->where('type', 'salary')
                        ->exists();
                }
                
                // 计算汇总数据
                $employeeCount = count($projectSalaries);
                
                // 将工资对象转换为数组以便计算
                $salaryArray = [];
                foreach ($projectSalaries as $salary) {
                    $salaryArray[] = $salary->toArray();
                }
                
                // 初始化所有合计字段为0
                $totals = [
                    // 考勤
                    'work_days' => 0,
                    'actual_work_days' => 0,
                    'absent_days' => 0,
                    'absent_deduction' => 0,
                    // 工资
                    'basic_salary' => 0,
                    'gross_salary' => 0,
                    // 累计
                    'cumulative_income' => 0,
                    'cumulative_basic_deduction' => 0,
                    'cumulative_special_deduction_insurance' => 0,
                    // 税务
                    'cumulative_tax_payable' => 0,
                    'tax_already_withheld' => 0,
                    // 保险合计
                    'company_insurance_total' => 0,
                    'personal_insurance_total' => 0,
                    // 专项扣除
                    'special_deduction_monthly' => 0,
                    'special_deduction' => 0,
                    // 其他
                    'taxable_income' => 0,
                    'cumulative_other_taxable' => 0,
                    'tax_payable_or_refundable' => 0,
                    'deductions' => 0,
                    'net_salary' => 0,
                    'paid_salary' => 0,
                    // 险种细分（需要从明细中计算，暂时设为0）
                    'pension_company' => 0,
                    'medical_company' => 0,
                    'unemployment_company' => 0,
                    'work_injury_company' => 0,
                    'maternity_company' => 0,
                    'pension_personal' => 0,
                    'medical_personal' => 0,
                    'unemployment_personal' => 0,
                    'housing_fund_company' => 0,
                    'housing_fund_personal' => 0,
                    'large_medical_company' => 0,
                    'large_medical_personal' => 0,
                    'social_security_compensation' => 0,
                    'housing_fund_compensation' => 0,
                ];
                
                // 累加计算
                foreach ($salaryArray as $salary) {
                    $totals['work_days'] += $salary['work_days'] ?? 0;
                    $totals['actual_work_days'] += $salary['actual_work_days'] ?? 0;
                    $totals['absent_days'] += $salary['absent_days'] ?? 0;
                    $totals['absent_deduction'] += $salary['absent_deduction'] ?? 0;
                    $totals['basic_salary'] += $salary['basic_salary'] ?? 0;
                    $totals['gross_salary'] += $salary['gross_salary'] ?? 0;
                    $totals['cumulative_income'] += $salary['cumulative_income'] ?? 0;
                    $totals['cumulative_basic_deduction'] += $salary['cumulative_basic_deduction'] ?? 0;
                    $totals['cumulative_special_deduction_insurance'] += $salary['cumulative_special_deduction_insurance'] ?? 0;
                    $totals['cumulative_tax_payable'] += $salary['cumulative_tax_payable'] ?? 0;
                    $totals['tax_already_withheld'] += $salary['tax_already_withheld'] ?? 0;
                    $totals['company_insurance_total'] += $salary['company_insurance_total'] ?? 0;
                    $totals['personal_insurance_total'] += $salary['personal_insurance_total'] ?? 0;
                    $totals['special_deduction_monthly'] += $salary['special_deduction_monthly'] ?? 0;
                    $totals['special_deduction'] += $salary['special_deduction'] ?? 0;
                    $totals['taxable_income'] += $salary['taxable_income'] ?? 0;
                    $totals['cumulative_other_taxable'] += $salary['cumulative_other_taxable'] ?? 0;
                    $totals['tax_payable_or_refundable'] += $salary['tax_payable_or_refundable'] ?? 0;
                    $totals['deductions'] += $salary['deductions'] ?? 0;
                    $totals['net_salary'] += $salary['net_salary'] ?? 0;
                    $totals['paid_salary'] += $salary['paid_salary'] ?? 0;
                    
                    // 公积金（个人和单位）
                    $totals['housing_fund_company'] += $salary['housing_fund'] ?? 0;
                    $totals['housing_fund_personal'] += $salary['housing_fund'] ?? 0;
                    
                    // 社保（个人） - 使用工资表中的social_security字段
                    // 注意：工资表中的social_security已经是个人社保合计，包含养老+医疗+失业
                    $socialSecurityPersonal = floatval($salary['social_security'] ?? 0);
                    
                    // 个人保险合计 = 社保个人 + 公积金个人 + 大额医疗个人
                    $personalInsuranceTotal = floatval($salary['personal_insurance_total'] ?? 0);
                    $housingFundPersonal = floatval($salary['housing_fund'] ?? 0);
                    
                    // 大额医疗个人 = 个人保险合计 - 社保个人 - 公积金个人
                    $largeMedicalPersonal = $personalInsuranceTotal - $socialSecurityPersonal - $housingFundPersonal;
                    $largeMedicalPersonal = max(0, $largeMedicalPersonal); // 确保不为负数
                    
                    // 累加细分数据（用于前端显示）
                    // 注意：工资表没有存储险种细分，这里用社保合计除以3作为近似值
                    $avgInsuranceAmount = $socialSecurityPersonal / 3;
                    $totals['pension_personal'] += $avgInsuranceAmount;
                    $totals['medical_personal'] += $avgInsuranceAmount;
                    $totals['unemployment_personal'] += $avgInsuranceAmount;
                    $totals['large_medical_personal'] += $largeMedicalPersonal;
                }
                
                // 调试日志：查看计算结果
                Log::info('========== 工资汇总计算结果 ==========', [
                    'project_id' => $projectId,
                    'project_name' => $projectName,
                    'month' => $salaryApproval->month,
                    'employee_count' => $employeeCount,
                    '养老保险_单位' => $totals['pension_company'],
                    '养老保险_个人' => $totals['pension_personal'],
                    '医疗保险_单位' => $totals['medical_company'],
                    '医疗保险_个人' => $totals['medical_personal'],
                    '失业保险_单位' => $totals['unemployment_company'],
                    '失业保险_个人' => $totals['unemployment_personal'],
                    '大额医疗_单位' => $totals['large_medical_company'],
                    '大额医疗_个人' => $totals['large_medical_personal'],
                    '公积金_单位' => $totals['housing_fund_company'],
                    '公积金_个人' => $totals['housing_fund_personal'],
                ]);
                
                // 创建或更新汇总记录（包含所有字段）
                \App\Models\SalarySummary::updateOrCreate(
                    [
                        'project_id' => $projectId,
                        'month' => $salaryApproval->month
                    ],
                    [
                        'account_set_id' => $salaryApproval->account_set_id,
                        'project_name' => $projectName,
                        'period_start' => $firstSalary->period_start,
                        'period_end' => $firstSalary->period_end,
                        'employee_count' => $employeeCount,
                        // 项目配置信息（快照）
                        'insurance_import_setting' => $insuranceImportSetting,
                        'social_security_location' => $socialSecurityLocation,
                        'salary_payment_day' => $salaryPaymentDate,
                        'requires_salary_basis' => $requiresSalaryBasis,
                        'salary_basis_uploaded' => $salaryBasisUploaded,
                        // 考勤
                        'total_work_days' => $totals['work_days'],
                        'total_actual_work_days' => $totals['actual_work_days'],
                        'total_absent_days' => $totals['absent_days'],
                        'total_absent_deduction' => $totals['absent_deduction'],
                        // 工资
                        'total_basic_salary' => $totals['basic_salary'],
                        'total_gross_salary' => $totals['gross_salary'],
                        // 累计
                        'total_cumulative_income' => $totals['cumulative_income'],
                        'total_cumulative_basic_deduction' => $totals['cumulative_basic_deduction'],
                        'total_cumulative_special_deduction_insurance' => $totals['cumulative_special_deduction_insurance'],
                        // 税率（暂时留空）
                        'avg_tax_rate' => null,
                        'avg_quick_deduction' => null,
                        'total_cumulative_tax_payable' => $totals['cumulative_tax_payable'],
                        'total_tax_already_withheld' => $totals['tax_already_withheld'],
                        // 社保单位（细分险种暂时为0）
                        'total_pension_company' => $totals['pension_company'],
                        'total_medical_company' => $totals['medical_company'],
                        'total_unemployment_company' => $totals['unemployment_company'],
                        'total_work_injury_company' => $totals['work_injury_company'],
                        'total_maternity_company' => $totals['maternity_company'],
                        // 社保个人（细分险种暂时为0）
                        'total_pension_personal' => $totals['pension_personal'],
                        'total_medical_personal' => $totals['medical_personal'],
                        'total_unemployment_personal' => $totals['unemployment_personal'],
                        // 公积金
                        'total_housing_fund_company' => $totals['housing_fund_company'],
                        'total_housing_fund_personal' => $totals['housing_fund_personal'],
                        // 大额医疗
                        'total_large_medical_company' => $totals['large_medical_company'],
                        'total_large_medical_personal' => $totals['large_medical_personal'],
                        // 补差
                        'total_social_security_compensation' => $totals['social_security_compensation'],
                        'total_housing_fund_compensation' => $totals['housing_fund_compensation'],
                        // 保险合计
                        'total_company_insurance_total' => $totals['company_insurance_total'],
                        'total_personal_insurance_total' => $totals['personal_insurance_total'],
                        // 专项扣除
                        'total_special_deduction_monthly' => $totals['special_deduction_monthly'],
                        'total_special_deduction' => $totals['special_deduction'],
                        // 税务
                        'total_taxable_income' => $totals['taxable_income'],
                        'total_cumulative_other_taxable' => $totals['cumulative_other_taxable'],
                        'total_tax_payable_or_refundable' => $totals['tax_payable_or_refundable'],
                        // 其他
                        'total_deductions' => $totals['deductions'],
                        'total_net_salary' => $totals['net_salary'],
                        'total_paid_salary' => $totals['paid_salary'],
                        // 状态
                        'status' => 'approved',
                        'salary_approval_id' => $salaryApproval->id,
                        'approved_at' => now()
                    ]
                );
                
                Log::info('工资汇总记录已生成', [
                    'project_id' => $projectId,
                    'project_name' => $projectName,
                    'month' => $salaryApproval->month,
                    'employee_count' => $employeeCount
                ]);
            }
            
        } catch (\Exception $e) {
            Log::error('生成工资汇总记录失败', [
                'salary_approval_id' => $salaryApproval->id,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    /**
     * 创建参保减少记录（离职/退休）
     */
    private function autoCreateDecreaseInsuranceRecord($contract, $employeeStatus)
    {
        try {
            Log::info('开始创建参保减少记录', [
                'contract_id' => $contract->id,
                'employee_id' => $contract->employee_id,
                'employee_status' => $employeeStatus
            ]);
            
            // 获取员工信息
            $employee = \App\Models\Employee::find($contract->employee_id);
            if (!$employee) {
                Log::warning('员工不存在，无法创建参保减少记录', ['employee_id' => $contract->employee_id]);
                return;
            }
            
            // 查找该员工当前的参保记录
            $personnelRecord = \App\Models\InsurancePersonnel::where('employee_id', $employee->id)
                ->where('account_set_id', $contract->account_set_id)
                ->where('status', 'active')
                ->first();
            
            if (!$personnelRecord) {
                Log::warning('员工没有活跃的参保记录，无需创建减少记录', [
                    'employee_id' => $employee->id,
                    'account_set_id' => $contract->account_set_id
                ]);
                return;
            }
            
            // 获取项目信息
            $project = $employee->projects->first();
            if (!$project) {
                Log::warning('员工未关联项目，无法创建参保减少记录', ['employee_id' => $contract->employee_id]);
                return;
            }
            
            // 转换性别格式
            $genderValue = null;
            if ($employee->gender) {
                if (is_numeric($employee->gender)) {
                    $genderValue = (int)$employee->gender;
                } else {
                    $genderStr = strtolower($employee->gender);
                    if (in_array($genderStr, ['male', '男', '1'])) {
                        $genderValue = 1;
                    } elseif (in_array($genderStr, ['female', '女', '2'])) {
                        $genderValue = 2;
                    }
                }
            }
            
            // 转换员工状态为整数值
            $employeeStatusValue = null;
            if ($employeeStatus === 'terminated') {
                $employeeStatusValue = 2; // 离职
            } elseif ($employeeStatus === 'retired') {
                $employeeStatusValue = 3; // 退休
            }
            
            // 创建参保减少记录
            $insuranceChange = \App\Models\InsuranceChange::create([
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_id_number' => $employee->id_number,
                'employee_gender' => $genderValue,
                'employee_birth_date' => $employee->birth_date,
                'employee_phone' => $employee->phone,
                'employee_status' => $employeeStatusValue,
                'project_id' => $project->id,
                'account_set_id' => $contract->account_set_id,
                'change_type' => 'decrease',  // 标识为减少记录
                'status' => 'pending',
                'created_by' => $contract->created_by,
                'notes' => $employeeStatus === 'terminated' ? '员工离职，停止参保' : '员工退休，停止参保',
                // 复制当前参保记录的保险配置
                'social_security_types' => $personnelRecord->social_security_types,
                'medical_insurance_types' => $personnelRecord->medical_insurance_types,
                'housing_fund_params' => $personnelRecord->housing_fund_params,
                'other_insurance_policies' => $personnelRecord->other_insurance_policies,
                'large_medical_insurance_config' => $personnelRecord->large_medical_insurance_config,
                'large_medical_insurance_config_id' => $personnelRecord->large_medical_insurance_config_id,
                'large_medical_insurance_enabled' => $personnelRecord->large_medical_insurance_enabled,
                'employee_social_security_base' => $personnelRecord->employee_social_security_base,
                'employee_medical_insurance_base' => $personnelRecord->employee_medical_insurance_base,
                'employee_housing_fund_base' => $personnelRecord->employee_housing_fund_base,
                'employee_large_medical_base' => $personnelRecord->employee_large_medical_base,
                'used_quotas' => $personnelRecord->used_quotas,
            ]);
            
            Log::info('参保减少记录创建成功', [
                'insurance_change_id' => $insuranceChange->id,
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'employee_status' => $employeeStatus
            ]);
            
        } catch (\Exception $e) {
            Log::error('创建参保减少记录失败', [
                'contract_id' => $contract->id,
                'employee_id' => $contract->employee_id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * 生成发工资表
     * 当工资表审批通过时，自动生成发工资表记录
     */
    private function generateSalaryPaymentRecords($salaryApproval)
    {
        try {
            // 获取该工资表审批对应的所有工资记录
            $salaries = \App\Models\Salary::where('project_id', $salaryApproval->project_id)
                                        ->where('month', $salaryApproval->month)
                                        ->get();

            if ($salaries->isEmpty()) {
                Log::warning('工资表中没有员工记录', [
                    'salary_approval_id' => $salaryApproval->id,
                    'project_id' => $salaryApproval->project_id,
                    'month' => $salaryApproval->month,
                ]);
                return;
            }

            // 为每个员工生成发工资记录
            foreach ($salaries as $salary) {
                $employee = $salary->employee;
                if (!$employee) {
                    continue;
                }

                // 检查是否已经存在该记录（避免重复生成）
                $exists = \App\Models\SalaryPaymentRecord::where('salary_id', $salary->id)
                                                        ->exists();
                if ($exists) {
                    continue;
                }

                // 创建发工资记录
                \App\Models\SalaryPaymentRecord::create([
                    'salary_id' => $salary->id,
                    'employee_id' => $employee->id,
                    'project_id' => $salaryApproval->project_id,
                    'month' => $salaryApproval->month,
                    'bank_account' => $employee->bank_account ?? '',
                    'bank_account_holder' => $employee->bank_account_holder ?? '',
                    'amount' => $salary->net_salary ?? 0, // 实发工资
                    'bank_name' => $employee->bank_name ?? '',
                    'bank_province' => $employee->bank_province ?? '',
                    'remittance_remark' => $employee->remittance_remark ?? '',
                    'account_set_id' => $salaryApproval->account_set_id,
                ]);
            }

            Log::info('发工资表生成成功', [
                'salary_approval_id' => $salaryApproval->id,
                'project_id' => $salaryApproval->project_id,
                'month' => $salaryApproval->month,
                'record_count' => $salaries->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('生成发工资表失败', [
                'salary_approval_id' => $salaryApproval->id,
                'project_id' => $salaryApproval->project_id,
                'month' => $salaryApproval->month,
                'error' => $e->getMessage(),
            ]);
        }
    }
    
    /**
     * 为驳回的审批创建考核记录
     * 
     * @param ApprovalInstance $instance 审批实例
     * @param ApprovalRecord $record 驳回的审批记录
     * @param string $comment 驳回原因
     */
    private function createAssessmentForRejection($instance, $record, $comment)
    {
        try {
            // 获取发起人信息
            $creator = \App\Models\User::find($instance->created_by);
            if (!$creator) {
                Log::warning('无法找到审批发起人', ['user_id' => $instance->created_by]);
                return;
            }
            
            // 构建业务名称
            $businessName = $this->getBusinessNameForAssessment($instance);
            
            // 构建考核描述
            $description = sprintf(
                '审批流程被驳回。业务类型：%s，驳回原因：%s，驳回人：%s',
                $instance->business_type,
                $comment,
                $record->approver_name
            );
            
            // 创建考核记录
            \App\Models\AssessmentRecord::create([
                'account_set_id' => $instance->account_set_id,
                'business_type' => 'approval_rejection', // 审批驳回类型
                'business_id' => $instance->id,
                'business_name' => $businessName,
                'handler_id' => $creator->id,
                'handler_name' => $creator->name,
                'deadline_date' => now(), // 驳回当天作为截止日期
                'actual_complete_date' => now(), // 已完成（驳回即完成）
                'overdue_days' => 0, // 驳回当天，不算超期
                'status' => 'completed', // 状态为已完成
                'remark' => $description
            ]);
            
            Log::info('审批驳回考核记录已创建', [
                'instance_id' => $instance->id,
                'business_type' => $instance->business_type,
                'handler_id' => $creator->id,
                'handler_name' => $creator->name,
                'approver_name' => $record->approver_name
            ]);
            
        } catch (\Exception $e) {
            Log::error('创建审批驳回考核记录失败', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 获取考核记录的业务名称
     */
    private function getBusinessNameForAssessment($instance)
    {
        $businessTypeMap = [
            'employee_contract' => '员工合同审批',
            '工资表审批' => '工资表审批',
            '工资付款申请' => '工资付款申请',
            '报销付款申请' => '报销付款申请',
            '保险汇总付款申请' => '保险汇总付款申请',
            '发票申请' => '发票申请',
            '保险汇总' => '保险汇总审批',
            '付款申请' => '付款申请',
            '考勤申请' => '考勤申请',
            '报销申请' => '报销申请',
            'reimbursement' => '报销申请',  // 兼容英文类型
            'employee_salary_adjustment' => '员工工资调整审批',
        ];
        
        $typeName = $businessTypeMap[$instance->business_type] ?? $instance->business_type;
        
        return sprintf('%s（ID:%d）被驳回', $typeName, $instance->business_id);
    }
    
    /**
     * 删除员工的基数调差记录
     * 当员工离职或退休时调用
     */
    private function deleteEmployeeCompensationRecords($employeeId)
    {
        try {
            $deletedCount = \App\Models\InsuranceCompensationRecord::where('employee_id', $employeeId)->delete();
            
            Log::info('已删除员工的基数调差记录', [
                'employee_id' => $employeeId,
                'deleted_count' => $deletedCount
            ]);
            
            return $deletedCount;
        } catch (\Exception $e) {
            Log::error('删除员工基数调差记录失败', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 0;
        }
    }
    
    /**
     * 自动创建基数调差记录
     * 当劳动合同审批通过后，将员工创建时填写的基数作为新基数，直接生效
     * 
     * @param \App\Models\Employee $employee 员工对象
     * @param int $accountSetId 账套ID
     * @param int|null $createdBy 创建人ID
     */
    private function autoCreateBaseAdjustment($employee, $accountSetId, $createdBy = null)
    {
        try {
            Log::info('开始自动创建基数调差记录', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'account_set_id' => $accountSetId,
                'social_security_base' => $employee->social_security_base,
                'medical_insurance_base' => $employee->medical_insurance_base,
                'housing_fund_base' => $employee->housing_fund_base,
                'large_medical_base' => $employee->large_medical_base,
                'large_medical_company_base' => $employee->large_medical_company_base,
            ]);

            $effectiveDate = now()->format('Y-m-d');
            $projectId = null;
            if (method_exists($employee, 'projects')) {
                $projectId = optional($employee->projects()->first())->id;
            }

            $definitions = [
                \App\Models\BaseAdjustment::TYPE_SOCIAL_SECURITY => [
                    'enabled' => !empty($employee->social_security_base) && $employee->social_security_base > 0,
                    'payload' => [
                        'old_social_security_base' => 0,
                        'new_social_security_base' => $employee->social_security_base,
                        'social_security_effective_date' => $effectiveDate,
                        'effective_date' => $effectiveDate,
                    ],
                ],
                \App\Models\BaseAdjustment::TYPE_MEDICAL_INSURANCE => [
                    'enabled' => !empty($employee->medical_insurance_base) && $employee->medical_insurance_base > 0,
                    'payload' => [
                        'old_medical_insurance_base' => 0,
                        'new_medical_insurance_base' => $employee->medical_insurance_base,
                        'medical_insurance_effective_date' => $effectiveDate,
                        'effective_date' => $effectiveDate,
                    ],
                ],
                \App\Models\BaseAdjustment::TYPE_HOUSING_FUND => [
                    'enabled' => !empty($employee->housing_fund_base) && $employee->housing_fund_base > 0,
                    'payload' => [
                        'old_housing_fund_base' => 0,
                        'new_housing_fund_base' => $employee->housing_fund_base,
                        'housing_fund_effective_date' => $effectiveDate,
                        'effective_date' => $effectiveDate,
                    ],
                ],
                \App\Models\BaseAdjustment::TYPE_LARGE_MEDICAL => [
                    'enabled' => (!empty($employee->large_medical_base) && $employee->large_medical_base > 0)
                        || (!empty($employee->large_medical_company_base) && $employee->large_medical_company_base > 0),
                    'payload' => [
                        'old_large_medical_base' => 0,
                        'new_large_medical_base' => $employee->large_medical_base ?: null,
                        'old_large_medical_company_base' => 0,
                        'new_large_medical_company_base' => $employee->large_medical_company_base ?: null,
                        'large_medical_effective_date' => $effectiveDate,
                        'effective_date' => $effectiveDate,
                    ],
                ],
            ];

            $createdRecords = [];

            foreach ($definitions as $type => $definition) {
                if (!$definition['enabled']) {
                    continue;
                }

                $existingPending = \App\Models\BaseAdjustment::pending()
                    ->where('employee_id', $employee->id)
                    ->where('account_set_id', $accountSetId)
                    ->get()
                    ->first(function (\App\Models\BaseAdjustment $record) use ($type) {
                        return $record->hasType($type);
                    });

                if ($existingPending) {
                    Log::info('员工已存在待生效的基数调差记录，跳过自动创建当前险种', [
                        'employee_id' => $employee->id,
                        'adjustment_type' => $type,
                        'existing_adjustment_id' => $existingPending->id,
                    ]);
                    continue;
                }

                $record = \App\Models\BaseAdjustment::create(array_merge(
                    \App\Models\BaseAdjustment::emptyTypePayload(),
                    [
                        'employee_id' => $employee->id,
                        'project_id' => $projectId,
                        'account_set_id' => $accountSetId,
                        'status' => 'applied',
                        'adjustment_reason' => '劳动合同审批通过，自动导入员工基数',
                        'created_by' => $createdBy,
                        'applied_at' => now(),
                    ],
                    $definition['payload']
                ));

                $createdRecords[] = [
                    'adjustment_id' => $record->id,
                    'adjustment_type' => $type,
                ];
            }

            if (empty($createdRecords)) {
                Log::info('员工没有可自动创建的新调基记录', [
                    'employee_id' => $employee->id,
                    'account_set_id' => $accountSetId,
                ]);

                return null;
            }

            Log::info('自动创建基数调差记录成功', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'records' => $createdRecords,
                'effective_date' => $effectiveDate,
            ]);

            return $createdRecords;
        } catch (\Exception $e) {
            Log::error('自动创建基数调差记录失败', [
                'employee_id' => $employee->id,
                'account_set_id' => $accountSetId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * 回退其他保险名额
     * 当员工离职或退休时，将其使用的名额回退到保单，并添加员工姓名到列表
     * 
     * @param Contract $contract 合同记录
     * @param Employee $employee 员工信息
     */
    private function returnOtherInsuranceQuota($contract, $employee)
    {
        try {
            Log::info('开始回退其他保险名额', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'contract_id' => $contract->id,
                'contract_type' => $contract->contract_type
            ]);
            
            // 查找该员工当前的参保记录
            $personnelRecord = \App\Models\InsurancePersonnel::where('employee_id', $employee->id)
                ->where('account_set_id', $contract->account_set_id)
                ->first(); // 不限制 status，因为可能已经被设置为 inactive
            
            if (!$personnelRecord) {
                Log::warning('员工没有参保记录，无需回退名额', [
                    'employee_id' => $employee->id,
                    'account_set_id' => $contract->account_set_id
                ]);
                return;
            }
            
            // 解析该员工使用过的名额
            $usedQuotas = $personnelRecord->used_quotas;
            if (empty($usedQuotas)) {
                Log::info('员工未使用任何保单名额，无需回退', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name
                ]);
                return;
            }
            
            // 如果是字符串，先解析
            if (is_string($usedQuotas)) {
                $usedQuotas = json_decode($usedQuotas, true);
            }
            
            if (!is_array($usedQuotas)) {
                Log::warning('used_quotas 格式错误', [
                    'employee_id' => $employee->id,
                    'used_quotas' => $usedQuotas
                ]);
                return;
            }
            
            // 遍历已使用的名额，逐个回退
            foreach ($usedQuotas as $quotaInfo) {
                if (!is_array($quotaInfo)) {
                    continue;
                }
                
                $policyId = $quotaInfo['policy_id'] ?? null;
                $removedPersonName = $quotaInfo['removed_person_name'] ?? null;
                
                if (!$policyId) {
                    continue;
                }
                
                $policy = \App\Models\OtherInsurancePolicy::find($policyId);
                if (!$policy) {
                    Log::warning('保单不存在，跳过回退', ['policy_id' => $policyId]);
                    continue;
                }
                
                // 1. 名额 +1
                $oldQuota = $policy->quota ?? 0;
                $newQuota = $oldQuota + 1;
                
                // 2. 将员工姓名添加到人员列表
                $personnelNameList = $policy->personnel_name_list ?? [];
                if (!is_array($personnelNameList)) {
                    $personnelNameList = [];
                }
                
                // 添加离职员工姓名到列表末尾
                $personnelNameList[] = $employee->name;
                
                // 3. 更新保单
                $policy->update([
                    'quota' => $newQuota,
                    'personnel_name_list' => $personnelNameList
                ]);
                
                Log::info('其他保险名额已回退', [
                    'policy_id' => $policy->id,
                    'policy_name' => $policy->policy_name,
                    'old_quota' => $oldQuota,
                    'new_quota' => $newQuota,
                    'returned_employee_name' => $employee->name,
                    'removed_person_name' => $removedPersonName,
                    'personnel_name_list_count' => count($personnelNameList),
                    'employee_id' => $employee->id,
                    'contract_id' => $contract->id
                ]);
            }
            
            Log::info('其他保险名额回退完成', [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name,
                'returned_quota_count' => count($usedQuotas)
            ]);
            
        } catch (\Exception $e) {
            Log::error('回退其他保险名额失败', [
                'employee_id' => $employee->id,
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 自动盖银行付讫章
     * 当审批流程最后一个节点通过时，检查审批人是否有银行付讫章
     * 如果有，则在附件中查找名称包含"付款申请单"的PDF文件并自动盖章
     */
    private function applyBankStampIfExists($instance, $approverId)
    {
        try {
            // 检查审批人是否有银行付讫章
            $bankStamp = \App\Models\UserBankStamp::where('user_id', $approverId)->first();
            
            if (!$bankStamp) {
                Log::info('审批人未设置银行付讫章，跳过自动盖章', [
                    'instance_id' => $instance->id,
                    'approver_id' => $approverId
                ]);
                return;
            }
            
            // 获取审批实例的附件
            $attachments = $instance->attachments;
            
            if (!$attachments || $attachments->isEmpty()) {
                Log::info('审批实例无附件，跳过自动盖章', [
                    'instance_id' => $instance->id
                ]);
                return;
            }
            
            // 查找名称包含"付款申请单"的PDF文件
            $targetAttachments = $attachments->filter(function ($attachment) {
                $fileName = $attachment->file_name ?? $attachment->original_name ?? '';
                return stripos($fileName, '付款申请单') !== false 
                    && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) === 'pdf';
            });
            
            if ($targetAttachments->isEmpty()) {
                Log::info('未找到付款申请单PDF文件，跳过自动盖章', [
                    'instance_id' => $instance->id
                ]);
                return;
            }
            
            // 银行付讫章图片路径
            $stampImagePath = storage_path('app/public/' . $bankStamp->image_path);
            
            if (!file_exists($stampImagePath)) {
                Log::error('银行付讫章图片不存在', [
                    'image_path' => $bankStamp->image_path
                ]);
                return;
            }
            
            // 对每个付款申请单PDF进行盖章
            foreach ($targetAttachments as $attachment) {
                $this->stampPdfWithBankStamp($attachment, $bankStamp, $stampImagePath);
            }
            
        } catch (\Exception $e) {
            // 盖章失败不影响审批流程
            Log::error('自动盖银行付讫章失败', [
                'instance_id' => $instance->id,
                'approver_id' => $approverId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * 在PDF上盖银行付讫章
     */
    private function stampPdfWithBankStamp($attachment, $bankStamp, $stampImagePath)
    {
        try {
            $pdfPath = storage_path('app/public/' . $attachment->file_path);
            
            if (!file_exists($pdfPath)) {
                Log::error('PDF文件不存在', [
                    'file_path' => $attachment->file_path
                ]);
                return;
            }
            
            // 使用 FPDI 进行PDF盖章
            $pdf = new \setasign\Fpdi\Fpdi();
            $pageCount = $pdf->setSourceFile($pdfPath);
            
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $pdf->importPage($pageNo);
                $size = $pdf->getTemplateSize($templateId);
                
                $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
                
                // 只在最后一页盖章
                if ($pageNo == $pageCount) {
                    // 计算盖章位置（从百分比转换为实际坐标）
                    $x = ($bankStamp->position_x / 100) * $size['width'];
                    $y = ($bankStamp->position_y / 100) * $size['height'];
                    
                    // 将像素转换为毫米（假设72dpi）
                    $stampWidth = $bankStamp->width * 0.3528;
                    $stampHeight = $bankStamp->height * 0.3528;
                    
                    $pdf->Image($stampImagePath, $x, $y, $stampWidth, $stampHeight);
                }
            }
            
            // 保存覆盖原文件
            $pdf->Output($pdfPath, 'F');
            
            Log::info('银行付讫章盖章成功', [
                'attachment_id' => $attachment->id,
                'file_name' => $attachment->file_name ?? $attachment->original_name,
                'position' => [
                    'x' => $bankStamp->position_x,
                    'y' => $bankStamp->position_y
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('PDF盖章失败', [
                'attachment_id' => $attachment->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
