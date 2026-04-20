<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentDelivery extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '资料交付';
    
    protected $auditableFields = [
        'delivery_cycle' => '交付周期',
        'delivery_method' => '交付方式',
        'delivery_period' => '交付期间',
        'status' => '状态',
        'express_number' => '快递单号',
        'express_date' => '快递日期',
        'remarks' => '备注'
    ];

    protected $fillable = [
        'account_set_id',
        'project_id',
        'delivery_cycle',
        'delivery_method',
        'delivery_period',
        'status',
        'required_documents',
        'submitted_documents',
        'express_number',
        'express_date',
        'submitted_by',
        'submitted_at',
        'completed_by',
        'completed_at',
        'remarks',
    ];

    protected $casts = [
        'required_documents' => 'array',
        'express_date' => 'date',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->project ? "{$this->project->name} - {$this->delivery_period}" : "ID:{$this->id}";
    }

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    // 关联项目
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    // 关联提交人
    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    // 关联完成人
    public function completer()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    // 关联附件
    public function attachments()
    {
        return $this->hasMany(DocumentDeliveryAttachment::class, 'delivery_id');
    }

    // 关联提醒记录
    public function reminders()
    {
        return $this->hasMany(DocumentDeliveryReminder::class, 'delivery_id');
    }
}

