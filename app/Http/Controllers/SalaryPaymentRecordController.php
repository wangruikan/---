<?php

namespace App\Http\Controllers;

use App\Models\SalaryPaymentRecord;
use App\Models\Salary;
use App\Models\Employee;
use App\Traits\ChecksPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalaryPaymentRecordController extends Controller
{
    use ChecksPermission;

    /**
     * 获取发工资表列表（支持按项目、时间筛选）
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('salary_payment.view')) {
            return $response;
        }

        $query = SalaryPaymentRecord::query();

        // 账套过滤
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }

        // 项目过滤
        if ($request->has('project_id') && $request->project_id) {
            $query->where('project_id', $request->project_id);
        }

        // 月份过滤
        if ($request->has('month') && $request->month) {
            $query->where('month', $request->month);
        }

        // 加载关系数据
        $records = $query->with(['employee:id,name,employee_number', 'project:id,name'])
                        ->orderBy('month', 'desc')
                        ->orderBy('project_id', 'asc')
                        ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $records
        ]);
    }

    /**
     * 工资表审核通过时自动生成发工资表
     * 由工资表审核流程调用
     */
    public function generate(Request $request)
    {
        if ($response = $this->checkPermission('salary_payment.create')) {
            return $response;
        }

        $request->validate([
            'salary_id' => 'required|exists:salaries,id',
        ]);

        try {
            DB::beginTransaction();

            $salary = Salary::findOrFail($request->salary_id);

            // 获取该工资表的所有员工记录
            $salaryDetails = DB::table('salary_details')
                ->where('salary_id', $salary->id)
                ->get();

            if ($salaryDetails->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '工资表中没有员工记录'
                ], 422);
            }

            // 为每个员工生成发工资记录
            foreach ($salaryDetails as $detail) {
                $employee = Employee::find($detail->employee_id);
                if (!$employee) {
                    continue;
                }

                // 获取员工的工资卡信息
                $bankAccount = $employee->bank_account ?? '';
                $bankAccountHolder = $employee->bank_account_holder ?? '';
                $bankName = $employee->bank_name ?? '';
                $bankProvince = $employee->bank_province ?? '';
                $remittanceRemark = $employee->remittance_remark ?? '';

                // 创建发工资记录
                SalaryPaymentRecord::create([
                    'salary_id' => $salary->id,
                    'employee_id' => $employee->id,
                    'project_id' => $salary->project_id,
                    'month' => $salary->month,
                    'bank_account' => $bankAccount,
                    'bank_account_holder' => $bankAccountHolder,
                    'amount' => $detail->net_salary ?? 0, // 实发工资
                    'bank_name' => $bankName,
                    'bank_province' => $bankProvince,
                    'remittance_remark' => $remittanceRemark,
                    'account_set_id' => $salary->account_set_id,
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '发工资表生成成功'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('生成发工资表失败', [
                'salary_id' => $request->salary_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '生成发工资表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导出发工资表为 Excel
     */
    public function export(Request $request)
    {
        if ($response = $this->checkPermission('salary_payment.export')) {
            return $response;
        }

        try {
            $query = SalaryPaymentRecord::query();

            // 账套过滤
            $currentAccountSetId = $request->input('current_account_set_id');
            if ($currentAccountSetId) {
                $query->where('account_set_id', $currentAccountSetId);
            }

            // 项目过滤
            if ($request->has('project_id') && $request->project_id) {
                $query->where('project_id', $request->project_id);
            }

            // 月份过滤
            if ($request->has('month') && $request->month) {
                $query->where('month', $request->month);
            }

            $records = $query->with(['employee:id,name,employee_number', 'project:id,name'])
                            ->orderBy('month', 'desc')
                            ->orderBy('project_id', 'asc')
                            ->get();

            if ($records->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => '没有数据可导出'
                ], 422);
            }

            // 准备导出数据 - 只导出必要字段
            $headers = ['账号', '户名', '金额', '开户行', '开户地', '汇款备注'];
            $data = [];

            foreach ($records as $index => $record) {
                $data[] = [
                    $record->bank_account ?? '',
                    $record->bank_account_holder ?? '',
                    $record->amount,
                    $record->bank_name ?? '',
                    $record->bank_province ?? '',
                    $record->remittance_remark ?? '',
                ];
            }

            // 返回 Excel 数据（前端使用 XLSX 库处理）
            return response()->json([
                'success' => true,
                'headers' => $headers,
                'data' => $data,
                'filename' => '发工资表_' . ($request->month ?? date('Y-m')) . '_' . date('Y-m-d H:i:s') . '.xlsx'
            ]);
        } catch (\Exception $e) {
            \Log::error('导出发工资表失败', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除发工资记录
     */
    public function destroy($id)
    {
        if ($response = $this->checkPermission('salary_payment.delete')) {
            return $response;
        }

        try {
            $record = SalaryPaymentRecord::findOrFail($id);
            $record->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
