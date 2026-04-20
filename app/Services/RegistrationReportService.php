<?php

namespace App\Services;

use App\Models\InsuranceChange;
use App\Models\ReportTemplate;
use App\Models\SocialSecurityRegion;
use App\Models\MedicalInsuranceRegion;
use App\Models\HousingFundRegion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;
use Exception;

class RegistrationReportService
{
    /**
     * 生成参保登记表（返回文件数组供前端打包）
     * 
     * @param array $taskIds 任务ID数组
     * @param int $accountSetId 账套ID
     * @param string $month 月份（格式：YYYY-MM）
     * @return array 文件数组
     * @throws Exception
     */
    public function generateReportsForDownload($taskIds, $accountSetId, $month = null)
    {
        Log::info('开始生成参保登记表', [
            'task_ids' => $taskIds,
            'account_set_id' => $accountSetId,
            'month' => $month
        ]);

        try {
            // 1. 分析任务数据并按地区和类型分组
            $grouped = $this->analyzeTasksData($taskIds, $accountSetId);

            // 2. 生成Excel文件
            $files = [];
            
            foreach ($grouped as $insuranceType => $regions) {
                foreach ($regions as $regionId => $data) {
                    $tempFilePath = $this->generateExcelFile(
                        $regionId,
                        $insuranceType,
                        $data['employees'],
                        $data['region_name'],
                        $accountSetId,
                        $month
                    );
                    
                    if ($tempFilePath && file_exists($tempFilePath)) {
                        $filename = $data['region_name'] . '-' . $this->getInsuranceTypeName($insuranceType) . '参保登记表.xlsx';
                        
                        // 读取文件内容并转为 base64
                        $fileContent = file_get_contents($tempFilePath);
                        
                        $files[] = [
                            'name' => $filename,
                            'content' => base64_encode($fileContent)
                        ];
                        
                        // 删除临时文件
                        unlink($tempFilePath);
                    }
                }
            }

            if (empty($files)) {
                throw new Exception('未找到可用的报表模板');
            }

            Log::info('报表生成完成', [
                'generated_files' => count($files)
            ]);

            return $files;

        } catch (Exception $e) {
            Log::error('生成报表失败', [
                'error' => $e->getMessage(),
                'task_ids' => $taskIds
            ]);
            throw $e;
        }
    }

    /**
     * 生成参保登记表
     * 
     * @param array $taskIds 任务ID数组
     * @param int $accountSetId 账套ID
     * @param string $month 月份（格式：YYYY-MM）
     * @return string ZIP文件路径
     * @throws Exception
     */
    public function generateReports($taskIds, $accountSetId, $month = null)
    {
        Log::info('开始生成参保登记表', [
            'task_ids' => $taskIds,
            'account_set_id' => $accountSetId,
            'month' => $month
        ]);

        try {
            // 1. 分析任务数据并按地区和类型分组
            $grouped = $this->analyzeTasksData($taskIds, $accountSetId);

            // 2. 生成Excel文件
            $excelFiles = [];
            
            foreach ($grouped as $insuranceType => $regions) {
                foreach ($regions as $regionId => $data) {
                    $filename = $this->generateExcelFile(
                        $regionId,
                        $insuranceType,
                        $data['employees'],
                        $data['region_name'],
                        $accountSetId,
                        $month
                    );
                    
                    if ($filename) {
                        $excelFiles[$data['region_name'] . '-' . $this->getInsuranceTypeName($insuranceType) . '参保登记表.xlsx'] = $filename;
                    }
                }
            }

            if (empty($excelFiles)) {
                throw new Exception('未找到可用的报表模板');
            }

            // 3. 打包成ZIP
            $zipFilename = $this->createZipPackage($excelFiles, $month ?? date('Y-m'));

            Log::info('报表生成完成', [
                'generated_files' => count($excelFiles),
                'zip_file' => $zipFilename
            ]);

            return $zipFilename;

        } catch (Exception $e) {
            Log::error('生成报表失败', [
                'error' => $e->getMessage(),
                'task_ids' => $taskIds
            ]);
            throw $e;
        }
    }

