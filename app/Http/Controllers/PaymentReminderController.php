<?php

namespace App\Http\Controllers;

use App\Models\PaymentDueDateConfig;
use App\Models\PaymentReminderConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentReminderController extends Controller
{
    /**
     * 获取缴费日期配置（按月份）
     */
    public function getDueDateConfigs(Request $request)
    {
        $accountSetId = $request->input('account_set_id');
        $paymentType = $request->input('payment_type');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }
        
        $query = PaymentDueDateConfig::where('account_set_id', $accountSetId);
        
        if ($paymentType) {
            $query->where('payment_type', $paymentType);
        }
        
        $configs = $query->orderBy('payment_type')
                        ->orderBy('month')
                        ->get();

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * 批量保存缴费日期配置
     */
    public function batchSaveDueDateConfigs(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_set_id' => 'required|integer',
            'payment_type' => 'required|in:social_security,housing_fund',
            'configs' => 'required|array|size:12',
            'configs.*.month' => 'required|integer|min:1|max:12',
            'configs.*.due_day' => 'required|integer|min:1|max:31',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        try {
            foreach ($request->configs as $config) {
                PaymentDueDateConfig::updateOrCreate(
                    [
                        'account_set_id' => $request->account_set_id,
                        'payment_type' => $request->payment_type,
                        'month' => $config['month'],
                    ],
                    [
                        'due_day' => $config['due_day'],
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'message' => '批量保存成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '保存失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 保存单个月份的缴费日期配置
     */
    public function saveDueDateConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_set_id' => 'required|integer',
            'payment_type' => 'required|in:social_security,housing_fund',
            'month' => 'required|integer|min:1|max:12',
            'due_day' => 'required|integer|min:1|max:31',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $config = PaymentDueDateConfig::updateOrCreate(
            [
                'account_set_id' => $request->account_set_id,
                'payment_type' => $request->payment_type,
                'month' => $request->month,
            ],
            [
                'due_day' => $request->due_day,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => '保存成功',
            'data' => $config
        ]);
    }

    /**
     * 获取提醒时间配置
     */
    public function getReminderConfigs(Request $request)
    {
        $accountSetId = $request->input('account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }
        
        $configs = PaymentReminderConfig::where('account_set_id', $accountSetId)
                        ->orderBy('days_before')
                        ->orderBy('reminder_time')
                        ->get();

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * 保存提醒时间配置
     */
    public function saveReminderConfig(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'account_set_id' => 'required|integer',
            'days_before' => 'required|integer|min:0|max:30',
            'reminder_time' => 'required|date_format:H:i:s',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $config = PaymentReminderConfig::create([
            'account_set_id' => $request->account_set_id,
            'days_before' => $request->days_before,
            'reminder_time' => $request->reminder_time,
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json([
            'success' => true,
            'message' => '保存成功',
            'data' => $config
        ]);
    }

    /**
     * 更新提醒时间配置
     */
    public function updateReminderConfig(Request $request, $id)
    {
        $config = PaymentReminderConfig::find($id);
        
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => '配置不存在'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'days_before' => 'integer|min:0|max:30',
            'reminder_time' => 'date_format:H:i:s',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $config->update($request->only(['days_before', 'reminder_time', 'is_active']));

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $config
        ]);
    }

    /**
     * 删除提醒时间配置
     */
    public function deleteReminderConfig($id)
    {
        $config = PaymentReminderConfig::find($id);
        
        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => '配置不存在'
            ], 404);
        }

        $config->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }
}
