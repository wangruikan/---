<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class BidProject extends Model
{
    use Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '投标项目';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'project_code' => '项目编号',
        'project_name' => '项目名称',
        'project_category' => '项目类别',
        'client_name' => '客户名称',
        'client_contact' => '客户联系人',
        'client_phone' => '客户电话',
        'project_budget' => '项目预算',
        'bid_bond' => '投标保证金',
        'bond_paid_at' => '保证金支付时间',
        'bond_refunded_at' => '保证金退还时间',
        'project_location' => '项目地点',
        'project_scale' => '项目规模',
        'service_period' => '服务期限',
        'bid_deadline' => '投标截止时间',
        'bid_opening_time' => '开标时间',
        'bid_method' => '投标方式',
        'information_source' => '信息来源',
        'status' => '状态',
        'bid_result' => '投标结果',
        'win_amount' => '中标金额',
        'win_date' => '中标日期',
        'contract_signed_at' => '合同签订时间',
        'contract_number' => '合同编号',
        'contract_amount' => '合同金额',
        'remarks' => '备注',
        'responsible_person' => '负责人',
        'responsible_department' => '负责部门',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->project_name;
    }
    protected $table = 'bid_projects';

    protected $fillable = [
        'account_set_id',
        'project_code',
        'project_name',
        'project_category',
        'client_name',
        'client_contact',
        'client_phone',
        'project_budget',
        'bid_bond',
        'bond_paid_at',
        'bond_refunded_at',
        'project_location',
        'project_scale',
        'service_period',
        'bid_deadline',
        'bid_opening_time',
        'bid_method',
        'information_source',
        'status',
        'bid_result',
        'win_amount',
        'win_date',
        'contract_signed_at',
        'contract_number',
        'contract_amount',
        'remarks',
        'responsible_person',
        'responsible_department',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'project_budget' => 'decimal:2',
        'bid_bond' => 'decimal:2',
        'win_amount' => 'decimal:2',
        'contract_amount' => 'decimal:2',
        'bond_paid_at' => 'datetime',
        'bond_refunded_at' => 'datetime',
        'bid_deadline' => 'datetime',
        'bid_opening_time' => 'datetime',
        'contract_signed_at' => 'datetime',
        'win_date' => 'date',
    ];

    // 状态常量
    const STATUS_PREPARING = 'preparing';      // 准备中
    const STATUS_SUBMITTED = 'submitted';      // 已提交
    const STATUS_OPENED = 'opened';            // 已开标
    const STATUS_EVALUATING = 'evaluating';    // 评标中
    const STATUS_WON = 'won';                  // 已中标
    const STATUS_LOST = 'lost';                // 未中标
    const STATUS_ABANDONED = 'abandoned';      // 已放弃
    const STATUS_CONTRACTED = 'contracted';    // 已签约
    const STATUS_COMPLETED = 'completed';      // 已完成
    const STATUS_CANCELLED = 'cancelled';      // 已取消

    // 投标结果常量
    const RESULT_WON = 'won';                  // 中标
    const RESULT_LOST = 'lost';                // 未中标
    const RESULT_ABANDONED = 'abandoned';      // 放弃

    /**
     * 获取状态文本
     */
    public static function getStatusText($status)
    {
        $statusMap = [
            self::STATUS_PREPARING => '准备中',
            self::STATUS_SUBMITTED => '已提交',
            self::STATUS_OPENED => '已开标',
            self::STATUS_EVALUATING => '评标中',
            self::STATUS_WON => '已中标',
            self::STATUS_LOST => '未中标',
            self::STATUS_ABANDONED => '已放弃',
            self::STATUS_CONTRACTED => '已签约',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消',
        ];
        return $statusMap[$status] ?? $status;
    }

    /**
     * 获取投标结果文本
     */
    public static function getResultText($result)
    {
        $resultMap = [
            self::RESULT_WON => '中标',
            self::RESULT_LOST => '未中标',
            self::RESULT_ABANDONED => '放弃',
        ];
        return $resultMap[$result] ?? '';
    }

    /**
     * 关联：投标文件
     */
    public function documents()
    {
        return $this->hasMany(BidDocument::class, 'bid_project_id');
    }

    /**
     * 关联：进度记录
     */
    public function progressLogs()
    {
        return $this->hasMany(BidProgressLog::class, 'bid_project_id');
    }

    /**
     * 关联：提醒
     */
    public function reminders()
    {
        return $this->hasMany(BidReminder::class, 'bid_project_id');
    }

    /**
     * 关联：创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 关联：更新人
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * 生成项目编号
     */
    public static function generateProjectCode()
    {
        $date = date('Ymd');
        $prefix = 'BID' . $date;
        
        // 查找今天最后一个编号
        $lastProject = self::where('project_code', 'like', $prefix . '%')
            ->orderBy('project_code', 'desc')
            ->first();
        
        if ($lastProject) {
            $lastNumber = intval(substr($lastProject->project_code, -4));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }
        
        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * 检查是否即将到期（3天内）
     */
    public function isDeadlineApproaching()
    {
        if (!$this->bid_deadline) {
            return false;
        }
        
        $now = now();
        $deadline = $this->bid_deadline;
        $diffInDays = $now->diffInDays($deadline, false);
        
        return $diffInDays >= 0 && $diffInDays <= 3;
    }

    /**
     * 检查是否已过期
     */
    public function isOverdue()
    {
        if (!$this->bid_deadline) {
            return false;
        }
        
        return now()->gt($this->bid_deadline);
    }
}