    /**
     * 分析任务数据并按地区和类型分组
     * 
     * @param array $taskIds 任务ID数组
     * @param int $accountSetId 账套ID
     * @return array 分组后的数据
     */
    private function analyzeTasksData($taskIds, $accountSetId)
    {
        // 查询已完成的任务，预加载员工关联
        $tasks = InsuranceChange::whereIn('id', $taskIds)
            ->where('account_set_id', $accountSetId)
            ->where('status', 'completed')
            ->with(['employee'])
            ->get();

        $grouped = [
            'social_security' => [],
            'medical_insurance' => [],
            'housing_fund' => []
        ];

        foreach ($tasks as $task) {
            // 根据任务类型决定包含哪些保险
            $includeTypes = $this->determineIncludeTypes($task);

            // 社保
            if ($includeTypes['social_security'] && $task->social_security_region_id) {
                $regionId = $task->social_security_region_id;
                if (!isset($grouped['social_security'][$regionId])) {
                    // 获取地区名称
                    $regionName = '未知地区';
                    $region = \App\Models\SocialSecurityRegion::find($regionId);
                    if ($region) {
                        $regionName = $region->name;
                    }
                    
                    $grouped['social_security'][$regionId] = [
                        'region_name' => $regionName,
                        'employees' => []
                    ];
                }
                $grouped['social_security'][$regionId]['employees'][] = $this->extractEmployeeData($task, 'social_security');
            }

            // 医保
            if ($includeTypes['medical_insurance'] && $task->medical_insurance_region_id) {
                $regionId = $task->medical_insurance_region_id;
                if (!isset($grouped['medical_insurance'][$regionId])) {
                    // 获取地区名称
                    $regionName = '未知地区';
                    $region = \App\Models\MedicalInsuranceRegion::find($regionId);
                    if ($region) {
                        $regionName = $region->name;
                    }
                    
                    $grouped['medical_insurance'][$regionId] = [
                        'region_name' => $regionName,
                        'employees' => []
                    ];
                }
                $grouped['medical_insurance'][$regionId]['employees'][] = $this->extractEmployeeData($task, 'medical_insurance');
            }

            // 公积金
            if ($includeTypes['housing_fund'] && $task->housing_fund_region_id) {
                $regionId = $task->housing_fund_region_id;
                if (!isset($grouped['housing_fund'][$regionId])) {
                    // 获取地区名称
                    $regionName = '未知地区';
                    $region = \App\Models\HousingFundRegion::find($regionId);
                    if ($region) {
                        $regionName = $region->region_name;
                    }
                    
                    $grouped['housing_fund'][$regionId] = [
                        'region_name' => $regionName,
                        'employees' => []
                    ];
                }
                $grouped['housing_fund'][$regionId]['employees'][] = $this->extractEmployeeData($task, 'housing_fund');
            }
        }

        return $grouped;
    }

    /**
     * 判断任务类型应包含哪些保险
     * 
     * @param InsuranceChange $task 任务对象
     * @return array 包含的保险类型
     */
    private function determineIncludeTypes($task)
    {
        switch ($task->change_type) {
            case 'increase':
            case 'decrease':
                // 新增和减少：包含所有保险
                return [
                    'social_security' => true,
                    'medical_insurance' => true,
                    'housing_fund' => true
                ];
            case 'change':
                // 变更：只包含发生变更的保险
                return [
                    'social_security' => $task->social_security_changed ?? false,
                    'medical_insurance' => $task->medical_insurance_changed ?? false,
                    'housing_fund' => $task->housing_fund_changed ?? false
                ];
            default:
                return [
                    'social_security' => false,
                    'medical_insurance' => false,
                    'housing_fund' => false
                ];
        }
    }

