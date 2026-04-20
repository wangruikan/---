<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AccountSet extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '账套';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'name' => '账套名称',
        'code' => '账套编码',
        'description' => '描述',
        'company_name' => '公司名称',
        'tax_number' => '税号',
        'contact_person' => '联系人',
        'contact_phone' => '联系电话',
        'address' => '地址',
        'status' => '状态',
        'is_default' => '是否默认',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->name;
    }

    protected $fillable = [
        'name',
        'code',
        'description',
        'company_name',
        'tax_number',
        'contact_person',
        'contact_phone',
        'address',
        'status',
        'is_default',
        'created_by',
        'base_adjustment_months',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'base_adjustment_months' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 创建者关联
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 关联的用户
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'account_set_users')
            ->withPivot('role', 'is_default')
            ->withTimestamps();
    }

    /**
     * 关联的员工
     */
    public function employees()
    {
        return $this->hasMany(\App\Models\Employee::class);
    }

    /**
     * 关联的项目
     */
    public function projects()
    {
        return $this->hasMany(\App\Models\Project::class);
    }

    /**
     * 是否为活跃状态
     */
    public function isActive()
    {
        return $this->status === 'active';
    }

    /**
     * 设置为默认套账
     */
    public function setAsDefault()
    {
        // 先取消所有默认套账
        self::where('is_default', true)->update(['is_default' => false]);
        
        // 设置当前为默认
        $this->update(['is_default' => true]);
    }

    /**
     * 归档套账
     */
    public function archive()
    {
        if ($this->is_default) {
            throw new \Exception('默认套账不能归档');
        }
        
        $this->update(['status' => 'archived']);
    }

    /**
     * 检查当前月份是否允许基数调整
     */
    public function canAdjustBaseInCurrentMonth()
    {
        $currentMonth = (int) date('n'); // 获取当前月份 (1-12)
        
        // 如果没有设置调整月份，默认不允许调整
        if (empty($this->base_adjustment_months)) {
            return false;
        }
        
        // 检查当前月份是否在允许调整的月份列表中
        return in_array($currentMonth, $this->base_adjustment_months);
    }

    /**
     * 获取允许调整的月份文本
     */
    public function getAdjustmentMonthsTextAttribute()
    {
        if (empty($this->base_adjustment_months)) {
            return '未设置';
        }
        
        $monthNames = [
            1 => '1月', 2 => '2月', 3 => '3月', 4 => '4月',
            5 => '5月', 6 => '6月', 7 => '7月', 8 => '8月',
            9 => '9月', 10 => '10月', 11 => '11月', 12 => '12月'
        ];
        
        $months = array_map(function($month) use ($monthNames) {
            return $monthNames[$month] ?? $month . '月';
        }, $this->base_adjustment_months);
        
        return implode('、', $months);
    }
}

