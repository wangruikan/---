<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TaxDeclarationTask extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '税费申报任务';

    protected $fillable = [
        'account_set_id',
        'config_id',
        'company_name',
        'tax_category_ids',
        'declaration_date',
        'year',
        'handler_id',
        'handler_name',
        'status',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'tax_category_ids' => 'array',
        'declaration_date' => 'datetime:Y-m-d',
        'completed_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $auditableFields = [
        'company_name' => '公司名称',
        'status' => '状态',
    ];

    public function getAuditIdentifier()
    {
        return $this->company_name . ' - ' . $this->declaration_date->format('Y-m-d');
    }

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 关联配置
     */
    public function config()
    {
        return $this->belongsTo(TaxDeclarationConfig::class, 'config_id');
    }

    /**
     * 关联操作员
     */
    public function handler()
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    /**
     * 关联完成人
     */
    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * 关联附件
     */
    public function attachments()
    {
        return $this->hasMany(TaxDeclarationAttachment::class, 'task_id');
    }

    /**
     * 获取税种列表
     */
    public function getTaxCategoriesAttribute()
    {
        if (empty($this->tax_category_ids)) {
            return collect();
        }
        
        return TaxCategory::whereIn('id', $this->tax_category_ids)->get();
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        $statuses = [
            'pending' => '待处理',
            'completed' => '已完成',
        ];
        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * 标记为已完成
     */
    public function markAsCompleted($userId)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $userId,
        ]);
    }
}
