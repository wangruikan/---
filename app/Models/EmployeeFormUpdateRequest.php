<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeFormUpdateRequest extends Model
{
    protected $fillable = [
        'account_set_id',
        'employee_id',
        'form_type',
        'original_data',
        'new_data',
        'reason',
        'status',
        'approval_instance_id',
        'created_by',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'original_data' => 'array',
        'new_data' => 'array',
        'approved_at' => 'datetime:Y-m-d H:i:s',
        'rejected_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function approvalInstance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