    /**
     * 提取员工保险数据
     * 
     * @param InsuranceChange $task 任务对象
     * @param string $insuranceType 保险类型
     * @return array 员工数据
     */
    private function extractEmployeeData($task, $insuranceType)
    {
        $data = [
            'employee_id' => $task->employee_id,
            'employee_name' => $task->employee_name,
            'employee_number' => $task->employee->employee_number ?? '',  // 员工工号
            'id_number' => $task->employee_id_number,  // 身份证号
            'employee_id_number' => $task->employee_id_number,
            'employee_gender' => $task->employee_gender,
            'employee_birth_date' => $task->employee_birth_date,
            'change_type' => $task->change_type,
            'basic_salary' => $task->employee->basic_salary ?? 0,  // 月工资额（基础工资）
            'remarks' => $task->remarks ?? '',  // 备注
        ];

        // 根据保险类型添加对应的基数和类型信息
        switch ($insuranceType) {
            case 'social_security':
                $data['base'] = $task->employee_social_security_base;
                $data['types'] = $task->social_security_types;
                break;
            case 'medical_insurance':
                $data['base'] = $task->employee_medical_insurance_base;
                $data['types'] = $task->medical_insurance_types;
                break;
            case 'housing_fund':
                $data['base'] = $task->employee_housing_fund_base;
                $data['params'] = $task->housing_fund_params;
                // 计算公积金个人和单位金额
                $housingFundParams = is_string($task->housing_fund_params) 
                    ? json_decode($task->housing_fund_params, true) 
                    : $task->housing_fund_params;
                $base = $task->employee_housing_fund_base ?? 0;
                $employeeRatio = $housingFundParams['employee_ratio'] ?? 0;
                $companyRatio = $housingFundParams['company_ratio'] ?? 0;
                $data['housing_fund_employee'] = round($base * $employeeRatio, 2);
                $data['housing_fund_company'] = round($base * $companyRatio, 2);
                break;
        }

        return $data;
    }

    /**
     * 生成Excel文件
     * 
     * @param int $regionId 地区ID
     * @param string $insuranceType 保险类型
     * @param array $employeesData 员工数据
     * @param string $regionName 地区名称
     * @param int $accountSetId 账套ID
     * @param string $month 月份
     * @return string|null 临时文件路径，如果没有模板则返回null
     */
    private function generateExcelFile($regionId, $insuranceType, $employeesData, $regionName, $accountSetId, $month)
    {
        // 查找模板 - 按创建时间倒序，取最新的模板
        $template = ReportTemplate::where('region_id', $regionId)
            ->where('region_type', $insuranceType)
            ->where('account_set_id', $accountSetId)
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$template) {
            Log::warning('未找到报表模板', [
                'region_id' => $regionId,
                'insurance_type' => $insuranceType
            ]);
            return null;
        }
        
        Log::info('使用报表模板', [
            'template_id' => $template->id,
            'template_name' => $template->name,
            'region_id' => $regionId,
            'insurance_type' => $insuranceType
        ]);

        // 根据保险类型获取地区详细信息（包含公司名称）
        $regionData = $this->getRegionData($regionId, $insuranceType);

        // 创建Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 解析字段配置
        $fields = is_string($template->fields) ? json_decode($template->fields, true) : $template->fields;
        
        // 展开字段（处理父列和子列）
        $flatFields = [];
        foreach ($fields as $field) {
            if (isset($field['is_parent']) && $field['is_parent'] && !empty($field['children'])) {
                // 父列：展开子列
                foreach ($field['children'] as $child) {
                    $flatFields[] = [
                        'key' => $child['field'] ?? $child['key'] ?? '',
                        'label' => $child['title'] ?? $child['label'] ?? '',
                        'width' => $child['width'] ?? 100,
                        'format' => $child['format'] ?? 'text',
                        'align' => $child['align'] ?? 'center',
                        'show_total' => $child['show_total'] ?? $child['showTotal'] ?? false,
                    ];
                }
            } else if (!isset($field['is_parent']) || !$field['is_parent']) {
                // 普通列
                $flatFields[] = [
                    'key' => $field['key'] ?? $field['field'] ?? '',
                    'label' => $field['label'] ?? $field['title'] ?? '',
                    'width' => $field['width'] ?? 100,
                    'format' => $field['format'] ?? 'text',
                    'align' => $field['align'] ?? 'center',
                    'show_total' => $field['show_total'] ?? $field['showTotal'] ?? false,
                ];
            }
        }
        
