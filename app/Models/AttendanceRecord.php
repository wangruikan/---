<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'attendance_sheet_id',
        'employee_id',
        'project_id',
        'date',
        'day_of_month',
        'status',
        'check_in_time',
        'check_out_time',
        'work_hours',
        'overtime_hours',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_time' => 'datetime:H:i',
        'check_out_time' => 'datetime:H:i',
        'work_hours' => 'decimal:2',
        'overtime_hours' => 'decimal:2',
    ];

    // 状态常量
    const STATUS_NORMAL = 'normal';
    const STATUS_LATE = 'late';
    const STATUS_EARLY = 'early';
    const STATUS_ABSENT = 'absent';
    const STATUS_LEAVE = 'leave';
    const STATUS_OFF = 'off';

    // 关联关系
    public function accountSet(): BelongsTo
    {
        return $this->belongsTo(AccountSet::class);
    }

    public function attendanceSheet(): BelongsTo
    {
        return $this->belongsTo(AttendanceSheet::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // 访问器
    public function getEmployeeNameAttribute()
    {
        return $this->employee ? $this->employee->name : '';
    }

    public function getProjectNameAttribute()
    {
        return $this->project ? $this->project->name : '';
    }

    public function getStatusTextAttribute()
    {
        $statusMap = [
            self::STATUS_NORMAL => '正常',
            self::STATUS_LATE => '迟到',
            self::STATUS_EARLY => '早退',
            self::STATUS_ABSENT => '缺勤',
            self::STATUS_LEAVE => '请假',
            self::STATUS_OFF => '调休',
        ];

        return $statusMap[$this->status] ?? '未知';
    }

    public function getStatusTypeAttribute()
    {
        $typeMap = [
            self::STATUS_NORMAL => 'success',
            self::STATUS_LATE => 'warning',
            self::STATUS_EARLY => 'warning',
            self::STATUS_ABSENT => 'danger',
            self::STATUS_LEAVE => 'info',
            self::STATUS_OFF => 'info',
        ];

        return $typeMap[$this->status] ?? 'info';
    }

    // 作用域
    public function scopeByAccountSet($query, $accountSetId)
    {
        return $query->where('account_set_id', $accountSetId);
    }

    public function scopeByAttendanceSheet($query, $attendanceSheetId)
    {
        return $query->where('attendance_sheet_id', $attendanceSheetId);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // 方法
    public function isWorkDay()
    {
        return in_array($this->status, [self::STATUS_NORMAL, self::STATUS_LATE, self::STATUS_EARLY]);
    }

    public function isAbsent()
    {
        return $this->status === self::STATUS_ABSENT;
    }

    public function isLeave()
    {
        return $this->status === self::STATUS_LEAVE;
    }

    public function isOff()
    {
        return $this->status === self::STATUS_OFF;
    }

    public function calculateWorkHours()
    {
        if (!$this->check_in_time || !$this->check_out_time) {
            return 0;
        }

        $checkIn = \Carbon\Carbon::parse($this->check_in_time);
        $checkOut = \Carbon\Carbon::parse($this->check_out_time);
        
        return $checkOut->diffInHours($checkIn);
    }
}
