<?php

namespace App\Http\Controllers;

use App\Models\PaymentSummary;
use App\Models\PaymentRequest;
use App\Traits\ChecksPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class PaymentSummaryController extends Controller
{
    use ChecksPermission;

    /**
     * 获取出款汇总列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('payment_summaries.view')) {
            return $response;
        }

        try {
            $accountSetId = (int)$request->input('current_account_set_id');
            $month = $request->input('month'); // YYYY-MM 格式

            Log::info('出款汇总列表请求', [
                'account_set_id' => $accountSetId,
                'month' => $month,
                'all_params' => $request->all()
            ]);

            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $query = PaymentSummary::where('account_set_id', $accountSetId)
                ->with(['paymentRequest', 'accountSet'])
                ->orderBy('approved_at', 'desc')
                ->orderBy('created_at', 'desc');

            // 按月份筛选
            if ($month) {
                $query->where('month', $month);
            }

            $summaries = $query->get();

            Log::info('出款汇总查询结果', [
                'count' => $summaries->count(),
                'account_set_id' => $accountSetId,
                'month' => $month,
                'sql' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);

            return response()->json([
                'success' => true,
                'data' => $summaries,
                'count' => $summaries->count()
            ]);
        } catch (\Exception $e) {
            Log::error('获取出款汇总列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => '获取列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导出Excel
     */
    public function export(Request $request)
    {
        if ($response = $this->checkPermission('payment_summaries.export')) {
            return $response;
        }

        try {
            $accountSetId = (int)$request->input('current_account_set_id');
            $month = $request->input('month');

            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            $query = PaymentSummary::where('account_set_id', $accountSetId)
                ->with(['paymentRequest', 'accountSet'])
                ->orderBy('approved_at', 'desc')
                ->orderBy('created_at', 'desc');

            if ($month) {
                $query->where('month', $month);
            }

            $summaries = $query->get();

            // 使用 PhpSpreadsheet 生成 Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('出款汇总');

            // 设置表头
            $headers = [
                '序号', '付款类型', '月份', '项目', '申请日期', '单位名称', '发票号码',
                '查验', '打款日期', '支出金额', '项目名称', '摘要', '收到发票',
                '发票类型', '开票金额', '税率', '扣除额', '不含税金额', '税金',
                '是否一致', '状态', '勾选月份', '报销人', '开票日期', '入账', '公司', '付款金额', '审批通过时间'
            ];

            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $col++;
            }

            // 设置表头样式
            $sheet->getStyle('A1:' . $col . '1')->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0']
                ]
            ]);
            
            // 确保至少有一行数据（即使没有数据也要有表头）
            if ($summaries->isEmpty()) {
                // 如果没有数据，至少保留表头
                $sheet->setCellValue('A2', '暂无数据');
                $sheet->mergeCells('A2:' . $col . '2');
            }

            // 填充数据
            $row = 2;
            foreach ($summaries as $index => $summary) {
                $sheet->setCellValue('A' . $row, $index + 1);
                $sheet->setCellValue('B' . $row, $this->getPaymentTypeName($summary->payment_type));
                $sheet->setCellValue('C' . $row, $summary->month);
                $sheet->setCellValue('D' . $row, $summary->project ?? '');
                $sheet->setCellValue('E' . $row, $summary->apply_date ? $summary->apply_date->format('Y-m-d') : '');
                $sheet->setCellValue('F' . $row, $summary->unit_name ?? '');
                $sheet->setCellValue('G' . $row, $summary->invoice_number ?? '');
                $sheet->setCellValue('H' . $row, $summary->verified ? '已查验' : '');
                $sheet->setCellValue('I' . $row, $summary->payment_date ? $summary->payment_date->format('Y-m-d') : '');
                $sheet->setCellValue('J' . $row, $summary->expenditure_amount ?? '');
                $sheet->setCellValue('K' . $row, $summary->project_name ?? '');
                $sheet->setCellValue('L' . $row, $summary->summary ?? '');
                $sheet->setCellValue('M' . $row, $summary->invoice_received ? '已收到' : '');
                $sheet->setCellValue('N' . $row, $summary->invoice_type ?? '');
                $sheet->setCellValue('O' . $row, $summary->invoice_amount ?? '');
                $sheet->setCellValue('P' . $row, $summary->tax_rate ?? '');
                $sheet->setCellValue('Q' . $row, $summary->deduction_amount ?? '');
                $sheet->setCellValue('R' . $row, $summary->amount_excluding_tax ?? '');
                $sheet->setCellValue('S' . $row, $summary->tax_amount ?? '');
                $sheet->setCellValue('T' . $row, $summary->is_consistent ? '一致' : '');
                $sheet->setCellValue('U' . $row, $summary->status_checked ? '已确认' : '');
                $sheet->setCellValue('V' . $row, $summary->selected_month ?? '');
                $sheet->setCellValue('W' . $row, $summary->reimburser ?? '');
                $sheet->setCellValue('X' . $row, $summary->invoice_date ? $summary->invoice_date->format('Y-m-d') : '');
                $sheet->setCellValue('Y' . $row, $summary->accounted ? '已入账' : '');
                $sheet->setCellValue('Z' . $row, $summary->company ?? '');
                $sheet->setCellValue('AA' . $row, $summary->amount);
                $sheet->setCellValue('AB' . $row, $summary->approved_at ? $summary->approved_at->format('Y-m-d H:i:s') : '');
                $row++;
            }

            // 自动调整列宽
            foreach (range('A', 'AB') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // 生成文件名
            $fileName = '出款汇总_' . ($month ?: date('Y-m')) . '_' . date('YmdHis') . '.xlsx';
            
            // 创建Writer
            $writer = new Xlsx($spreadsheet);
            
            // 直接输出到浏览器（参考InvoiceApplicationController的实现）
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . urlencode($fileName) . '"');
            header('Cache-Control: max-age=0');
            
            // 输出到浏览器
            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            Log::error('导出出款汇总失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => '导出失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取付款类型名称
     */
    private function getPaymentTypeName($paymentType)
    {
        $map = [
            'salary' => '工资付款',
            'insurance' => '保险付款',
            'reimbursement' => '报销付款',
            '报销' => '报销付款',
            '差旅' => '差旅报销付款',
            '采购' => '采购报销付款',
            '项目' => '项目报销付款',
            '其他' => '其他报销付款',
        ];

        return $map[$paymentType] ?? $paymentType;
    }

    /**
     * 从付款申请创建出款汇总记录
     */
    public static function createFromPaymentRequest(PaymentRequest $paymentRequest)
    {
        try {
            // 检查是否已存在
            $existing = PaymentSummary::where('payment_request_id', $paymentRequest->id)->first();
            if ($existing) {
                Log::info('出款汇总记录已存在', [
                    'payment_request_id' => $paymentRequest->id,
                    'summary_id' => $existing->id
                ]);
                return $existing;
            }

            // 确定月份（优先使用 selected_month，否则使用申请日期或创建日期）
            $month = $paymentRequest->selected_month;
            if (!$month) {
                if ($paymentRequest->submitted_at) {
                    $month = $paymentRequest->submitted_at->format('Y-m');
                } else {
                    $month = now()->format('Y-m');
                }
            }

            // 获取项目信息（优先使用表单中的project，如果为空则从关联数据获取）
            $project = $paymentRequest->project;
            if (empty($project)) {
                // 如果是工资付款，从salaryApproval获取项目名称
                if ($paymentRequest->payment_type === 'salary' && $paymentRequest->salary_approval_id) {
                    $paymentRequest->load('salaryApproval.project');
                    if ($paymentRequest->salaryApproval && $paymentRequest->salaryApproval->project) {
                        $project = $paymentRequest->salaryApproval->project->name;
                        Log::info('从salaryApproval获取项目名称', [
                            'payment_request_id' => $paymentRequest->id,
                            'project' => $project
                        ]);
                    }
                }
                // 如果是报销付款，从reimbursement获取项目
                elseif ($paymentRequest->payment_type === 'reimbursement' && $paymentRequest->reimbursement_id) {
                    $paymentRequest->load('reimbursement');
                    if ($paymentRequest->reimbursement && $paymentRequest->reimbursement->project) {
                        $project = $paymentRequest->reimbursement->project;
                        Log::info('从reimbursement获取项目名称', [
                            'payment_request_id' => $paymentRequest->id,
                            'project' => $project
                        ]);
                    }
                }
            }
            
            // 构建创建数据
            $createData = [
                'payment_request_id' => $paymentRequest->id,
                'payment_type' => $paymentRequest->payment_type,
                'account_set_id' => $paymentRequest->account_set_id,
                'month' => $month,
                'project' => $project,
                'apply_date' => $paymentRequest->apply_date,
                'unit_name' => $paymentRequest->unit_name,
                'invoice_number' => $paymentRequest->invoice_number,
                'verified' => $paymentRequest->verified ?? true,
                'payment_date' => $paymentRequest->payment_date,
                'expenditure_amount' => $paymentRequest->expenditure_amount,
                'project_name' => $paymentRequest->project_name,
                'summary' => $paymentRequest->summary,
                'invoice_received' => $paymentRequest->invoice_received ?? false,
                'invoice_type' => $paymentRequest->invoice_type,
                'invoice_amount' => $paymentRequest->invoice_amount,
                'tax_rate' => $paymentRequest->tax_rate,
                'deduction_amount' => $paymentRequest->deduction_amount,
                'amount_excluding_tax' => $paymentRequest->amount_excluding_tax,
                'tax_amount' => $paymentRequest->tax_amount,
                'is_consistent' => $paymentRequest->is_consistent ?? false,
                'status_checked' => $paymentRequest->status_checked ?? true,
                'selected_month' => $paymentRequest->selected_month,
                'reimburser' => $paymentRequest->reimburser,
                'invoice_date' => $paymentRequest->invoice_date,
                'accounted' => $paymentRequest->accounted ?? true,
                'company' => $paymentRequest->company,
                'amount' => $paymentRequest->amount,
                'approved_at' => $paymentRequest->approved_at ?? now(),
            ];
            
            // 检查payment_summaries表是否有category字段，如果有则添加
            try {
                $hasCategoryColumn = Schema::hasColumn('payment_summaries', 'category');
                if ($hasCategoryColumn) {
                    // 根据 payment_type 确定类目
                    // 报销 → 报销, 差旅 → 差旅, 采购 → 采购, 其他所有类型 → 其他
                    $paymentType = $paymentRequest->payment_type;
                    if (in_array($paymentType, ['报销', 'reimbursement'])) {
                        $category = '报销';
                    } elseif ($paymentType === '差旅') {
                        $category = '差旅';
                    } elseif ($paymentType === '采购') {
                        $category = '采购';
                    } else {
                        // salary, insurance, 项目, 其他 等所有其他类型都归为"其他"
                        $category = '其他';
                    }
                    $createData['category'] = $category;
                    
                    Log::info('出款汇总类目设置', [
                        'payment_request_id' => $paymentRequest->id,
                        'payment_type' => $paymentType,
                        'category' => $category
                    ]);
                }
            } catch (\Exception $e) {
                // 如果检查失败，不添加category字段
                Log::warning('检查category字段失败，跳过该字段', ['error' => $e->getMessage()]);
            }
            
            // 记录创建前的数据（用于调试）
            Log::info('出款汇总记录创建前 - 付款申请数据', [
                'payment_request_id' => $paymentRequest->id,
                'project' => $paymentRequest->project ?? '未设置',
                'project_name' => $paymentRequest->project_name ?? '未设置',
                'create_data_project' => $createData['project'] ?? '未设置'
            ]);
            
            $summary = PaymentSummary::create($createData);

            Log::info('出款汇总记录已创建', [
                'payment_request_id' => $paymentRequest->id,
                'summary_id' => $summary->id,
                'month' => $month,
                'project' => $summary->project ?? '未设置',
                'project_name' => $summary->project_name ?? '未设置'
            ]);

            return $summary;
        } catch (\Exception $e) {
            Log::error('创建出款汇总记录失败', [
                'payment_request_id' => $paymentRequest->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
}

