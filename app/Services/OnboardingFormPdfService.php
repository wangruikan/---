<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\OnboardingForm;

class OnboardingFormPdfService
{
    /**
     * 生成单个员工的入职登记表PDF
     */
    public function generatePdf($employeeId)
    {
        $employee = Employee::with('onboardingForm')->findOrFail($employeeId);
        $form = $employee->onboardingForm;
        
        if (!$form) {
            throw new \Exception('该员工未填写入职登记表');
        }
        
        // 准备PDF内容
        $html = view('pdf.onboarding_form', [
            'employee' => $employee,
            'form' => $form
        ])->render();
        
        // 使用mpdf生成PDF（支持中文）
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_top' => 15,
            'margin_bottom' => 15,
            'margin_left' => 15,
            'margin_right' => 15,
        ]);
        
        // 设置自动字体替换
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        
        $mpdf->WriteHTML($html);
        
        // 记录位置信息用于动态计算签名位置
        $signatureTextY = $mpdf->y;
        $pageHeight = $mpdf->h;
        $fromBottom = $pageHeight - $signatureTextY;
        $signatureYFromBottom = $fromBottom * 2.83;
        
        return [
            'pdf_content' => $mpdf->Output('', 'S'),
            'signature_position' => [
                'x' => 115,
                'y' => round($signatureYFromBottom),
                'width' => 50,
                'height' => 20,
                'from_bottom' => true,
                'calculated_from_text' => true,
            ]
        ];
    }
    
    /**
     * 批量生成多个员工的入职登记表PDF，打包成ZIP
     */
    public function generateMultiplePdfs(array $employeeIds)
    {
        $zip = new \ZipArchive();
        $zipFileName = '入职登记表_' . date('YmdHis') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);
        
        // 创建临时目录
        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('无法创建ZIP文件');
        }
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($employeeIds as $employeeId) {
            try {
                $employee = Employee::findOrFail($employeeId);
                $pdfData = $this->generatePdf($employeeId);
                
                $fileName = $employee->name . '_入职登记表.pdf';
                $zip->addFromString($fileName, $pdfData['pdf_content']);
                $successCount++;
            } catch (\Exception $e) {
                \Log::error("生成PDF失败: 员工ID {$employeeId}", [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errorCount++;
                continue;
            }
        }
        
        $zip->close();
        
        if ($successCount === 0) {
            throw new \Exception('所有员工的PDF生成均失败');
        }
        
        return [
            'path' => $zipPath,
            'success_count' => $successCount,
            'error_count' => $errorCount
        ];
    }
}
