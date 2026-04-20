<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceStatistics extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'attendance_sheet_id',
        'employee_id',
        'project_id',
        'work_days',
        'actual_work_days',
        'absent_days',
        'late_count',
        'early_count',
        'leave_days',
        'off_days',
        'attendance_rate',
        'total_work_hours',
        'total_overtime_hours'
    ];

    protected $casts = [
        'attendance_rate' => 'decimal:4',
        'total_work_hours' => 'decimal:2',
        'total_overtime_hours' => 'decimal:2',
    ];

    protected $appends = [
        'employee_name',
        'project_name'
    ];

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

    // 访问器
    public function getEmployeeNameAttribute()
    {
        return $this->employee ? $this->employee->name : '';
    }

    public function getProjectNameAttribute()
    {
        return $this->project ? $this->project->name : '';
    }

    public function getAttendanceRatePercentAttribute()
    {
        return round($this->attendance_rate * 100, 1);
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

    // 方法
    public function calculateAttendanceRate()
    {
        if ($this->work_days == 0) {
            return 0;
        }

        return $this->actual_work_days / $this->work_days;
    }

    public function updateStatistics()
    {
        $this->attendance_rate = $this->calculateAttendanceRate();
        $this->save();
    }

    public static function generateStatistics($attendanceSheetId, $employeeId)
    {
        $sheet = AttendanceSheet::find($attendanceSheetId);
        if (!$sheet) {
            return null;
        }

        $records = AttendanceRecord::where('attendance_sheet_id', $attendanceSheetId)
            ->where('employee_id', $employeeId)
            ->get();

        $workDays = $sheet->work_days;
        $actualWorkDays = $records->whereIn('status', ['normal', 'late', 'early'])->count();
        $absentDays = $records->where('status', 'absent')->count();
        $lateCount = $records->where('status', 'late')->count();
        $earlyCount = $records->where('status', 'early')->count();
        $leaveDays = $records->where('status', 'leave')->count();
        $offDays = $records->where('status', 'off')->count();
        $totalWorkHours = $records->sum('work_hours');
        $totalOvertimeHours = $records->sum('overtime_hours');

        $attendanceRate = $workDays > 0 ? $actualWorkDays / $workDays : 0;

        return self::updateOrCreate(
            [
                'attendance_sheet_id' => $attendanceSheetId,
                'employee_id' => $employeeId,
            ],
            [
                'account_set_id' => $sheet->account_set_id,
                'project_id' => $sheet->project_id,
                'work_days' => $workDays,
                'actual_work_days' => $actualWorkDays,
                'absent_days' => $absentDays,
                'late_count' => $lateCount,
                'early_count' => $earlyCount,
                'leave_days' => $leaveDays,
                'off_days' => $offDays,
                'attendance_rate' => $attendanceRate,
                'total_work_hours' => $totalWorkHours,
                'total_overtime_hours' => $totalOvertimeHours,
            ]
        );
    }
}