        $totalColumns = count($flatFields);
        
        // 设置列宽（根据模板配置）
        for ($col = 0; $col < $totalColumns; $col++) {
            $width = $flatFields[$col]['width'] ?? 100;
            // Excel列宽单位约为字符宽度，像素/7 约等于字符数
            $excelWidth = max(8, $width / 7);
            $colLetter = $this->getColumnLetter($col + 1);
            $sheet->getColumnDimension($colLetter)->setWidth($excelWidth);
        }

        // 填充报表标题
        $sheet->setCellValue('A1', $template->report_title);
        $sheet->mergeCells('A1:' . $this->getColumnLetter($totalColumns) . '1');
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // 填充表头字段 - 按行分组
        $currentRow = 2;
        $headerFields = is_string($template->header_fields) ? json_decode($template->header_fields, true) : $template->header_fields;
        if ($headerFields && count($headerFields) > 0) {
            // 按行分组
            $headerRows = [];
            foreach ($headerFields as $field) {
                $row = $field['row'] ?? 1;
                if (!isset($headerRows[$row])) {
                    $headerRows[$row] = [];
                }
                $headerRows[$row][] = $field;
            }
            ksort($headerRows);
            
            foreach ($headerRows as $rowNum => $rowFields) {
                // 计算每个字段的列宽
                $fieldCount = count($rowFields);
                $colsPerField = max(1, intval($totalColumns / $fieldCount));
                
                $colStart = 1;
                foreach ($rowFields as $index => $field) {
                    $value = $this->resolveFieldValue($field, $month, $regionData);
                    $cellValue = $field['label'] . $value;
                    
                    // 设置单元格值
                    $sheet->setCellValueByColumnAndRow($colStart, $currentRow, $cellValue);
                    
                    // 计算结束列
                    $colEnd = ($index == $fieldCount - 1) ? $totalColumns : $colStart + $colsPerField - 1;
                    
                    // 合并单元格（如果跨多列）
                    if ($colEnd > $colStart) {
                        $sheet->mergeCells($this->getColumnLetter($colStart) . $currentRow . ':' . $this->getColumnLetter($colEnd) . $currentRow);
                    }
                    
                    $colStart = $colEnd + 1;
                }
                
                // 设置整行两端对齐
                $sheet->getStyle('A' . $currentRow . ':' . $this->getColumnLetter($totalColumns) . $currentRow)
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                
                $currentRow++;
            }
        }

        // 填充表格列头
        $tableHeaderRow = $currentRow;
        $colIndex = 0;
        foreach ($flatFields as $field) {
            $sheet->setCellValueByColumnAndRow($colIndex + 1, $currentRow, $field['label']);
            $colIndex++;
        }
        // 设置列头样式 - 居中加粗
        $sheet->getStyle('A' . $currentRow . ':' . $this->getColumnLetter($totalColumns) . $currentRow)
            ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A' . $currentRow . ':' . $this->getColumnLetter($totalColumns) . $currentRow)
            ->getFont()->setBold(true);

        // 填充数据行
        $currentRow++;
        $dataStartRow = $currentRow;
        $serialNumber = 1;
        
        // 最小数据行数（保证表格美观）
        $minDataRows = 10;
        $actualDataCount = count($employeesData);
        $totalDataRows = max($minDataRows, $actualDataCount);
        
        // 填充实际数据
        foreach ($employeesData as $employee) {
            $colIndex = 0;
            foreach ($flatFields as $field) {
                $value = $this->getEmployeeFieldValue($employee, $field['key'], $serialNumber);
                $sheet->setCellValueByColumnAndRow($colIndex + 1, $currentRow, $value);
                $colIndex++;
            }
            $currentRow++;
            $serialNumber++;
        }
        
