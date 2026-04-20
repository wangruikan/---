<?php

namespace App\Models;

use App\Traits\HasApprovalResubmit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSheet extends Model
{
    use HasFactory, HasApprovalResubmit;

    protected $fillable = [
        'account_set_id',
        'project_id',
        'month',
        'work_days',
        'total_employees',
        'status',
        'notes',
        'attachments',
        'created_by',
        'submitted_by',
        'approved_by',
        'submitted_at',
        'approved_at'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'attachments' => 'array',
    ];

    // 状态常量
    const STATUS_DRAFT = 'draft';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';

    // 关联关系
    public function accountSet(): BelongsTo
    {
        return $this->belongsTo(AccountSet::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function attendanceStatistics(): HasMany
    {
        return $this->hasMany(AttendanceStatistics::class);
    }

    // 访问器
    public function getProjectNameAttribute()
    {
        return $this->project ? $this->project->name : '';
    }

    public function getStatusTextAttribute()
    {
        $statusMap = [
            self::STATUS_DRAFT => '草稿',
            self::STATUS_SUBMITTED => '已提交',
            self::STATUS_APPROVED => '已审批',
            self::STATUS_REJECTED => '已拒绝',
        ];

        return $statusMap[$this->status] ?? '未知';
    }

    public function getStatusTypeAttribute()
    {
        $typeMap = [
            self::STATUS_DRAFT => 'info',
            self::STATUS_SUBMITTED => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
        ];

        return $typeMap[$this->status] ?? 'info';
    }

    // 作用域
    public function scopeByAccountSet($query, $accountSetId)
    {
        return $query->where('account_set_id', $accountSetId);
    }

    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByMonth($query, $month)
    {
        return $query->where('month', $month);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // 方法
    public function canEdit()
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canSubmit()
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canApprove()
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function submit($userId, $submitData = [])
    {
        if (!$this->canSubmit()) {
            throw new \Exception('当前状态不允许提交');
        }

        $updateData = [
            'status' => self::STATUS_SUBMITTED,
            'submitted_by' => $userId,
            'submitted_at' => now(),
        ];

        // 如果有提交说明，保存到notes字段
        if (isset($submitData['notes']) && $submitData['notes']) {
            $updateData['notes'] = $submitData['notes'];
        }

        // 如果有附件，保存到attachments字段
        if (isset($submitData['attachments']) && is_array($submitData['attachments'])) {
            $updateData['attachments'] = json_encode($submitData['attachments']);
        }

        $this->update($updateData);
    }

    public function approve($userId)
    {
        if (!$this->canApprove()) {
            throw new \Exception('当前状态不允许审批');
        }

        $this->update([
            'status' => self::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }

    public function reject($userId)
    {
        if (!$this->canReject()) {
            throw new \Exception('当前状态不允许拒绝');
        }

        $this->update([
            'status' => self::STATUS_REJECTED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);
    }
}