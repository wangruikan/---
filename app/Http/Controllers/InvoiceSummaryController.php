<?php

namespace App\Http\Controllers;

use App\Models\InvoiceSummary;
use App\Traits\ChecksPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class InvoiceSummaryController extends Controller
{
    use ChecksPermission;

    /**
     * 获取发票汇总列表
     */
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('invoice_summaries.view')) {
            return $response;
        }

        $query = InvoiceSummary::with('invoiceApplication');
        
        // 【账套过滤】
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user() && $request->user()->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }
        
        // 日期筛选
        if ($request->has('start_date') && $request->start_date) {
            $query->where('apply_date', '>=', $request->start_date);
        }
        
        if ($request->has('end_date') && $request->end_date) {
            $query->where('apply_date', '<=', $request->end_date);
        }
        
        // 所属期筛选
        if ($request->has('period') && $request->period) {
            $query->where('period', $request->period);
        }
        
        // 项目筛选
        if ($request->has('project_name') && $request->project_name) {
            $query->where('project_name', 'like', '%' . $request->project_name . '%');
        }
        
        // 状态筛选
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }
        
        // 排序
        $query->orderBy('apply_date', 'desc')->orderBy('id', 'desc');
        
        // 分页
        $perPage = $request->input('per_page', 50);
        $summaries = $query->paginate($perPage);
        
        // 格式化日期字段
        $summaries->getCollection()->transform(function ($summary) {
            if ($summary->apply_date) {
                $summary->apply_date = $summary->apply_date->format('Y-m-d');
            }
            if ($summary->invoice_date) {
                $summary->invoice_date = $summary->invoice_date->format('Y-m-d');
            }
            return $summary;
        });
        
        return response()->json([
            'success' => true,
            'data' => $summaries
        ]);
    }

    /**
     * 更新发票汇总记录
     */
    public function update(Request $request, $id)
    {
        if ($response = $this->checkPermission('invoice_summaries.edit')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'invoice_date' => 'nullable|date',
            'invoicer' => 'nullable|string|max:100',
            'invoice_number' => 'nullable|string|max:100',
            'is_completed' => 'nullable|boolean',
            'status' => 'nullable|in:pending,completed',
            'remarks' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $summary = InvoiceSummary::findOrFail($id);
            
            $summary->update($request->only([
                'invoice_date',
                'invoicer',
                'invoice_number',
                'is_completed',
                'status',
                'remarks',
            ]));

            return response()->json([
                'success' => true,
                'message' => '更新成功',
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '更新失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 标记为已完成
     */
    public function markAsCompleted(Request $request, $id)
    {
        if ($response = $this->checkPermission('invoice_summaries.edit')) {
            return $response;
        }

        try {
            $summary = InvoiceSummary::findOrFail($id);
            
            $summary->update([
                'is_completed' => true,
                'status' => 'completed',
                'invoice_date' => $request->invoice_date ?? now(),
                'invoicer' => $request->invoicer ?? $request->user()->name,
                'invoice_number' => $request->invoice_number,
            ]);

            return response()->json([
                'success' => true,
                'message' => '已标记为完成',
                'data' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '操作失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导出Excel
     */
    public function export(Request $request)
    {
        if ($response = $this->checkPermission('invoice_summaries.export')) {
            return $response;
        }

        try {
            $query = InvoiceSummary::with('invoiceApplication');
            
            // 应用相同的筛选条件
            if ($request->has('start_date') && $request->start_date) {
                $query->where('apply_date', '>=', $request->start_date);
            }
            
            if ($request->has('end_date') && $request->end_date) {
                $query->where('apply_date', '<=', $request->end_date);
            }
            
            if ($request->has('period') && $request->period) {
                $query->where('period', $request->period);
            }
            
            if ($request->has('project_name') && $request->project_name) {
                $query->where('project_name', 'like', '%' . $request->project_name . '%');
            }
            
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            $summaries = $query->orderBy('apply_date', 'desc')->get();

            // 使用 PhpSpreadsheet 生成 Excel
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('发票汇总');

            // 设置表头
            $headers = [
                '序号', '所属期', '单位名称', '申请日期', '开票方式', '开票种类', '状态',
                '项目名称', '开票金额', '扣除额', '税率', '不含税金额', '开票税金', '税金',
                '开票日期', '是否完成', '开票人', '发票号码', '备注'
            ];

            $col = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($col . '1', $header);
                $col++;
            }

            // 设置表头样式
            $sheet->getStyle('A1:S1')->applyFromArray([
                'font' => ['bold' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E0E0E0']
                ]
            ]);

            // 填充数据
            $row = 2;
            foreach ($summaries as $index => $summary) {
                $sheet->setCellValue('A' . $row, $index + 1);
                $sheet->setCellValue('B' . $row, $summary->period ?? '');
                $sheet->setCellValue('C' . $row, $summary->unit_name ?? '');
                $sheet->setCellValue('D' . $row, $summary->apply_date ? $summary->apply_date->format('Y-m-d') : '');
                $sheet->setCellValue('E' . $row, $this->getInvoiceMethodName($summary->invoice_method));
                $sheet->setCellValue('F' . $row, $summary->invoice_type ?? '');
                $sheet->setCellValue('G' . $row, $this->getStatusName($summary->status));
                $sheet->setCellValue('H' . $row, $summary->project_name ?? '');
                $sheet->setCellValue('I' . $row, $summary->invoice_amount ?? '');
                $sheet->setCellValue('J' . $row, $summary->deduction_amount ?? '');
                $sheet->setCellValue('K' . $row, $summary->tax_rate ? ($summary->tax_rate * 100) . '%' : '');
                $sheet->setCellValue('L' . $row, $summary->amount_without_tax ?? '');
                $sheet->setCellValue('M' . $row, $summary->invoice_tax ?? '');
                $sheet->setCellValue('N' . $row, $summary->tax_amount ?? '');
                $sheet->setCellValue('O' . $row, $summary->invoice_date ? $summary->invoice_date->format('Y-m-d') : '');
                $sheet->setCellValue('P' . $row, $summary->is_completed ? '是' : '否');
                $sheet->setCellValue('Q' . $row, $summary->invoicer ?? '');
                $sheet->setCellValue('R' . $row, $summary->invoice_number ?? '');
                $sheet->setCellValue('S' . $row, $summary->remarks ?? '');
                $row++;
            }

            // 自动调整列宽
            foreach (range('A', 'S') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // 生成文件名
            $fileName = '发票汇总_' . date('YmdHis') . '.xlsx';
            
            // 创建Writer
            $writer = new Xlsx($spreadsheet);
            
            // 直接输出到浏览器
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . urlencode($fileName) . '"');
            header('Cache-Control: max-age=0');
            
            // 输出到浏览器
            $writer->save('php://output');
            exit;
        } catch (\Exception $e) {
            Log::error('导出发票汇总失败', [
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
     * 获取开票方式名称
     */
    private function getInvoiceMethodName($method)
    {
        $map = [
            'full' => '全额',
            'diff' => '差额',
            'none' => '无'
        ];
        return $map[$method] ?? $method;
    }

    /**
     * 获取状态名称
     */
    private function getStatusName($status)
    {
        $map = [
            'pending' => '待开票',
            'completed' => '已完成'
        ];
        return $map[$status] ?? $status;
    }
}