        // 填充空行（如果实际数据少于最小行数）
        for ($i = $actualDataCount; $i < $totalDataRows; $i++) {
            // 空行只需要递增行号，不需要填充数据
            $currentRow++;
        }
        
        $dataEndRow = $currentRow - 1;
        
        // 设置数据区域样式 - 根据字段配置的对齐方式
        for ($col = 0; $col < $totalColumns; $col++) {
            $align = $flatFields[$col]['align'] ?? 'center';
            $alignConst = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER;
            if ($align === 'left') {
                $alignConst = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT;
            } elseif ($align === 'right') {
                $alignConst = \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT;
            }
            
            $colLetter = $this->getColumnLetter($col + 1);
            $sheet->getStyle($colLetter . $dataStartRow . ':' . $colLetter . $dataEndRow)
                ->getAlignment()->setHorizontal($alignConst);
        }

        // 检查是否有需要合计的列
        $hasTotal = false;
        $firstTotalColIndex = -1;
        foreach ($flatFields as $index => $field) {
            if (!empty($field['show_total'])) {
                $hasTotal = true;
                if ($firstTotalColIndex === -1) {
                    $firstTotalColIndex = $index;
                }
            }
        }
        
        // 填充合计行
        if ($hasTotal) {
            $currentRow++;
            
            // 计算每列的合计值
            $totals = [];
            foreach ($flatFields as $index => $field) {
                if (!empty($field['show_total'])) {
                    $sum = 0;
                    foreach ($employeesData as $employee) {
                        $value = $this->getEmployeeFieldValue($employee, $field['key'], 0);
                        if (is_numeric($value)) {
                            $sum += floatval($value);
                        }
                    }
                    $totals[$index] = $sum;
                } else {
                    $totals[$index] = '';
                }
            }
            
            // 填充合计行
            // 合并第一个合计列之前的所有单元格，显示"合计"
            if ($firstTotalColIndex > 0) {
                $sheet->setCellValue('A' . $currentRow, '合计');
                $sheet->mergeCells('A' . $currentRow . ':' . $this->getColumnLetter($firstTotalColIndex) . $currentRow);
                $sheet->getStyle('A' . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            }
            
            // 填充合计值
            foreach ($flatFields as $index => $field) {
                if (!empty($field['show_total'])) {
                    $colLetter = $this->getColumnLetter($index + 1);
                    $sheet->setCellValue($colLetter . $currentRow, $totals[$index]);
                    $sheet->getStyle($colLetter . $currentRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                }
            }
            
            // 设置合计行样式
            $sheet->getStyle('A' . $currentRow . ':' . $this->getColumnLetter($totalColumns) . $currentRow)
                ->getFont()->setBold(true);
        }

        // 填充表尾字段 - 按行分组
        $currentRow++;
        $footerFields = is_string($template->footer_fields) ? json_decode($template->footer_fields, true) : $template->footer_fields;
        if ($footerFields && count($footerFields) > 0) {
            // 按行分组
            $footerRows = [];
            foreach ($footerFields as $field) {
                $row = $field['row'] ?? 1;
                if (!isset($footerRows[$row])) {
                    $footerRows[$row] = [];
                }
                $footerRows[$row][] = $field;
            }
            ksort($footerRows);
            
            foreach ($footerRows as $rowNum => $rowFields) {
                // 计算每个字段的列宽
                $fieldCount = count($rowFields);
                $colsPerField = max(1, intval($totalColumns / $fieldCount));
                
                $colStart = 1;
                foreach ($rowFields as $index => $field) {
                    $value = $this->resolveFieldValue($field, $month, $regionData);
                    $cellValue = $field['label'] . $value;
                    
                    // 设置单元格值
                    $sheet->setCellValueByColumnAndRow($colStart, $currentRow, $cellValue);
                    
                    // 计算结束列
                    $colEnd = ($index == $fieldCount - 1) ? $totalColumns : $colStart + $colsPerField - 1;
                    
                    // 合并单元格（如果跨多列）
                    if ($colEnd > $colStart) {
                        $sheet->mergeCells($this->getColumnLetter($colStart) . $currentRow . ':' . $this->getColumnLetter($colEnd) . $currentRow);
                    }
                    
                    $colStart = $colEnd + 1;
                }
                
                // 设置整行两端对齐
                $sheet->getStyle('A' . $currentRow . ':' . $this->getColumnLetter($totalColumns) . $currentRow)
                    ->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                
                $currentRow++;
            }
        }

        // 保存到临时文件
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'report_') . '.xlsx';
        $writer->save($tempFile);

        // 验证文件是否创建成功
        if (!file_exists($tempFile) || filesize($tempFile) == 0) {
            throw new \Exception('Excel文件创建失败');
        }

        Log::info('Excel文件生成成功', [
            'region_id' => $regionId,
            'insurance_type' => $insuranceType,
            'tempfile' => $tempFile,
            'filesize' => filesize($tempFile)
        ]);

        return $tempFile;
    }

