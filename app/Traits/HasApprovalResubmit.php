<?php

namespace App\Traits;

use Illuminate\Support\Facades\Schema;

/**
 * 审批重新发起功能 Trait
 * 
 * 为业务模型提供统一的驳回后重新发起功能
 * 
 * 使用方法：
 * 1. 在业务模型中添加：use HasApprovalResubmit;
 * 2. 确保模型有 status 字段
 */
trait HasApprovalResubmit
{
    /**
     * 判断是否可以重新发起（驳回后可重新提交）
     * 
     * 注意：InvoiceApplication 模型有自己的 canResubmit() 实现，会覆盖此方法
     * 
     * @return bool
     */
    public function canResubmit()
    {
        return $this->status === 'rejected';
    }

    /**
     * 标记为已驳回状态
     * 
     * @param string|null $reason 驳回原因
     * @return void
     */
    public function markAsRejected($reason = null)
    {
        $updateData = ['status' => 'rejected'];
        
        // 检查表是否有 rejection_reason 字段
        if (Schema::hasColumn($this->getTable(), 'rejection_reason') && $reason) {
            $updateData['rejection_reason'] = $reason;
        }
        
        // 检查表是否有 approval_status 字段（如 InvoiceApplication）
        if (Schema::hasColumn($this->getTable(), 'approval_status')) {
            $updateData['approval_status'] = 'rejected';
        }
        
        // 特殊处理：InvoiceApplication 驳回时状态改为红冲
        if ($this->getTable() === 'invoice_applications') {
            $updateData['status'] = 'red_flushed';
        }
        
        $this->update($updateData);
    }

    /**
     * 重新发起审批（重置状态为待审批）
     * 
     * @return void
     */
    public function resetForResubmit()
    {
        $updateData = [
            'status' => 'pending',
        ];
        
        // 清空驳回原因
        if (Schema::hasColumn($this->getTable(), 'rejection_reason')) {
            $updateData['rejection_reason'] = null;
        }
        
        // 重置审批状态
        if (Schema::hasColumn($this->getTable(), 'approval_status')) {
            $updateData['approval_status'] = null;
        }
        
        // 清空旧的审批实例ID
        if (Schema::hasColumn($this->getTable(), 'approval_instance_id')) {
            $updateData['approval_instance_id'] = null;
        }

        // 兼容报销等使用 approval_flow_id 的业务
        if (Schema::hasColumn($this->getTable(), 'approval_flow_id')) {
            $updateData['approval_flow_id'] = null;
        }
        
        // 特殊处理：InvoiceApplication 重新发起时状态改为正常
        if ($this->getTable() === 'invoice_applications') {
            $updateData['status'] = 'normal';
        }
        
        $this->update($updateData);
    }
}
