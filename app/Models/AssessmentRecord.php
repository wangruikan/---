<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\AssessmentAppeal;

class AssessmentRecord extends Model
{
    protected $table = 'assessment_records';

    protected $fillable = [
        'account_set_id',
        'business_type',
        'business_id',
        'business_name',
        'handler_id',
        'handler_name',
        'deadline_date',
        'actual_complete_date',
        'overdue_days',
        'status',
        'remark'
    ];

    protected $casts = [
        'deadline_date' => 'date',
        'actual_complete_date' => 'datetime',
        'overdue_days' => 'integer'
    ];

    protected $appends = [
        'business_type_text',
        'status_text',
        'can_appeal'
    ];

    // 状态文本
    public function getStatusTextAttribute()
    {
        $statusMap = [
            'pending' => '待处理',
            'overdue' => '已超期',
            'completed' => '已完成'
        ];
        return $statusMap[$this->status] ?? '未知';
    }

    // 业务类型文本
    public function getBusinessTypeTextAttribute()
    {
        $typeMap = [
            'insurance_enrollment' => '参保入职',
            'contract_signing' => '合同签署',
            'salary_payment' => '工资发放',
            'invoice_processing' => '发票处理',
            'document_upload' => '资料收集',
            'contract_management' => '合同管理',
            'document_delivery' => '资料交付',
            'payment_request_missing' => '付款申请缺失',
            'resignation_contract' => '离职合同',
            'probation_management' => '试用期管理',
            'material_request' => '资料申请',
            'approval_request' => '审批申请',
            'reimbursement_request' => '报销申请',
            'travel_request' => '差旅申请',
            'invoice_request' => '发票申请',
            'payment_application' => '付款申请'
        ];
        return $typeMap[$this->business_type] ?? $this->business_type;
    }

    public function appeals()
    {
        return $this->hasMany(AssessmentAppeal::class, 'assessment_record_id');
    }

    public function latestAppeal()
    {
        return $this->hasOne(AssessmentAppeal::class, 'assessment_record_id')->latest('id');
    }

    public function getCanAppealAttribute()
    {
        $latestAppeal = $this->latestAppeal()->first();

        return !$latestAppeal;
    }

    // 计算超期天数
    public function calculateOverdueDays()
    {
        if ($this->actual_complete_date) {
            // 如果已完成，计算实际完成时间与截止时间的差值
            $deadline = Carbon::parse($this->deadline_date)->endOfDay();
            $actual = Carbon::parse($this->actual_complete_date);
            $days = $deadline->diffInDays($actual, false);
            return $days < 0 ? abs($days) : 0;
        } else {
            // 如果未完成，计算当前时间与截止时间的差值
            $deadline = Carbon::parse($this->deadline_date)->endOfDay();
            $now = Carbon::now();
            $days = $deadline->diffInDays($now, false);
            return $days < 0 ? abs($days) : 0;
        }
    }

    // 更新状态和超期天数
    public function updateStatus()
    {
        $this->overdue_days = $this->calculateOverdueDays();

        if ($this->actual_complete_date) {
            $this->status = 'completed';
        } else {
            $this->status = $this->overdue_days > 0 ? 'overdue' : 'pending';
        }

        $this->save();
    }

    // 批量更新所有待处理和已超期记录的状态
    public static function updateAllPendingStatus($accountSetId = null)
    {
        $query = self::whereIn('status', ['pending', 'overdue']);
        
        if ($accountSetId) {
            $query->where('account_set_id', $accountSetId);
        }

        $records = $query->get();
        foreach ($records as $record) {
            $record->updateStatus();
        }

        return $records->count();
    }
}