    /**
     * 创建ZIP打包文件
     * 
     * @param array $excelFiles Excel文件数组 [filename => filepath]
     * @param string $month 月份
     * @return string ZIP文件路径
     * @throws Exception
     */
    private function createZipPackage($excelFiles, $month)
    {
        $zip = new ZipArchive();
        // 使用英文文件名避免编码问题
        $zipFilename = storage_path('app/temp/registration-reports-' . $month . '.zip');

        // 确保temp目录存在
        $tempDir = storage_path('app/temp');
        if (!file_exists($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        // 删除旧的ZIP文件（如果存在）
        if (file_exists($zipFilename)) {
            unlink($zipFilename);
        }

        $result = $zip->open($zipFilename, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($result !== TRUE) {
            throw new Exception('无法创建ZIP文件，错误代码：' . $result);
        }

        // 添加文件到ZIP - 使用 addFromString 而不是 addFile
        foreach ($excelFiles as $filename => $filepath) {
            if (!file_exists($filepath)) {
                Log::warning('Excel文件不存在', ['filepath' => $filepath]);
                continue;
            }
            
            // 读取文件内容
            $fileContent = file_get_contents($filepath);
            
            // 使用 addFromString 添加到 ZIP
            $added = $zip->addFromString($filename, $fileContent);
            
            if (!$added) {
                Log::warning('无法添加文件到ZIP', [
                    'filename' => $filename,
                    'filepath' => $filepath
                ]);
            } else {
                Log::info('已添加文件到ZIP', [
                    'filename' => $filename,
                    'filesize' => strlen($fileContent)
                ]);
            }
        }

        $zip->close();

        // 验证ZIP文件是否创建成功
        if (!file_exists($zipFilename) || filesize($zipFilename) == 0) {
            throw new Exception('ZIP文件创建失败或为空');
        }

        Log::info('ZIP文件创建成功', [
            'zipfile' => $zipFilename,
            'filesize' => filesize($zipFilename)
        ]);

        // 删除临时Excel文件
        foreach ($excelFiles as $filepath) {
            if (file_exists($filepath)) {
                unlink($filepath);
            }
        }

        return $zipFilename;
    }

    /**
     * 解析字段值（系统字段、日期字段等）
     * 
     * @param array $field 字段配置
     * @param string $month 月份
     * @param array|null $regionData 地区数据（包含公司名称、地区名称等）
     * @return string 字段值
     */
    private function resolveFieldValue($field, $month, $regionData = null)
    {
        if ($field['type'] === 'system') {
            switch ($field['system_field']) {
                case 'company_name':
                    // 优先从地区数据获取公司名称
                    if ($regionData) {
                        return $regionData['company_name'] ?? $regionData['company'] ?? '';
                    }
                    return '';
                case 'region_name':
                    // 从地区数据获取地区名称
                    if ($regionData) {
                        return $regionData['region_name'] ?? $regionData['name'] ?? '';
                    }
                    return '';
                case 'region_code':
                    // 从地区数据获取编号
                    if ($regionData) {
                        return $regionData['code'] ?? $regionData['account_number'] ?? '';
                    }
                    return '';
                case 'account_set_name':
                    // 账套名称从当前用户获取
                    return Auth::user()->accountSet->name ?? '';
                case 'current_year':
                    return date('Y');
                case 'current_month':
                    return $month ?? date('Y-m');
                case 'current_user':
                case 'creator_name':
                    return Auth::user()->name ?? '';
                case 'auditor_name':
                    return ''; // 审核人需要在审批流程中设置
                default:
                    return '';
            }
        } elseif ($field['type'] === 'date') {
            $format = $field['date_format'] ?? 'Y-m-d';
            // 转换格式字符串
            $phpFormat = str_replace(
                ['YYYY', 'MM', 'DD', '年', '月', '日'],
                ['Y', 'm', 'd', '年', '月', '日'],
                $format
            );
            return date($phpFormat);
        } elseif ($field['type'] === 'text') {
            return $field['value'] ?? '';
        }

        return '';
    }

    /**
     * 根据保险类型获取地区详细信息
     * 
     * @param int $regionId 地区ID
     * @param string $insuranceType 保险类型
     * @return array 地区数据
     */
    private function getRegionData($regionId, $insuranceType)
    {
        $region = null;
        
        switch ($insuranceType) {
            case 'social_security':
                $region = SocialSecurityRegion::find($regionId);
                if ($region) {
                    return [
                        'region_name' => $region->name,
                        'company_name' => $region->company,
                        'code' => $region->code,
                    ];
                }
                break;
                
            case 'medical_insurance':
                $region = MedicalInsuranceRegion::find($regionId);
                if ($region) {
                    return [
                        'region_name' => $region->name,
                        'company_name' => $region->company,
                        'code' => $region->code,
                    ];
                }
                break;
                
            case 'housing_fund':
                $region = HousingFundRegion::find($regionId);
                if ($region) {
                    return [
                        'region_name' => $region->region_name,
                        'company_name' => $region->company_name,
                        'account_number' => $region->account_number,
                    ];
                }
                break;
        }
        
        return [];
    }

    /**
     * 获取员工字段值
     * 
     * @param array $employee 员工数据
     * @param string $key 字段键
     * @param int $serialNumber 序号
     * @return mixed 字段值
     */
    private function getEmployeeFieldValue($employee, $key, $serialNumber)
    {
        if ($key === 'serial_number') {
            return $serialNumber;
        }

        // 变更类型转换为中文
        if ($key === 'change_type') {
            $changeType = $employee[$key] ?? '';
            $typeMap = [
                'increase' => '新增',
                'decrease' => '终止',
                'change' => '变更',
            ];
            return $typeMap[$changeType] ?? $changeType;
        }

        return $employee[$key] ?? '';
    }

    /**
     * 获取列字母（A, B, C, ..., Z, AA, AB, ...）
     * 
     * @param int $columnNumber 列号（从1开始）
     * @return string 列字母
     */
    private function getColumnLetter($columnNumber)
    {
        $letter = '';
        while ($columnNumber > 0) {
            $columnNumber--;
            $letter = chr(65 + ($columnNumber % 26)) . $letter;
            $columnNumber = intval($columnNumber / 26);
        }
        return $letter;
    }

    /**
     * 获取保险类型中文名称
     * 
     * @param string $insuranceType 保险类型
     * @return string 中文名称
     */
    private function getInsuranceTypeName($insuranceType)
    {
        $names = [
            'social_security' => '社保',
            'medical_insurance' => '医保',
            'housing_fund' => '公积金'
        ];

        return $names[$insuranceType] ?? $insuranceType;
    }
}
