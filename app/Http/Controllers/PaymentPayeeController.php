<?php

namespace App\Http\Controllers;

use App\Models\PaymentPayee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PaymentPayeeController extends Controller
{
    public function index(Request $request)
    {
        $accountSetId = $this->resolveAccountSetId($request);

        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        $query = PaymentPayee::where('account_set_id', $accountSetId)
            ->with(['creator:id,name', 'updater:id,name'])
            ->orderBy('payee_name')
            ->orderByDesc('updated_at');

        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->input('keyword'));
            $query->where(function ($subQuery) use ($keyword) {
                $subQuery->where('payee_name', 'like', '%' . $keyword . '%')
                    ->orWhere('bank_name', 'like', '%' . $keyword . '%')
                    ->orWhere('bank_account', 'like', '%' . $keyword . '%');
            });
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()
        ]);
    }

    public function store(Request $request)
    {
        $accountSetId = $this->resolveAccountSetId($request);

        $validator = Validator::make($request->all(), [
            'payee_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('payment_payees', 'payee_name')->where(fn ($query) => $query->where('account_set_id', $accountSetId)),
            ],
            'bank_name' => 'required|string|max:255',
            'bank_account' => 'required|string|max:100',
        ], [
            'payee_name.required' => '请输入支付对象',
            'payee_name.max' => '支付对象不能超过100个字符',
            'payee_name.unique' => '该支付对象已存在，请直接编辑',
            'bank_name.required' => '请输入开户行',
            'bank_name.max' => '开户行不能超过255个字符',
            'bank_account.required' => '请输入账号',
            'bank_account.max' => '账号不能超过100个字符',
        ]);

        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $payee = PaymentPayee::create([
            'account_set_id' => $accountSetId,
            'payee_name' => trim((string) $request->input('payee_name')),
            'bank_name' => trim((string) $request->input('bank_name')),
            'bank_account' => trim((string) $request->input('bank_account')),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '创建成功',
            'data' => $payee->load(['creator:id,name', 'updater:id,name'])
        ]);
    }

    public function update(Request $request, $id)
    {
        $accountSetId = $this->resolveAccountSetId($request);

        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        $payee = PaymentPayee::where('account_set_id', $accountSetId)->find($id);

        if (!$payee) {
            return response()->json([
                'success' => false,
                'message' => '收款信息不存在'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'payee_name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('payment_payees', 'payee_name')
                    ->where(fn ($query) => $query->where('account_set_id', $accountSetId))
                    ->ignore($payee->id),
            ],
            'bank_name' => 'required|string|max:255',
            'bank_account' => 'required|string|max:100',
        ], [
            'payee_name.required' => '请输入支付对象',
            'payee_name.max' => '支付对象不能超过100个字符',
            'payee_name.unique' => '该支付对象已存在，请直接编辑',
            'bank_name.required' => '请输入开户行',
            'bank_name.max' => '开户行不能超过255个字符',
            'bank_account.required' => '请输入账号',
            'bank_account.max' => '账号不能超过100个字符',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 400);
        }

        $payee->update([
            'payee_name' => trim((string) $request->input('payee_name')),
            'bank_name' => trim((string) $request->input('bank_name')),
            'bank_account' => trim((string) $request->input('bank_account')),
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '更新成功',
            'data' => $payee->fresh(['creator:id,name', 'updater:id,name'])
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $accountSetId = $this->resolveAccountSetId($request);

        if (!$accountSetId) {
            return response()->json([
                'success' => false,
                'message' => '请选择账套'
            ], 400);
        }

        $payee = PaymentPayee::where('account_set_id', $accountSetId)->find($id);

        if (!$payee) {
            return response()->json([
                'success' => false,
                'message' => '收款信息不存在'
            ], 404);
        }

        $payee->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功'
        ]);
    }

    private function resolveAccountSetId(Request $request): ?int
    {
        $accountSetId = $request->header('X-Account-Set-Id')
            ?: $request->input('current_account_set_id')
            ?: $request->input('account_set_id')
            ?: optional($request->user())->current_account_set_id
            ?: optional($request->user())->account_set_id;

        return $accountSetId ? (int) $accountSetId : null;
    }
}
