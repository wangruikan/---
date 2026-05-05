<?php

namespace App\Http\Controllers;

use App\Models\EmployeeContract;
use App\Models\Employee;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EmployeeContractController extends Controller
{
    /**
     * 获取员工的合同列表
     */
    public function index(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        
        // 获取当前账套ID
        $currentAccountSetId = $request->input('current_account_set_id');
        
        $query = EmployeeContract::where('employee_id', $employeeId);
        
        // 账套数据过滤
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            // 非admin用户必须有账套，否则返回空
            $query->whereRaw('1 = 0');
        }
        
        $contracts = $query->with(['creator'])
            ->orderBy('created_at', 'desc')
            ->get();

        $contracts->each(function ($contract) {
            $isOffline = $contract->status === 'completed' && is_null($contract->employee_signed_at);
            $contract->source_type = $isOffline ? 'offline' : 'online';
            $contract->source_text = $isOffline ? '线下' : '线上';
        });

        return response()->json([
            'success' => true,
            'data' => $contracts
        ]);
    }

    /**
     * 创建合同（上传文件）- 简化版
     */
    public function store(Request $request)
    {
        // 简化验证：只验证必填字段，不验证文件类型
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'contract_type' => 'required|in:labor,termination,retirement,confidentiality,other',
            'template_id' => 'nullable|exists:contract_templates,id',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // 调试信息
            \Log::info('EmployeeContract store request data:', [
                'all' => $request->all(),
                'hasFile' => $request->hasFile('contract_file'),
                'hasTemplateId' => $request->has('template_id'),
                'templateId' => $request->input('template_id'),
                'contentType' => $request->header('Content-Type')
            ]);
            
            // 检查是文件上传模式还是模板模式
            if ($request->hasFile('contract_file')) {
                return $this->storeWithFile($request);
            } elseif ($request->has('template_id') && $request->input('template_id')) {
                return $this->storeWithTemplate($request);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => '请提供合同文件或选择合同模板',
                    'debug' => [
                        'hasFile' => $request->hasFile('contract_file'),
                        'hasTemplateId' => $request->has('template_id'),
                        'templateId' => $request->input('template_id'),
                        'allData' => $request->all()
                    ]
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交合同供员工签署
     */
    public function submit(Request $request, $id)
    {
        $contract = EmployeeContract::findOrFail($id);

        if ($contract->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只有草稿状态的合同才能提交'
            ], 422);
        }

        $contract->update([
            'status' => 'pending_sign',
            'uploaded_at' => now(),
        ]);

        // 发送短信通知员工签署合同
        $employee = Employee::find($contract->employee_id);
        if ($employee && $employee->phone) {
            try {
                $contractTypeName = $this->getContractTypeName($contract->contract_type);
                SmsService::sendContractPendingNotice(
                    $employee->phone,
                    $employee->name,
                    $contractTypeName
                );
                \Log::info('合同待签署短信已发送', [
                    'contract_id' => $contract->id,
                    'employee_id' => $employee->id,
                    'phone' => $employee->phone
                ]);
            } catch (\Exception $e) {
                \Log::warning('发送短信通知失败', [
                    'contract_id' => $contract->id,
                    'employee_id' => $employee->id,
                    'phone' => $employee->phone,
                    'error' => $e->getMessage()
                ]);
                // 短信发送失败不影响合同提交流程
            }
        }

        return response()->json([
            'success' => true,
            'message' => '合同已提交，等待员工签署',
            'data' => $contract
        ]);
    }

    /**
     * 员工签署合同（小程序端调用）
     */
    public function employeeSign(Request $request, $id)
    {
        $contract = EmployeeContract::findOrFail($id);

        if ($contract->status !== 'pending_sign') {
            return response()->json([
                'success' => false,
                'message' => '该合同当前状态不允许签署'
            ], 422);
        }

        $contract->update([
            'status' => 'employee_signed',
            'employee_signed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '签署成功',
            'data' => $contract
        ]);
    }

    /**
     * 完成合同（双方都签署完成）
     */
    public function complete(Request $request, $id)
    {
        $contract = EmployeeContract::findOrFail($id);

        if ($contract->status !== 'employee_signed') {
            return response()->json([
                'success' => false,
                'message' => '员工未签署，无法完成合同'
            ], 422);
        }

        try {
            // 管理员确认时，合成PDF
            \Log::info('开始合成签名PDF', [
                'contract_id' => $id,
                'signature_image' => $contract->signature_image,
                'sign_x_percent' => $contract->sign_x_percent,
                'sign_y_percent' => $contract->sign_y_percent,
                'sign_page_index' => $contract->sign_page_index,
                'signature_positions' => $contract->signature_positions
            ]);

            $employee = \App\Models\Employee::find($contract->employee_id);
            if ($contract->signature_image) {
                
                // 检查是否有预设的多签名位置
                $signaturePositions = $contract->signature_positions;
                
                if (!empty($signaturePositions) && is_array($signaturePositions)) {
                    // 使用预设的多个签名位置
                    \Log::info('使用预设签名位置', ['positions_count' => count($signaturePositions)]);
                    
                    $newPdfPath = $this->mergeMultipleSignaturesToPDF(
                        $contract->contract_file,
                        $contract->signature_image,
                        $employee->name,
                        $contract->employee_signed_at->format('Y-m-d H:i:s'),
                        $signaturePositions
                    );
                } elseif ($contract->sign_x_percent !== null && $contract->sign_y_percent !== null) {
                    // 兼容旧版本：使用单个签名位置
                    \Log::info('使用单个签名位置（旧版本兼容）');
                    
                    $newPdfPath = $this->mergeSignatureToPDF(
                        $contract->contract_file,
                        $contract->signature_image,
                        $employee->name,
                        $contract->employee_signed_at->format('Y-m-d H:i:s'),
                        $contract->sign_x_percent,
                        $contract->sign_y_percent,
                        $contract->sign_page_index
                    );
                } else {
                    // 没有签名位置信息，跳过合成
                    \Log::warning('没有签名位置信息，跳过PDF合成');
                    $newPdfPath = $contract->contract_file;
                }
                
                // 删除原PDF文件
                if ($contract->contract_file && $newPdfPath !== $contract->contract_file) {
                    \Storage::disk('public')->delete($contract->contract_file);
                }
                
                // 更新合同文件路径
                $contract->contract_file = $newPdfPath;
                
                \Log::info('PDF合成成功', ['new_path' => $newPdfPath]);
            }

            $contract->status = 'completed';
            $contract->completed_at = now();
            $contract->save();
            
            // 检查员工是否是线下入职，如果是则标记合同已上传
            $employee = Employee::find($contract->employee_id);
            if ($employee && $employee->is_offline_onboarding && !$employee->contract_uploaded) {
                $employee->update(['contract_uploaded' => true]);
                \Log::info('线下入职员工合同已上传', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'contract_id' => $contract->id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => '合同已完成',
                'data' => $contract
            ]);
            
        } catch (\Exception $e) {
            \Log::error('合同完成失败', [
                'contract_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '合同完成失败：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 合成签名到PDF（管理员确认时调用）
     */
    private function mergeSignatureToPDF($originalPdfPath, $signatureImagePath, $employeeName, $signTime, $xPercent, $yPercent, $pageIndex)
    {
        if (extension_loaded('imagick')) {
            return $this->mergeWithImagick($originalPdfPath, $signatureImagePath, $employeeName, $signTime, $xPercent, $yPercent, $pageIndex);
        } else {
            // 备用：使用FPDI追加签名页
            return $this->mergeWithFPDI($originalPdfPath, $signatureImagePath, $employeeName, $signTime);
        }
    }
    
    /**
     * 使用Imagick合成签名
     */
    private function mergeWithImagick($originalPdfPath, $signatureImagePath, $employeeName, $signTime, $xPercent, $yPercent, $pageIndex)
    {
        $originalFullPath = storage_path('app/public/' . $originalPdfPath);
        $signatureFullPath = storage_path('app/public/' . $signatureImagePath);
        
        $imagick = new \Imagick();
        
        // 在Windows上，手动设置Ghostscript路径
        if (PHP_OS_FAMILY === 'Windows') {
            $gsPath = 'C:\\Program Files\\gs\\gs10.02.1\\bin\\gswin64c.exe';
            if (file_exists($gsPath)) {
                putenv("MAGICK_GHOSTSCRIPT_PATH={$gsPath}");
                \Log::info('设置Ghostscript路径', ['path' => $gsPath]);
            }
        }
        
        $imagick->setResolution(150, 150);
        $imagick->readImage($originalFullPath);
        
        // 跳转到指定页
        $imagick->setIteratorIndex($pageIndex);
        
        // 获取页面尺寸
        $geometry = $imagick->getImageGeometry();
        $pageWidth = $geometry['width'];
        $pageHeight = $geometry['height'];
        
        // 根据百分比计算实际坐标
        $actualX = ($xPercent / 100) * $pageWidth;
        $actualY = ($yPercent / 100) * $pageHeight;
        
        \Log::info('签名坐标计算', [
            'page_size' => [$pageWidth, $pageHeight],
            'percent' => [$xPercent, $yPercent],
            'actual' => [$actualX, $actualY]
        ]);
        
        // 读取并缩放签名图片
        $signature = new \Imagick($signatureFullPath);
        // 签名图片放大到原来的2倍
        $signature->scaleImage(400, 0);
        
        // 添加签名到PDF
        $imagick->compositeImage($signature, \Imagick::COMPOSITE_OVER, $actualX, $actualY);
        
        // 添加签署文字
        $draw = new \ImagickDraw();
        if (file_exists(storage_path('fonts/simhei.ttf'))) {
            $draw->setFont(storage_path('fonts/simhei.ttf'));
        }
        $draw->setFontSize(10);
        $draw->setFillColor('#000000');
        
        $signText = "{$employeeName} {$signTime}";
        $imagick->annotateImage($draw, $actualX, $actualY + 80, 0, $signText);
        
        // 生成新PDF
        $newFilename = 'contracts/signed_' . time() . '_' . basename($originalPdfPath);
        $newFullPath = storage_path('app/public/' . $newFilename);
        
        $imagick->setImageFormat('pdf');
        $imagick->writeImages($newFullPath, true);
        
        $imagick->clear();
        $imagick->destroy();
        $signature->clear();
        $signature->destroy();
        
        return $newFilename;
    }
    
    /**
     * 使用FPDI追加签名页（备用方案）
     */
    private function mergeWithFPDI($originalPdfPath, $signatureImagePath, $employeeName, $signTime)
    {
        // 如果Imagick不可用，返回原路径（签名单独保存）
        \Log::warning('Imagick不可用，签名单独保存');
        return $originalPdfPath;
    }
    
    /**
     * 合成多个签名到PDF（支持预设的多个签名位置）
     */
    private function mergeMultipleSignaturesToPDF($originalPdfPath, $signatureImagePath, $employeeName, $signTime, $positions)
    {
        if (!extension_loaded('imagick')) {
            \Log::warning('Imagick不可用，无法合成多签名');
            return $originalPdfPath;
        }
        
        $originalFullPath = storage_path('app/public/' . $originalPdfPath);
        $signatureFullPath = storage_path('app/public/' . $signatureImagePath);
        
        if (!file_exists($originalFullPath)) {
            $originalFullPath = public_path('storage/' . $originalPdfPath);
        }
        if (!file_exists($signatureFullPath)) {
            $signatureFullPath = public_path('storage/' . $signatureImagePath);
        }
        
        $imagick = new \Imagick();
        
        // 在Windows上，手动设置Ghostscript路径
        if (PHP_OS_FAMILY === 'Windows') {
            $gsPath = 'C:\\Program Files\\gs\\gs10.02.1\\bin\\gswin64c.exe';
            if (file_exists($gsPath)) {
                putenv("MAGICK_GHOSTSCRIPT_PATH={$gsPath}");
            }
        }
        
        $imagick->setResolution(150, 150);
        $imagick->readImage($originalFullPath);
        
        // 读取签名图片
        $signature = new \Imagick($signatureFullPath);
        // 签名图片放大到原来的2倍
        $signature->scaleImage(300, 0); // 调整签名大小
        
        // 按页码分组签名位置
        $pagePositions = [];
        foreach ($positions as $pos) {
            $page = $pos['page'] ?? 0;
            if (!isset($pagePositions[$page])) {
                $pagePositions[$page] = [];
            }
            $pagePositions[$page][] = $pos;
        }
        
        \Log::info('多签名位置分组', ['pagePositions' => $pagePositions]);
        
        // 遍历每个页面添加签名
        $totalPages = $imagick->getNumberImages();
        for ($pageIndex = 0; $pageIndex < $totalPages; $pageIndex++) {
            if (!isset($pagePositions[$pageIndex])) {
                continue; // 该页没有签名位置
            }
            
            $imagick->setIteratorIndex($pageIndex);
            $geometry = $imagick->getImageGeometry();
            $pageWidth = $geometry['width'];
            $pageHeight = $geometry['height'];
            
            foreach ($pagePositions[$pageIndex] as $pos) {
                // 计算实际坐标（从百分比或像素）
                $x = isset($pos['x_percent']) 
                    ? ($pos['x_percent'] / 100) * $pageWidth 
                    : ($pos['x'] ?? 0);
                $y = isset($pos['y_percent']) 
                    ? ($pos['y_percent'] / 100) * $pageHeight 
                    : ($pos['y'] ?? 0);
                
                \Log::info('添加签名', [
                    'page' => $pageIndex,
                    'position' => $pos,
                    'calculated' => [$x, $y],
                    'page_size' => [$pageWidth, $pageHeight]
                ]);
                
                // 克隆签名图片并添加到页面
                $signatureCopy = clone $signature;
                $imagick->compositeImage($signatureCopy, \Imagick::COMPOSITE_OVER, (int)$x, (int)$y);
                $signatureCopy->clear();
                $signatureCopy->destroy();
            }
        }
        
        // 在最后一个签名位置添加签署信息文字
        if (!empty($positions)) {
            $lastPos = end($positions);
            $lastPage = $lastPos['page'] ?? 0;
            $imagick->setIteratorIndex($lastPage);
            $geometry = $imagick->getImageGeometry();
            
            $x = isset($lastPos['x_percent']) 
                ? ($lastPos['x_percent'] / 100) * $geometry['width'] 
                : ($lastPos['x'] ?? 0);
            $y = isset($lastPos['y_percent']) 
                ? ($lastPos['y_percent'] / 100) * $geometry['height'] 
                : ($lastPos['y'] ?? 0);
            
            $draw = new \ImagickDraw();
            if (file_exists(storage_path('fonts/simhei.ttf'))) {
                $draw->setFont(storage_path('fonts/simhei.ttf'));
            }
            $draw->setFontSize(10);
            $draw->setFillColor('#000000');
            
            $signText = "{$employeeName} {$signTime}";
            $imagick->annotateImage($draw, (int)$x, (int)$y + 60, 0, $signText);
        }
        
        // 生成新PDF
        $newFilename = 'contracts/signed_multi_' . time() . '_' . basename($originalPdfPath);
        $newFullPath = storage_path('app/public/' . $newFilename);
        
        // 确保目录存在
        $dir = dirname($newFullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $imagick->setImageFormat('pdf');
        $imagick->writeImages($newFullPath, true);
        
        $imagick->clear();
        $imagick->destroy();
        $signature->clear();
        $signature->destroy();
        
        \Log::info('多签名PDF合成完成', ['new_path' => $newFilename]);

        return $newFilename;
    }

    private function generateSignedNoticeCopies(EmployeeContract $contract, ?Employee $employee): array
    {
        if (!$employee || !$contract->signature_image || $contract->contract_type !== 'labor') {
            return [];
        }

        if (!extension_loaded('imagick')) {
            \Log::warning('Imagick不可用，须知签名副本将使用FPDI兜底生成', ['contract_id' => $contract->id]);
        }

        $projectQuery = $employee->projects();
        $accountSetId = $contract->account_set_id ?: $employee->account_set_id;
        if ($accountSetId) {
            $projectQuery->where('projects.account_set_id', $accountSetId);
        }

        $projects = $projectQuery->get();
        if ($projects->isEmpty()) {
            return [];
        }

        $noticeMetaById = [];
        foreach ($projects as $project) {
            $noticeIds = [];
            if (!empty($project->contract_notice_files)) {
                $noticeIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $project->contract_notice_files)))));
            } elseif (!empty($project->contract_notice_file_id)) {
                $noticeIds = [(int) $project->contract_notice_file_id];
            }

            if (empty($noticeIds)) {
                continue;
            }

            $files = \App\Models\SharedFile::whereIn('id', $noticeIds)
                ->where('file_category', 'notice')
                ->where('account_set_id', $project->account_set_id)
                ->get()
                ->keyBy('id');

            $positionsMap = is_array($project->notice_placeholder_positions) ? $project->notice_placeholder_positions : [];
            foreach ($noticeIds as $noticeId) {
                if (!isset($files[$noticeId])) {
                    continue;
                }
                if (!isset($noticeMetaById[$noticeId])) {
                    $noticeMetaById[$noticeId] = [
                        'file' => $files[$noticeId],
                        'positions' => is_array($positionsMap[$noticeId] ?? null) ? $positionsMap[$noticeId] : [],
                    ];
                }
            }
        }

        if (empty($noticeMetaById)) {
            return [];
        }

        $signTime = $contract->employee_signed_at
            ? $contract->employee_signed_at->format('Y-m-d H:i:s')
            : now()->format('Y-m-d H:i:s');

        $results = [];
        foreach ($noticeMetaById as $noticeId => $meta) {
            $sharedFile = $meta['file'];
            $positions = $meta['positions'];
            if (empty($sharedFile->path)) {
                continue;
            }

            $signedPath = $this->mergeNoticeSignatureToPDF($sharedFile->path, $contract->signature_image, $employee->name, $signTime, $positions);
            if (!$signedPath) {
                continue;
            }

            $results[] = [
                'notice_file_id' => (int) $noticeId,
                'template_path' => $sharedFile->path,
                'signed_path' => $signedPath,
                'name' => $sharedFile->original_name ?: $sharedFile->name,
            ];
        }

        return $results;
    }

    private function mergeNoticeSignatureToPDF(string $noticeTemplatePath, string $signatureImagePath, string $employeeName, string $signTime, array $positions = []): ?string
    {
        $templateFullPath = storage_path('app/public/' . $noticeTemplatePath);
        if (!file_exists($templateFullPath)) {
            $templateFullPath = public_path('storage/' . $noticeTemplatePath);
        }
        if (!file_exists($templateFullPath)) {
            \Log::warning('须知模板文件不存在，跳过签名副本生成', ['template_path' => $noticeTemplatePath]);
            return null;
        }

        $signatureFullPath = storage_path('app/public/' . $signatureImagePath);
        if (!file_exists($signatureFullPath)) {
            $signatureFullPath = public_path($signatureImagePath);
        }
        if (!file_exists($signatureFullPath)) {
            \Log::warning('签名图片不存在，跳过须知签名副本生成', ['signature_path' => $signatureImagePath]);
            return null;
        }

        $imagick = new \Imagick();
        if (PHP_OS_FAMILY === 'Windows') {
            $gsPath = 'C:\\Program Files\\gs\\gs10.02.1\\bin\\gswin64c.exe';
            if (file_exists($gsPath)) {
                putenv("MAGICK_GHOSTSCRIPT_PATH={$gsPath}");
            }
        }

        $imagick->setResolution(150, 150);
        $imagick->readImage($templateFullPath);

        $signature = new \Imagick($signatureFullPath);
        // 签名图片放大到原来的2倍
        $signature->scaleImage(300, 0);

        $normalizedPositions = [];
        foreach ($positions as $pos) {
            if (($pos['type'] ?? '') !== 'employee_signature') {
                continue;
            }
            $normalizedPositions[] = $pos;
        }

        if (empty($normalizedPositions)) {
            $normalizedPositions[] = [
                'x_percent' => 70,
                'y_percent' => 85,
                'page' => max(0, $imagick->getNumberImages() - 1),
            ];
        }

        $pagePositions = [];
        foreach ($normalizedPositions as $pos) {
            $page = (int) ($pos['page'] ?? 0);
            if (!isset($pagePositions[$page])) {
                $pagePositions[$page] = [];
            }
            $pagePositions[$page][] = $pos;
        }

        $totalPages = $imagick->getNumberImages();
        for ($pageIndex = 0; $pageIndex < $totalPages; $pageIndex++) {
            if (!isset($pagePositions[$pageIndex])) {
                continue;
            }

            $imagick->setIteratorIndex($pageIndex);
            $geometry = $imagick->getImageGeometry();
            $pageWidth = $geometry['width'];
            $pageHeight = $geometry['height'];

            foreach ($pagePositions[$pageIndex] as $pos) {
                $x = isset($pos['x_percent'])
                    ? ($pos['x_percent'] / 100) * $pageWidth
                    : ($pos['x'] ?? 0);
                $y = isset($pos['y_percent'])
                    ? ($pos['y_percent'] / 100) * $pageHeight
                    : ($pos['y'] ?? 0);

                $signatureCopy = clone $signature;
                $imagick->compositeImage($signatureCopy, \Imagick::COMPOSITE_OVER, (int) $x, (int) $y);
                $signatureCopy->clear();
                $signatureCopy->destroy();
            }
        }

        $lastPos = end($normalizedPositions);
        $lastPage = (int) ($lastPos['page'] ?? 0);
        if ($lastPage < $totalPages) {
            $imagick->setIteratorIndex($lastPage);
            $geometry = $imagick->getImageGeometry();
            $x = isset($lastPos['x_percent'])
                ? ($lastPos['x_percent'] / 100) * $geometry['width']
                : ($lastPos['x'] ?? 0);
            $y = isset($lastPos['y_percent'])
                ? ($lastPos['y_percent'] / 100) * $geometry['height']
                : ($lastPos['y'] ?? 0);

            $draw = new \ImagickDraw();
            if (file_exists(storage_path('fonts/simhei.ttf'))) {
                $draw->setFont(storage_path('fonts/simhei.ttf'));
            }
            $draw->setFontSize(10);
            $draw->setFillColor('#000000');
            $imagick->annotateImage($draw, (int) $x, (int) $y + 60, 0, "{$employeeName} {$signTime}");
        }

        $newFilename = 'contracts/notices/signed_notice_' . time() . '_' . uniqid() . '_' . basename($noticeTemplatePath);
        $newFullPath = storage_path('app/public/' . $newFilename);
        $dir = dirname($newFullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $imagick->setImageFormat('pdf');
        $imagick->writeImages($newFullPath, true);

        $imagick->clear();
        $imagick->destroy();
        $signature->clear();
        $signature->destroy();

        return $newFilename;
    }

    /**
     * 删除合同
     */
    public function destroy(Request $request, $id)
    {
        $contract = EmployeeContract::findOrFail($id);

        // 只能删除草稿状态的合同
        if ($contract->status !== 'draft') {
            return response()->json([
                'success' => false,
                'message' => '只能删除草稿状态的合同'
            ], 422);
        }

        // 删除文件
        if ($contract->contract_file) {
            Storage::disk('public')->delete($contract->contract_file);
        }

        $contract->delete();

        return response()->json([
            'success' => true,
            'message' => '合同删除成功'
        ]);
    }

    /**
     * 下载合同文件
     */
    public function download($id)
    {
        try {
            \Log::info('开始下载合同', ['contract_id' => $id]);
            
            $contract = EmployeeContract::findOrFail($id);
            \Log::info('合同信息', ['contract_file' => $contract->contract_file, 'original_filename' => $contract->original_filename]);

            if (!$contract->contract_file) {
                \Log::error('合同文件路径为空');
                return response()->json([
                    'success' => false,
                    'message' => '合同文件不存在'
                ], 404);
            }

            $filePathPublic  = public_path('storage/' . $contract->contract_file);  
            $filePathStorage = storage_path('app/public/' . $contract->contract_file);
            $filePath = null;
            // 优先查找 public/storage（因为上传时保存到这里）
            if (file_exists($filePathPublic)) {
                $filePath = $filePathPublic;
            } elseif (file_exists($filePathStorage)) {
                $filePath = $filePathStorage;
                \Log::info('使用storage路径提供文件', ['filePath' => $filePathStorage]);
            }

            \Log::info('完整文件路径', ['preferred' => $filePathStorage, 'fallback' => $filePathPublic, 'selected' => $filePath]);

            if (!$filePath) {
                \Log::error('文件不存在(两处均未找到)', ['storage_path' => $filePathStorage, 'public_path' => $filePathPublic]);
                return response()->json([
                    'success' => false,
                    'message' => '文件不存在: ' . $filePathStorage
                ], 404);
            }

            $downloadName = $contract->original_filename ?: basename($contract->contract_file);
            $downloadName = trim(str_replace(['/', '\\'], '-', $downloadName));
            $downloadName = preg_replace('/[\x00-\x1F\x7F]/u', '', $downloadName);
            if ($downloadName === '') {
                $downloadName = 'contract_' . $contract->id . '.pdf';
            }

            \Log::info('开始发送文件', ['download_name' => $downloadName]);
            return response()->download($filePath, $downloadName);
        } catch (\Exception $e) {
            \Log::error('下载合同失败', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'message' => '下载失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 使用文件上传创建合同
     */
    private function storeWithFile(Request $request)
    {
        try {
            \Log::info('=== storeWithFile 开始 ===', [
                'employee_id' => $request->employee_id,
                'contract_type' => $request->contract_type,
                'has_file' => $request->hasFile('contract_file'),
            ]);

            $file = $request->file('contract_file');
            if (!$file) {
                \Log::error('storeWithFile: 未收到文件');
                return response()->json([
                    'success' => false,
                    'message' => '未收到文件'
                ], 400);
            }

            $originalFilename = $file->getClientOriginalName();
            \Log::info('storeWithFile: 文件信息', [
                'original_name' => $originalFilename,
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'extension' => $file->getClientOriginalExtension(),
            ]);
            
            // 简单验证文件扩展名
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
            
            if (!in_array($extension, $allowedExtensions)) {
                \Log::warning('storeWithFile: 文件类型不允许', ['extension' => $extension]);
                return response()->json([
                    'success' => false,
                    'message' => '只支持PDF、Word文档或图片格式'
                ], 400);
            }
            
            // 保存文件
            \Log::info('storeWithFile: 开始保存文件到 storage/app/public/contracts');
            $path = $file->store('contracts', 'public');
            \Log::info('storeWithFile: 文件保存成功', [
                'relative_path' => $path,
                'full_path' => storage_path('app/public/' . $path),
                'file_exists' => file_exists(storage_path('app/public/' . $path)),
            ]);

            // 获取当前账套ID
            $currentAccountSetId = $request->input('current_account_set_id');

            \Log::info('storeWithFile: 开始创建数据库记录', [
                'employee_id' => $request->employee_id,
                'account_set_id' => $currentAccountSetId,
                'contract_file' => $path,
            ]);

            $contract = EmployeeContract::create([
                'employee_id' => $request->employee_id,
                'account_set_id' => $currentAccountSetId,
                'contract_type' => $request->contract_type,
                'contract_file' => $path,
                'original_filename' => $originalFilename,
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'notes' => $request->notes,
            ]);

            \Log::info('storeWithFile: 完成', [
                'contract_id' => $contract->id,
                'contract_file' => $contract->contract_file,
            ]);

            return response()->json([
                'success' => true,
                'message' => '合同文件上传成功',
                'data' => $contract
            ]);
        } catch (\Exception $e) {
            \Log::error('storeWithFile: 异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => '上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 使用模板创建合同（带数据填充）
     */
    public function createWithPlaceholderFill(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'template_id' => 'required|exists:contract_templates,id',
            'contract_type' => 'required|in:labor,termination,retirement,confidentiality,other',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // 获取模板信息
            $template = \App\Models\ContractTemplate::with('sharedFile')->findOrFail($request->template_id);
            
            // 验证模板类型是否匹配
            if ($template->contract_type !== $request->contract_type) {
                return response()->json([
                    'success' => false,
                    'message' => '模板类型与合同类型不匹配'
                ], 400);
            }

            // 获取员工信息
            $employee = \App\Models\Employee::findOrFail($request->employee_id);
            
            // 验证员工是否属于该模板的项目
            if (!$employee->project_ids || !in_array($template->project_id, $employee->project_ids)) {
                return response()->json([
                    'success' => false,
                    'message' => '员工不属于该模板的项目'
                ], 400);
            }

            // 检查是否有占位符位置设置
            if (!$template->placeholder_positions) {
                return response()->json([
                    'success' => false,
                    'message' => '该模板尚未设置占位符位置，请先在项目管理中设置'
                ], 400);
            }

            // 解析合同开始和结束日期的年月日
            $contractStartYear = $employee->contract_start_date?->format('Y');
            $contractStartMonth = $employee->contract_start_date?->format('m');
            $contractStartDay = $employee->contract_start_date?->format('d');
            $contractEndYear = $employee->contract_end_date?->format('Y');
            $contractEndMonth = $employee->contract_end_date?->format('m');
            $contractEndDay = $employee->contract_end_date?->format('d');
            $salaryItems = is_array($employee->salary_items) ? $employee->salary_items : [];
            $comprehensiveSalary = $this->extractSalaryItemAmount($salaryItems, ['综合薪资', '综合工资']);
            $probationSalary = $this->extractSalaryItemAmount($salaryItems, ['试用期薪资', '试用期工资']);
            $performanceSalary = $this->extractSalaryItemAmount($salaryItems, ['绩效薪资', '绩效工资']);

            // 返回模板和员工信息，让前端进行数据填充
            return response()->json([
                'success' => true,
                'message' => '准备数据填充',
                'data' => [
                    'template' => [
                        'id' => $template->id,
                        'name' => $template->sharedFile->name,
                        'file_url' => '/storage/' . $template->sharedFile->path, // 使用相对路径，通过Vite代理
                        'placeholder_positions' => $template->placeholder_positions
                    ],
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->name,
                        'id_number' => $employee->id_number,
                        'phone' => $employee->phone,
                        'address' => $employee->address,
                        'gender' => $employee->gender,
                        'birth_date' => $employee->birth_date?->format('Y-m-d'),
                        'nationality' => $employee->nationality,
                        'education' => $employee->education,
                        'position' => $employee->position,
                        'employee_number' => $employee->employee_number,
                        'email' => $employee->email_address ?: $employee->email,
                        'bank_name' => $employee->bank_name,
                        'bank_account' => $employee->bank_account,
                        'bank_account_holder' => $employee->bank_account_holder,
                        'basic_salary' => $employee->basic_salary,
                        'comprehensive_salary' => $comprehensiveSalary,
                        'probation_salary' => $probationSalary,
                        'performance_salary' => $performanceSalary,
                        'signing_location' => $employee->signing_location,
                        'household_type' => $employee->household_type,
                        // 条件性打勾字段
                        'gender_male_check' => $employee->gender === 'male' ? '√' : '',
                        'gender_female_check' => $employee->gender === 'female' ? '√' : '',
                        'household_agricultural_check' => $employee->household_type === 'agricultural' ? '√' : '',
                        'household_non_agricultural_check' => $employee->household_type === 'non_agricultural' ? '√' : '',
                        'hire_date' => $employee->hire_date?->format('Y-m-d'),
                        'contract_sign_date' => now()->format('Y-m-d'),
                        'contract_start_date' => $employee->contract_start_date?->format('Y-m-d'),
                        'contract_end_date' => $employee->contract_end_date?->format('Y-m-d'),
                        'contract_months' => $employee->contract_months,
                        'contract_start_year' => $contractStartYear,
                        'contract_start_month' => $contractStartMonth,
                        'contract_start_day' => $contractStartDay,
                        'contract_end_year' => $contractEndYear,
                        'contract_end_month' => $contractEndMonth,
                        'contract_end_day' => $contractEndDay,
                        'emergency_contact' => $employee->emergency_contact,
                        'emergency_phone' => $employee->emergency_phone,
                        'household_address' => $employee->household_address,
                        'residence_address' => $employee->residence_address,
                        'contact_address' => $employee->contact_address
                    ],
                    'contract_type' => $request->contract_type,
                    'notes' => $request->notes
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '创建合同失败: ' . $e->getMessage()
            ], 500);
        }
    }

    private function extractSalaryItemAmount(array $salaryItems, array $targetNames): ?float
    {
        foreach ($salaryItems as $item) {
            $name = trim((string)($item['name'] ?? ''));
            if ($name === '' || !in_array($name, $targetNames, true)) {
                continue;
            }
            if (!isset($item['amount']) || $item['amount'] === '') {
                return null;
            }
            return (float) $item['amount'];
        }

        return null;
    }

    /**
     * 保存填充后的合同文件
     */
    public function saveFilledContract(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'template_id' => 'required|exists:contract_templates,id',
            'contract_type' => 'required|in:labor,termination,retirement,confidentiality,other',
            'filled_pdf' => 'required|file|mimes:pdf|max:10240', // 10MB限制
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            \Log::warning('saveFilledContract: 验证失败', ['errors' => $validator->errors()]);
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \Log::info('=== saveFilledContract 开始 ===', [
                'employee_id' => $request->employee_id,
                'template_id' => $request->template_id,
                'has_file' => $request->hasFile('filled_pdf'),
            ]);

            // 获取模板信息
            $template = \App\Models\ContractTemplate::with('sharedFile')->findOrFail($request->template_id);
            \Log::info('saveFilledContract: 模板信息', ['template_id' => $template->id, 'template_name' => $template->sharedFile->name]);
            
            // 获取员工信息
            $employee = \App\Models\Employee::findOrFail($request->employee_id);
            \Log::info('saveFilledContract: 员工信息', ['employee_id' => $employee->id, 'employee_name' => $employee->name]);

            // 保存填充后的PDF文件
            $file = $request->file('filled_pdf');
            \Log::info('saveFilledContract: 文件信息', [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
            ]);

            $filename = 'contract_' . $employee->id . '_' . uniqid() . '_' . time() . '.pdf';
            $targetPath = 'contracts/' . $filename;
            $fullTargetPath = public_path('storage/' . $targetPath);
            
            \Log::info('saveFilledContract: 目标路径', [
                'relative_path' => $targetPath,
                'full_path' => $fullTargetPath,
            ]);
            
            // 确保目标目录存在
            $targetDir = dirname($fullTargetPath);
            if (!is_dir($targetDir)) {
                \Log::info('saveFilledContract: 创建目录', ['dir' => $targetDir]);
                mkdir($targetDir, 0755, true);
            }
            
            // 移动文件
            \Log::info('saveFilledContract: 开始移动文件');
            $file->move($targetDir, $filename);
            \Log::info('saveFilledContract: 文件移动成功', [
                'file_exists' => file_exists($fullTargetPath),
                'file_size' => @filesize($fullTargetPath),
            ]);

            // 获取当前账套ID
            $currentAccountSetId = $request->input('current_account_set_id');
            
            // 从模板中提取员工签字占位符位置
            $signaturePositions = [];
            if ($template->placeholder_positions) {
                $positions = is_array($template->placeholder_positions) 
                    ? $template->placeholder_positions 
                    : json_decode($template->placeholder_positions, true);
                
                foreach ($positions as $pos) {
                    if (isset($pos['type']) && $pos['type'] === 'employee_signature') {
                        $signaturePositions[] = [
                            'x' => $pos['x'] ?? 0,
                            'y' => $pos['y'] ?? 0,
                            'x_percent' => isset($pos['x']) && isset($pos['width']) ? null : ($pos['x_percent'] ?? null),
                            'y_percent' => isset($pos['y']) && isset($pos['height']) ? null : ($pos['y_percent'] ?? null),
                            'width' => $pos['width'] ?? 150,
                            'height' => $pos['height'] ?? 50,
                            'page' => $pos['page'] ?? 0,
                        ];
                    }
                }
                \Log::info('saveFilledContract: 提取签名位置', ['signature_positions' => $signaturePositions]);
            }

            \Log::info('saveFilledContract: 开始创建数据库记录', [
                'employee_id' => $request->employee_id,
                'account_set_id' => $currentAccountSetId,
                'contract_file' => $targetPath,
                'signature_positions_count' => count($signaturePositions),
            ]);

            $contract = EmployeeContract::create([
                'employee_id' => $request->employee_id,
                'account_set_id' => $currentAccountSetId,
                'contract_type' => $request->contract_type,
                'contract_file' => $targetPath,
                'original_filename' => $template->sharedFile->name,
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'notes' => $request->notes,
                'signature_positions' => !empty($signaturePositions) ? $signaturePositions : null,
            ]);

            \Log::info('saveFilledContract: 完成', [
                'contract_id' => $contract->id,
                'contract_file' => $contract->contract_file,
            ]);

            return response()->json([
                'success' => true,
                'message' => '合同创建成功',
                'data' => $contract
            ]);

        } catch (\Exception $e) {
            \Log::error('saveFilledContract: 异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => '保存合同失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 使用模板创建合同（旧版本，保持兼容）
     */
    private function storeWithTemplate(Request $request)
    {
        try {
            \Log::info('=== storeWithTemplate 开始 ===', [
                'employee_id' => $request->employee_id,
                'template_id' => $request->template_id,
                'contract_type' => $request->contract_type,
            ]);

            // 获取模板信息
            $template = \App\Models\ContractTemplate::with('sharedFile')->findOrFail($request->template_id);
            \Log::info('storeWithTemplate: 模板信息', ['template_id' => $template->id, 'template_name' => $template->sharedFile->name]);
            
            // 验证模板类型是否匹配
            if ($template->contract_type !== $request->contract_type) {
                \Log::warning('storeWithTemplate: 模板类型不匹配', [
                    'template_type' => $template->contract_type,
                    'request_type' => $request->contract_type,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => '模板类型与合同类型不匹配'
                ], 400);
            }

            // 获取员工信息
            $employee = \App\Models\Employee::findOrFail($request->employee_id);
            \Log::info('storeWithTemplate: 员工信息', ['employee_id' => $employee->id, 'employee_name' => $employee->name]);
            
            // 验证员工是否属于该模板的项目
            if (!$employee->project_ids || !in_array($template->project_id, $employee->project_ids)) {
                \Log::warning('storeWithTemplate: 员工不属于模板项目', [
                    'employee_projects' => $employee->project_ids,
                    'template_project' => $template->project_id,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => '员工不属于该模板的项目'
                ], 400);
            }

            // 复制模板文件到合同目录
            $templateFile = $template->sharedFile;
            $sourcePath = public_path('storage/' . $templateFile->path);
            $targetPath = 'contracts/' . uniqid() . '_' . time() . '.' . pathinfo($templateFile->path, PATHINFO_EXTENSION);
            $fullTargetPath = public_path('storage/' . $targetPath);
            
            \Log::info('storeWithTemplate: 文件路径', [
                'source_path' => $sourcePath,
                'source_exists' => file_exists($sourcePath),
                'target_path' => $targetPath,
                'full_target_path' => $fullTargetPath,
            ]);
            
            if (!file_exists($sourcePath)) {
                \Log::error('storeWithTemplate: 模板文件不存在', ['source_path' => $sourcePath]);
                return response()->json([
                    'success' => false,
                    'message' => '模板文件不存在'
                ], 400);
            }
            
            // 确保目标目录存在
            $targetDir = dirname($fullTargetPath);
            if (!is_dir($targetDir)) {
                \Log::info('storeWithTemplate: 创建目录', ['dir' => $targetDir]);
                mkdir($targetDir, 0755, true);
            }
            
            // 复制文件
            \Log::info('storeWithTemplate: 开始复制文件');
            if (!copy($sourcePath, $fullTargetPath)) {
                \Log::error('storeWithTemplate: 复制文件失败', [
                    'source' => $sourcePath,
                    'target' => $fullTargetPath,
                ]);
                return response()->json([
                    'success' => false,
                    'message' => '复制模板文件失败'
                ], 500);
            }
            
            \Log::info('storeWithTemplate: 文件复制成功', [
                'file_exists' => file_exists($fullTargetPath),
                'file_size' => @filesize($fullTargetPath),
            ]);

            // 获取当前账套ID
            $currentAccountSetId = $request->input('current_account_set_id');

            \Log::info('storeWithTemplate: 开始创建数据库记录', [
                'employee_id' => $request->employee_id,
                'account_set_id' => $currentAccountSetId,
                'contract_file' => $targetPath,
            ]);

            $contract = EmployeeContract::create([
                'employee_id' => $request->employee_id,
                'account_set_id' => $currentAccountSetId,
                'contract_type' => $request->contract_type,
                'contract_file' => $targetPath,
                'original_filename' => $templateFile->name,
                'status' => 'draft',
                'created_by' => $request->user()->id,
                'notes' => $request->notes,
            ]);

            \Log::info('storeWithTemplate: 完成', [
                'contract_id' => $contract->id,
                'contract_file' => $contract->contract_file,
            ]);

            return response()->json([
                'success' => true,
                'message' => '合同创建成功',
                'data' => $contract
            ]);
        } catch (\Exception $e) {
            \Log::error('storeWithTemplate: 异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => '合同创建失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 合成PDF（盖章签字）
     */
    public function mergeSignature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'contract_id' => 'required|exists:employee_contracts,id',
            'signed_pdf' => 'required|file|mimes:pdf|max:10240', // 最大10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $contract = EmployeeContract::findOrFail($request->contract_id);
            
            // 接收前端生成的已签名PDF文件
            $signedPdf = $request->file('signed_pdf');
            $fileName = 'signed_' . time() . '_' . $signedPdf->getClientOriginalName();
            $path = $signedPdf->storeAs('contracts', $fileName, 'public');
            
            // 删除原PDF文件
            if ($contract->contract_file) {
                \Storage::disk('public')->delete($contract->contract_file);
            }
            
            // 更新合同文件路径和状态
            $contract->update([
                'contract_file' => $path,
                'status' => 'employee_signed' // 使用正确的枚举值
            ]);
            
            \Log::info('已签名PDF保存成功', [
                'contract_id' => $request->contract_id,
                'old_file' => $contract->contract_file,
                'new_file' => $path
            ]);

            return response()->json([
                'success' => true,
                'message' => 'PDF合成成功',
                'data' => [
                    'file_path' => $path
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('PDF合成失败', [
                'contract_id' => $request->contract_id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'PDF合成失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取合同类型的中文名称
     */
    private function getContractTypeName($type)
    {
        $types = [
            'labor' => '劳动合同',
            'labor_dispatch' => '劳务派遣合同',
            'part_time' => '非全日制用工合同',
            'internship' => '实习协议',
            'retirement' => '退休返聘协议',
            'project' => '项目合作协议',
            'confidentiality' => '保密协议',
            'non_compete' => '竞业限制协议',
        ];

        return $types[$type] ?? $type;
    }
    
    /**
     * 上传已签署的合同（线下入职专用）
     * 直接上传已经签好字的纸质合同扫描件，无需电子签署流程
     */
    public function uploadSignedContract(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'contract_type' => 'required|in:labor,termination,retirement,confidentiality,other',
            'contract_file' => 'required|file|mimes:pdf|max:10240',
            'notes' => 'nullable|string',
        ], [
            'contract_file.required' => '请上传合同文件',
            'contract_file.mimes' => '合同文件必须是PDF格式',
            'contract_file.max' => '合同文件大小不能超过10MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = Employee::findOrFail($request->employee_id);
            $user = $request->user();
            
            // 上传文件
            $file = $request->file('contract_file');
            $filename = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('contracts', $filename, 'public');
            
            // 创建合同记录，直接设置为已完成状态
            $contract = EmployeeContract::create([
                'employee_id' => $request->employee_id,
                'account_set_id' => $employee->account_set_id,
                'contract_type' => $request->contract_type,
                'contract_file' => $path,
                'status' => 'completed', // 直接设置为已完成
                'notes' => $request->notes,
                'created_by' => $user->id,
                'uploaded_at' => now(),
                'completed_at' => now(), // 直接设置完成时间
            ]);
            
            // 如果员工是线下入职，标记合同已上传
            if ($employee->is_offline_onboarding && !$employee->contract_uploaded) {
                $employee->update(['contract_uploaded' => true]);
                \Log::info('线下入职员工合同已上传（简化流程）', [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->name,
                    'contract_id' => $contract->id
                ]);
                
                // 检查并完成线下合同上传待办任务
                try {
                    \App\Services\PendingTaskService::checkAndCompleteOfflineContractTask($employee);
                    \Log::info('已检查并完成线下合同上传待办任务', [
                        'employee_id' => $employee->id
                    ]);
                } catch (\Exception $e) {
                    \Log::error('检查并完成线下合同上传待办任务失败', [
                        'employee_id' => $employee->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            return response()->json([
                'success' => true,
                'message' => '合同上传成功',
                'data' => $contract
            ]);
            
        } catch (\Exception $e) {
            \Log::error('上传已签署合同失败', [
                'employee_id' => $request->employee_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }
}
