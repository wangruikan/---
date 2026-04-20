<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Attendance extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '考勤';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'date' => '日期',
        'check_in_time' => '签到时间',
        'check_out_time' => '签退时间',
        'work_hours' => '工作时长',
        'overtime_hours' => '加班时长',
        'status' => '状态',
        'notes' => '备注',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        $employee = $this->employee;
        return $employee ? "{$employee->name} {$this->date}" : "ID:{$this->id}";
    }

    protected $table = 'attendance';

    protected $fillable = [
        'employee_id',
        'project_id',
        'date',
        'check_in_time',
        'check_out_time',
        'work_hours',
        'overtime_hours',
        'status',
        'notes',
        'account_set_id',  // 【账套关联】
    ];

    protected $casts = [
        'date' => 'date',
        'check_in_time' => 'datetime:H:i',
        'check_out_time' => 'datetime:H:i',
        'work_hours' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function calculateWorkHours()
    {
        if ($this->check_in_time && $this->check_out_time) {
            $checkIn = Carbon::parse($this->check_in_time);
            $checkOut = Carbon::parse($this->check_out_time);
            $this->work_hours = $checkOut->diffInHours($checkIn);
            $this->save();
        }
    }
}
