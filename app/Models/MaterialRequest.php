<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class MaterialRequest extends Model
{
    use Auditable;

    protected $auditName = '资料申请';
    
    protected $auditableFields = [
        'reason' => '申请原因',
        'expected_return_date' => '预计归还日期',
        'status' => '状态'
    ];

    protected $table = 'material_requests';

    protected $fillable = [
        'account_set_id',
        'applicant_id',
        'reason',
        'expected_return_date',
        'status',
        'approval_instance_id',
        'archived_at',
    ];

    protected $casts = [
        'expected_return_date' => 'date:Y-m-d',
        'archived_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->applicant ? "{$this->applicant->name}的申请" : "ID:{$this->id}";
    }

    public function applicant()
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function items()
    {
        return $this->hasMany(MaterialRequestItem::class, 'material_request_id');
    }

    public function approvalInstance()
    {
        return $this->belongsTo(ApprovalInstance::class, 'approval_instance_id');
    }
}

