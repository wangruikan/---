<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 上下限更新服务
 * 
 * 处理上下限的新旧值切换逻辑
 */
class BaseLimitUpdateService
{
    /**
     * 更新上下限（每次修改都生成/更新补差）
     * 
     * @param object $model 模型实例（SocialSecurityType/MedicalInsuranceType/HousingFundConfig）
     * @param float|null $minBase 要设置的最低基数
     * @param float|null $maxBase 要设置的最高基数
     * @return array ['should_compensate' => bool, 'old_min' => float, 'old_max' => float, 'new_min' => float, 'new_max' => float]
     */
    public function updateLimits($model, $minBase, $maxBase)
    {
        $now = Carbon::now();
        
        // 1. 第一次设置上下限（初始化）
        if (is_null($model->new_min_base_amount) && is_null($model->new_max_base_amount)) {
            Log::info('第一次设置上下限', [
                'model' => get_class($model),
                'id' => $model->id,
                'min' => $minBase,
                'max' => $maxBase
            ]);
            
            $model->new_min_base_amount = $minBase;
            $model->new_max_base_amount = $maxBase;
            $model->new_limits_updated_at = $now;
            
            // 同步更新 min/max_base_amount（保持兼容性）
            $model->min_base_amount = $minBase;
            $model->max_base_amount = $maxBase;
            
            $model->save();
            
            return [
                'should_compensate' => false,
                'reason' => '第一次设置上下限，没有旧值，不需要补差',
                'old_min' => null,
                'old_max' => null,
                'new_min' => $minBase,
                'new_max' => $maxBase
            ];
        }
        
        // 2. 判断是否本月修改
        $currentMonth = Carbon::now()->startOfMonth();
        $lastUpdateMonth = $model->new_limits_updated_at ? 
            Carbon::parse($model->new_limits_updated_at)->startOfMonth() : 
            null;
        
        $isSameMonth = $lastUpdateMonth && $lastUpdateMonth->eq($currentMonth);
        
        if ($isSameMonth) {
            // 本月内修改：直接覆盖new字段，不复制到old
            Log::info('本月内修改上下限（直接覆盖）', [
                'model' => get_class($model),
                'id' => $model->id,
                'last_update' => $model->new_limits_updated_at,
                'current_new' => [
                    'min' => $model->new_min_base_amount,
                    'max' => $model->new_max_base_amount
                ],
                'will_set_to_new' => [
                    'min' => $minBase,
                    'max' => $maxBase
                ]
            ]);
            
            // 保存当前new值用于补差计算（本月第一次修改时的old值）
            $oldMinForCompensation = $model->old_min_base_amount ?? $model->new_min_base_amount;
            $oldMaxForCompensation = $model->old_max_base_amount ?? $model->new_max_base_amount;
            
            // 直接覆盖new值（不复制到old）
            $model->new_min_base_amount = $minBase;
            $model->new_max_base_amount = $maxBase;
            $model->new_limits_updated_at = $now;
            
            // 同步更新 min/max_base_amount（保持兼容性）
            $model->min_base_amount = $minBase;
            $model->max_base_amount = $maxBase;
            
            $model->save();
            
            return [
                'should_compensate' => true,
                'reason' => '本月内修改上下限，更新补差明细',
                'old_min' => $oldMinForCompensation,
                'old_max' => $oldMaxForCompensation,
                'new_min' => $minBase,
                'new_max' => $maxBase
            ];
        }
        
        // 3. 跨月修改：先将new复制到old，再设置新的new
        Log::info('跨月修改上下限', [
            'model' => get_class($model),
            'id' => $model->id,
            'last_update' => $model->new_limits_updated_at,
            'will_copy_to_old' => [
                'min' => $model->new_min_base_amount,
                'max' => $model->new_max_base_amount
            ],
            'new_values' => [
                'min' => $minBase,
                'max' => $maxBase
            ]
        ]);
        
        // 保存旧值用于补差计算
        $oldMinForCompensation = $model->new_min_base_amount;
        $oldMaxForCompensation = $model->new_max_base_amount;
        
        // 将当前的new值复制到old
        $model->old_min_base_amount = $model->new_min_base_amount;
        $model->old_max_base_amount = $model->new_max_base_amount;
        $model->old_limits_updated_at = $model->new_limits_updated_at;
        
        // 设置新的new值
        $model->new_min_base_amount = $minBase;
        $model->new_max_base_amount = $maxBase;
        $model->new_limits_updated_at = $now;
        
        // 同步更新 min/max_base_amount（保持兼容性）
        $model->min_base_amount = $minBase;
        $model->max_base_amount = $maxBase;
        
        $model->save();
        
        return [
            'should_compensate' => true,
            'reason' => '跨月修改上下限，生成新补差明细',
            'old_min' => $oldMinForCompensation,
            'old_max' => $oldMaxForCompensation,
            'new_min' => $minBase,
            'new_max' => $maxBase
        ];
    }
}

