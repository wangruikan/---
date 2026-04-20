<?php

namespace App\Http\Controllers;

use App\Models\ContractReminder;
use App\Models\BidReminder;
use App\Models\DocumentDeliveryReminder;
use App\Models\AssessmentRecord;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * 获取Dashboard统计数据
     */
    public function getStats(Request $request)
    {
        $accountSetId = $request->input('account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        // 这里可以添加各种统计数据的获取逻辑
        $stats = [
            'totalEmployees' => 0,
            'totalProjects' => 0,
            'pendingApprovals' => 0,
            'totalSalary' => 0
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * 获取提醒事项
     */
    public function getReminders(Request $request)
    {
        $accountSetId = $request->input('account_set_id');
        
        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        $reminders = [];

        // 1. 获取合同提醒
        $contractReminders = $this->getContractReminders($accountSetId);
        $reminders = array_merge($reminders, $contractReminders);

        // 2. 获取投标提醒
        $bidReminders = $this->getBidReminders($accountSetId);
        $reminders = array_merge($reminders, $bidReminders);

        // 3. 获取资料交付提醒
        $deliveryReminders = $this->getDeliveryReminders($accountSetId);
        $reminders = array_merge($reminders, $deliveryReminders);

        // 4. 获取考核提醒
        $assessmentReminders = $this->getAssessmentReminders($accountSetId);
        $reminders = array_merge($reminders, $assessmentReminders);

        // 按创建时间倒序排列，最多返回20条
        usort($reminders, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        $reminders = array_slice($reminders, 0, 20);

        return response()->json([
            'success' => true,
            'data' => $reminders
        ]);
    }

    /**
     * 获取合同提醒
     */
    private function getContractReminders($accountSetId)
    {
        $reminders = [];
        
        $contractReminders = ContractReminder::where('account_set_id', $accountSetId)
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($contractReminders as $reminder) {
            $type = 'contract';
            $icon = 'Document';
            
            // 根据提醒类型设置不同的图标和样式
            if ($reminder->reminder_type === 'retirement_agreement') {
                $type = 'retirement';
                $icon = 'UserFilled';
            }

            $reminders[] = [
                'id' => 'contract_' . $reminder->id,
                'type' => $type,
                'icon' => $icon,
                'title' => $reminder->reminder_type_text,
                'description' => $reminder->employee_name . ' - ' . $this->truncateText($reminder->description, 80),
                'created_at' => $reminder->created_at->toISOString(),
                'priority' => $this->getContractReminderPriority($reminder),
                'action_url' => '/contract-reminders',
                'source' => 'contract_reminder',
                'source_id' => $reminder->id
            ];
        }

        return $reminders;
    }

    /**
     * 获取投标提醒
     */
    private function getBidReminders($accountSetId)
    {
        $reminders = [];
        
        // 这里可以添加投标提醒的逻辑
        // 目前先返回空数组
        
        return $reminders;
    }

    /**
     * 获取资料交付提醒
     */
    private function getDeliveryReminders($accountSetId)
    {
        $reminders = [];
        
        $deliveryReminders = DocumentDeliveryReminder::where('account_set_id', $accountSetId)
            ->where('is_read', false)
            ->with('delivery')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($deliveryReminders as $reminder) {
            $delivery = $reminder->delivery;
            if (!$delivery) continue;

            $reminders[] = [
                'id' => 'delivery_' . $reminder->id,
                'type' => 'warning',
                'icon' => 'Warning',
                'title' => '资料交付提醒',
                'description' => "员工 {$delivery->employee_name} 的资料交付需要处理",
                'created_at' => $reminder->created_at->toISOString(),
                'priority' => 'medium',
                'action_url' => '/document-delivery',
                'source' => 'delivery_reminder',
                'source_id' => $reminder->id
            ];
        }

        return $reminders;
    }

    /**
     * 获取考核提醒
     */
    private function getAssessmentReminders($accountSetId)
    {
        $reminders = [];
        
        // 获取即将到期的考核记录
        $assessments = AssessmentRecord::where('account_set_id', $accountSetId)
            ->where('status', 'pending')
            ->where('deadline_date', '<=', Carbon::now()->addDays(3)->format('Y-m-d'))
            ->orderBy('deadline_date', 'asc')
            ->limit(10)
            ->get();

        foreach ($assessments as $assessment) {
            $isOverdue = Carbon::parse($assessment->deadline_date)->isPast();
            
            $reminders[] = [
                'id' => 'assessment_' . $assessment->id,
                'type' => $isOverdue ? 'warning' : 'contract',
                'icon' => $isOverdue ? 'Warning' : 'Clock',
                'title' => $isOverdue ? '考核已超期' : '考核即将到期',
                'description' => $assessment->business_name . ' - 截止日期：' . $assessment->deadline_date,
                'created_at' => $assessment->created_at->toISOString(),
                'priority' => $isOverdue ? 'high' : 'medium',
                'action_url' => '/assessment',
                'source' => 'assessment_record',
                'source_id' => $assessment->id
            ];
        }

        return $reminders;
    }

    /**
     * 获取合同提醒优先级
     */
    private function getContractReminderPriority($reminder)
    {
        // 根据提醒日期计算优先级
        $reminderDate = Carbon::parse($reminder->reminder_date);
        $daysSinceReminder = Carbon::now()->diffInDays($reminderDate);

        if ($daysSinceReminder >= 15) {
            return 'high'; // 超过15天的提醒为高优先级
        } elseif ($daysSinceReminder >= 7) {
            return 'medium'; // 7-15天为中优先级
        } else {
            return 'low'; // 7天内为低优先级
        }
    }

    /**
     * 截断文本
     */
    private function truncateText($text, $length = 100)
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length) . '...';
    }

    /**
     * 标记提醒为已读
     */
    public function markReminderAsRead(Request $request)
    {
        $source = $request->input('source');
        $sourceId = $request->input('source_id');

        if (!$source || !$sourceId) {
            return response()->json([
                'success' => false,
                'message' => '参数不完整'
            ], 400);
        }

        try {
            switch ($source) {
                case 'delivery_reminder':
                    DocumentDeliveryReminder::where('id', $sourceId)
                        ->update(['is_read' => true]);
                    break;
                    
                // 其他类型的提醒可以在这里添加标记逻辑
            }

            return response()->json([
                'success' => true,
                'message' => '已标记为已读'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '操作失败：' . $e->getMessage()
            ], 500);
        }
    }
}
