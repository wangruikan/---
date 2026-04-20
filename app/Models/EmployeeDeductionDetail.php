<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeDeductionDetail extends Model
{
    protected $table = 'employee_deduction_details';

    protected $fillable = [
        'account_set_id',
        'employee_id',
        'project_id',
        'deduction_items',
        'total_amount',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['employee_name', 'project_name', 'deduction_items_array'];

    // 关联账套
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    // 关联员工
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    // 关联项目
    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    // 关联更新人
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // 获取员工名称
    public function getEmployeeNameAttribute()
    {
        return $this->employee ? $this->employee->name : '';
    }

    // 获取项目名称
    public function getProjectNameAttribute()
    {
        return $this->project ? $this->project->name : '';
    }

    // 获取专项扣除项目数组
    public function getDeductionItemsArrayAttribute()
    {
        if (empty($this->deduction_items)) {
            return [];
        }

        $items = [];
        $parts = explode('|', $this->deduction_items);
        
        foreach ($parts as $part) {
            if (strpos($part, ':') !== false) {
                list($itemId, $amount) = explode(':', $part);
                $item = SpecialDeductionItem::find($itemId);
                if ($item) {
                    $items[] = [
                        'id' => $item->id,
                        'name' => $item->name,
                        'amount' => $amount,
                        'original_amount' => $item->amount,
                    ];
                }
            }
        }

        return $items;
    }

    // 设置专项扣除项目
    public function setDeductionItemsFromArray($itemsArray)
    {
        $parts = [];
        $totalAmount = 0;

        foreach ($itemsArray as $item) {
            $itemId = $item['id'];
            $amount = $item['amount'];
            $parts[] = "{$itemId}:{$amount}";
            $totalAmount += floatval($amount);
        }

        $this->deduction_items = implode('|', $parts);
        $this->total_amount = $totalAmount;
    }
}
