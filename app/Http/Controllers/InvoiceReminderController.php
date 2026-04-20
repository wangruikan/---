<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InvoiceReminderController extends Controller
{
    /**
     * 提交未开票原因
     */
    public function submitReason(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'notification_id' => 'required|exists:notifications,id',
            'reason' => 'required|string|max:500',
        ], [
            'notification_id.required' => '通知ID不能为空',
            'notification_id.exists' => '通知不存在',
            'reason.required' => '未开票原因不能为空',
            'reason.max' => '原因不能超过500个字符',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }

        $user = Auth::user();
        $notificationId = $request->input('notification_id');
        $reason = $request->input('reason');

        // 获取原通知
        $notification = Notification::find($notificationId);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => '通知不存在'
            ], 404);
        }

        // 验证通知是否属于当前用户
        if ($notification->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'message' => '无权操作此通知'
            ], 403);
        }

        // 验证通知类型
        if ($notification->type != 'invoice_reminder') {
            return response()->json([
                'success' => false,
                'message' => '通知类型错误'
            ], 400);
        }

        $data = $notification->data;
        $projectName = $data['project_name'] ?? '未知项目';
        $year = $data['year'] ?? date('Y');
        $month = $data['month'] ?? date('n');
        $accountSetId = $data['account_set_id'] ?? null;

        // 更新当前通知：改变类型、添加原因（保持未读状态）
        $notification->update([
            'type' => 'invoice_reason_submitted',
            'title' => '未开票原因说明',
            'content' => "您已提交项目【{$projectName}】{$year}年{$month}月未开票原因：{$reason}",
            'data' => array_merge($data, [
                'reason' => $reason,
                'submitted_at' => now()->toDateTimeString(),
                'submitted_by' => $user->id,
                'submitted_by_name' => $user->name,
            ])
        ]);

        // 删除当前用户的该项目该月份的其他所有未开票提醒
        Notification::where('type', 'invoice_reminder')
            ->where('user_id', $user->id)
            ->where('id', '!=', $notificationId)
            ->where('data', 'LIKE', '%"project_id":' . ($data['project_id'] ?? 0) . '%')
            ->where('data', 'LIKE', '%"year":"' . $year . '"%')
            ->where('data', 'LIKE', '%"month":"' . $month . '"%')
            ->delete();

        // 获取后面3个审批节点的人员（第2、3、4审批节点）
        $approvers = User::whereHas('accountSets', function($query) use ($accountSetId) {
                $query->where('account_sets.id', $accountSetId)
                      ->whereIn('account_set_users.approval_level', [2, 3, 4]);
            })
            ->where('id', '!=', $user->id) // 排除当前用户
            ->get();

        // 为其他审批人更新或创建"已提交原因"通知
        foreach ($approvers as $approver) {
            // 先查找该用户是否有该项目该月份的未开票提醒
            $existingReminder = Notification::where('type', 'invoice_reminder')
                ->where('user_id', $approver->id)
                ->where('data', 'LIKE', '%"project_id":' . ($data['project_id'] ?? 0) . '%')
                ->where('data', 'LIKE', '%"year":"' . $year . '"%')
                ->where('data', 'LIKE', '%"month":"' . $month . '"%')
                ->first();

            if ($existingReminder) {
                // 如果有未开票提醒，更新为已提交原因
                $existingReminder->update([
                    'type' => 'invoice_reason_submitted',
                    'title' => '未开票原因说明',
                    'content' => "{$user->name} 已提交项目【{$projectName}】{$year}年{$month}月未开票原因：{$reason}",
                    'data' => array_merge($existingReminder->data, [
                        'reason' => $reason,
                        'submitted_by' => $user->id,
                        'submitted_by_name' => $user->name,
                        'submitted_at' => now()->toDateTimeString(),
                    ]),
                    'is_read' => false,
                ]);
            } else {
                // 如果没有未开票提醒，创建新的已提交原因通知
                Notification::create([
                    'user_id' => $approver->id,
                    'type' => 'invoice_reason_submitted',
                    'title' => '未开票原因说明',
                    'content' => "{$user->name} 已提交项目【{$projectName}】{$year}年{$month}月未开票原因：{$reason}",
                    'data' => [
                        'project_id' => $data['project_id'] ?? null,
                        'project_name' => $projectName,
                        'year' => $year,
                        'month' => $month,
                        'account_set_id' => $accountSetId,
                        'reason' => $reason,
                        'submitted_by' => $user->id,
                        'submitted_by_name' => $user->name,
                        'submitted_at' => now()->toDateTimeString(),
                    ],
                    'is_read' => false,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => '提交成功'
        ]);
    }
}
