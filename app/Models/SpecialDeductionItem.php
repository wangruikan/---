<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SpecialDeductionItem extends Model
{
    protected $table = 'special_deduction_items';

    protected $fillable = [
        'account_set_id',
        'project_id',
        'name',
        'amount',
        'description',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 暂时注释掉appends，避免序列化时出现问题
    // protected $appends = ['project_name', 'creator_name'];

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    // 关联项目
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    // 关联创建人（已删除created_by字段，此方法保留但不会使用）
    // public function creator()
    // {
    //     return $this->belongsTo(User::class, 'created_by');
    // }

    // 关联员工专项扣除
    public function employeeDeductions()
    {
        return $this->hasMany(EmployeeSpecialDeduction::class, 'deduction_item_id');
    }

    // 获取项目名称
    public function getProjectNameAttribute()
    {
        return '通用'; // 所有扣除项目都是通用的
    }

    // 获取创建人名称（已删除created_by字段，此方法不再使用）
    // public function getCreatorNameAttribute()
    // {
    //     return $this->creator ? $this->creator->name : '未知';
    // }

    // 获取状态文本
    public function getStatusTextAttribute()
    {
        return $this->is_active ? '启用' : '停用';
    }
}
