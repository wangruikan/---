<?php

namespace App\Http\Controllers;

use App\Models\PaymentFormHistory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PaymentFormHistoryController extends Controller
{
    /**
     * 获取历史记录列表（用于自动完成）
     */
    public function index(Request $request)
    {
        try {
            $query = PaymentFormHistory::query();

            // 如果有搜索关键词，按银行账号或支付对象搜索
            if ($request->has('keyword')) {
                $keyword = $request->input('keyword');
                $query->where(function ($q) use ($keyword) {
                    $q->where('bank_account', 'like', "%{$keyword}%")
                      ->orWhere('payee', 'like', "%{$keyword}%");
                });
            }

            // 按更新时间倒序
            $histories = $query->orderBy('updated_at', 'desc')
                              ->limit(20)
                              ->get();

            return response()->json([
                'success' => true,
                'data' => $histories
            ]);
        } catch (\Exception $e) {
            Log::error('获取付款历史记录失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取历史记录失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 根据银行账号获取详细信息
     */
    public function show($bankAccount)
    {
        try {
            $history = PaymentFormHistory::where('bank_account', $bankAccount)->first();

            if (!$history) {
                return response()->json([
                    'success' => false,
                    'message' => '未找到该银行账号的历史记录'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            Log::error('获取付款历史记录详情失败', [
                'bank_account' => $bankAccount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取历史记录详情失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 保存或更新历史记录
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'bank_account' => 'required|string|max:100',
                'department' => 'nullable|string|max:100',
                'payee' => 'nullable|string|max:100',
                'amount_small' => 'nullable|string|max:50',
                'amount_large' => 'nullable|string|max:100',
                'payment_method' => 'nullable|array',
                'bank' => 'nullable|string|max:100',
                'purpose' => 'nullable|string',
                'invoice_status' => 'nullable|array',
            ]);

            // 使用 updateOrCreate 实现：相同银行账号则更新，否则创建
            $history = PaymentFormHistory::updateOrCreate(
                ['bank_account' => $validated['bank_account']],
                array_merge($validated, [
                    'user_id' => Auth::id()
                ])
            );

            return response()->json([
                'success' => true,
                'message' => '历史记录保存成功',
                'data' => $history
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '数据验证失败',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('保存付款历史记录失败', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '保存历史记录失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除历史记录
     */
    public function destroy($id)
    {
        try {
            $history = PaymentFormHistory::findOrFail($id);
            $history->delete();

            return response()->json([
                'success' => true,
                'message' => '历史记录删除成功'
            ]);
        } catch (\Exception $e) {
            Log::error('删除付款历史记录失败', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '删除历史记录失败: ' . $e->getMessage()
            ], 500);
        }
    }
}

