<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceChangeSummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'region_name',
        'insurance_type',
        'insurance_name',
        'employee_count',
        'total_base_amount',
        'total_employee_amount',
        'total_company_amount',
        'total_amount',
        'summary_date',
        'created_by',
    ];

    protected $casts = [
        'employee_count' => 'integer',
        'total_base_amount' => 'decimal:2',
        'total_employee_amount' => 'decimal:2',
        'total_company_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'summary_date' => 'date',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 账套关联
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 创建人关联
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 获取保险类型文本
     */
    public function getInsuranceTypeTextAttribute()
    {
        $typeMap = [
            'social_security' => '社保',
            'medical_insurance' => '医保',
            'housing_fund' => '公积金',
            'other_insurance' => '其他保险'
        ];

        return $typeMap[$this->insurance_type] ?? '未知';
    }

    /**
     * 生成汇总数据
     */
    public static function generateSummary($accountSetId, $regionName = null, $summaryDate = null)
    {
        $summaryDate = $summaryDate ?: now()->toDateString();
        
        // 获取已完成状态的参保明细
        $query = InsuranceChangeDetail::where('account_set_id', $accountSetId)
            ->whereHas('insuranceChange', function($q) {
                $q->where('status', 'completed');
            });

        if ($regionName) {
            $query->where('region_name', $regionName);
        }

        $details = $query->get();

        // 按保险类型和名称分组统计
        $grouped = $details->groupBy(['insurance_type', 'insurance_name']);

        $summaries = [];
        foreach ($grouped as $insuranceType => $typeGroup) {
            foreach ($typeGroup as $insuranceName => $nameGroup) {
                $summary = new self([
                    'account_set_id' => $accountSetId,
                    'region_name' => $regionName ?: '全部',
                    'insurance_type' => $insuranceType,
                    'insurance_name' => $insuranceName,
                    'employee_count' => $nameGroup->count(),
                    'total_base_amount' => $nameGroup->sum('base_amount'),
                    'total_employee_amount' => $nameGroup->sum('employee_amount'),
                    'total_company_amount' => $nameGroup->sum('company_amount'),
                    'total_amount' => $nameGroup->sum('total_amount'),
                    'summary_date' => $summaryDate,
                    'created_by' => auth()->id() ?: 1  // 如果没有认证用户，使用默认用户ID 1
                ]);

                $summary->save();
                $summaries[] = $summary;
            }
        }

        return $summaries;
    }
}
