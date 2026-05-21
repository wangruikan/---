<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\EmployeeContract;
use App\Models\OnboardingForm;
use App\Services\SmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

class MiniController extends Controller
{
    /**
     * 按数据库真实列过滤员工更新字段
     */
    private function filterEmployeeUpdateData(array $data): array
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::getColumnListing('employees');
        }

        return array_filter(
            $data,
            fn($value, $key) => in_array($key, $columns, true),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * 身份证号同步（保留唯一校验）
     */
    private function resolveEmployeeIdNumber(Employee $employee, ?string $idNumber): ?string
    {
        if (!$idNumber || $idNumber === $employee->id_number) {
            return $employee->id_number;
        }

        $exists = Employee::where('id_number', $idNumber)
            ->where('id', '!=', $employee->id)
            ->exists();

        if ($exists) {
            \Log::warning('登记表身份证号与其他员工重复，跳过身份证号同步', [
                'employee_id' => $employee->id,
                'id_number' => $idNumber,
            ]);

            return $employee->id_number;
        }

        return $idNumber;
    }

    /**
     * 标准化学历性质，统一为：统招 / 非统招
     */
    private function normalizeEducationTypeValue($value): string
    {
        $rawValue = trim((string) ($value ?? ''));
        if ($rawValue === '') {
            return '';
        }

        $normalizeText = function (string $text): string {
            return preg_replace('/\s+/u', '', trim($text));
        };

        $candidates = [];
        $pushCandidate = function ($text) use (&$candidates, $normalizeText): void {
            if (!is_string($text)) {
                return;
            }
            $normalized = $normalizeText($text);
            if ($normalized !== '' && !in_array($normalized, $candidates, true)) {
                $candidates[] = $normalized;
            }
        };

        $pushCandidate($rawValue);

        // 尝试修复常见编码错乱值（如：缁熸嫑 -> 统招）
        if (function_exists('iconv')) {
            foreach (['GBK', 'GB2312', 'BIG5'] as $legacyEncoding) {
                $legacyBytes = @iconv('UTF-8', $legacyEncoding . '//IGNORE', $rawValue);
                if ($legacyBytes === false || $legacyBytes === '') {
                    continue;
                }
                $recovered = @iconv('UTF-8', 'UTF-8//IGNORE', $legacyBytes);
                if ($recovered !== false && $recovered !== '') {
                    $pushCandidate($recovered);
                }
            }
        }

        $directMap = [
            '统招' => '统招',
            '統招' => '统招',
            '全日制' => '统招',
            '普通全日制' => '统招',
            '全日制统招' => '统招',
            '缁熸嫑' => '统招',
            'ç»æ' => '统招',
            '非统招' => '非统招',
            '非統招' => '非统招',
            '非全日制' => '非统招',
            '闈炵粺鎷' => '非统招',
            'éç»æ' => '非统招',
            '成人教育' => '非统招',
            '自考' => '非统招',
            '函授' => '非统招',
            '网络教育' => '非统招',
            '开放教育' => '非统招',
        ];

        foreach ($candidates as $normalized) {
            if (isset($directMap[$normalized])) {
                return $directMap[$normalized];
            }
        }

        foreach ($candidates as $normalized) {
            if (
                strpos($normalized, '非') !== false ||
                strpos($normalized, '闈') !== false ||
                stripos($normalized, 'feitong') !== false ||
                stripos($normalized, 'fei') !== false
            ) {
                return '非统招';
            }

            if (
                strpos($normalized, '统') !== false ||
                strpos($normalized, '缁熸嫑') !== false ||
                strpos($normalized, '全日制') !== false ||
                stripos($normalized, 'tongzhao') !== false ||
                stripos($normalized, 'tong') !== false
            ) {
                return '统招';
            }
        }

        return '';
    }

    /**
     * 小程序登录（简化版）
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'password' => 'required|string',
        ], [
            'phone.required' => '请输入手机号',
            'password.required' => '请输入密码',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 查询员工
        $employee = Employee::where('phone', $request->phone)->first();

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '手机号或密码错误'
            ], 401);
        }

        // 验证密码（身份证后6位）
        $last6 = substr($employee->id_number, -6);
        if ($request->password !== $last6) {
            return response()->json([
                'success' => false,
                'message' => '手机号或密码错误'
            ], 401);
        }

        // 生成 token（7天有效期）
        $token = $employee->createToken('mini-app')->plainTextToken;
        
        // 获取员工所属项目的登记表类型设置（优先使用活跃项目）
        $registrationFormType = 'onboarding';  // 默认入职登记表
        $employee->load('projects');
        
        // 优先获取活跃项目
        $activeProject = $employee->projects()->wherePivot('status', 'active')->first();
        if ($activeProject) {
            $registrationFormType = $activeProject->registration_form_type ?? 'onboarding';
        } elseif (!empty($employee->project_ids)) {
            // 如果没有活跃项目，使用 project_ids 字段
            $projectId = is_array($employee->project_ids) ? ($employee->project_ids[0] ?? null) : $employee->project_ids;
            if ($projectId) {
                $project = \App\Models\Project::find($projectId);
                if ($project) {
                    $registrationFormType = $project->registration_form_type ?? 'onboarding';
                }
            }
        } elseif ($employee->projects && $employee->projects->count() > 0) {
            // 兜底：使用第一个关联项目
            $project = $employee->projects->first();
            $registrationFormType = $project->registration_form_type ?? 'onboarding';
        }

        return response()->json([
            'success' => true,
            'message' => '登录成功',
            'data' => [
                'token' => $token,
                'employee' => [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'phone' => $employee->phone,
                    'id_number' => $employee->id_number,
                    'account_set_id' => $employee->account_set_id,
                    'registration_form_type' => $registrationFormType,
                    'contract_status' => $employee->contract_status,
                ],
            ]
        ]);
    }


    /**
     * 获取待签署合同列表
     */
    public function getPendingContracts(Request $request)
    {
        $employee = Employee::find($request->user()->id);

        $contracts = EmployeeContract::where('employee_id', $employee->id)
            ->where('status', 'pending_sign')
            ->with(['creator:id,name'])
            ->orderBy('uploaded_at', 'desc')
            ->get();

        // 生成文件访问URL
        $host = $request->getSchemeAndHttpHost();
        foreach ($contracts as $contract) {
            if ($contract->contract_file) {
                $contract->file_url = $host . '/storage/' . $contract->contract_file;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $contracts
        ]);
    }

    /**
     * 获取我的所有合同
     */
    public function getMyContracts(Request $request)
    {
        $employee = Employee::find($request->user()->id);
        
        $status = $request->input('status'); // pending_sign, employee_signed, completed

        $query = EmployeeContract::where('employee_id', $employee->id)
            ->with(['creator:id,name']);

        if ($status) {
            $query->where('status', $status);
        }

        $contracts = $query->orderBy('created_at', 'desc')->get();

        // 生成文件访问URL
        $host = $request->getSchemeAndHttpHost();
        foreach ($contracts as $contract) {
            if ($contract->contract_file) {
                $contract->file_url = $host . '/storage/' . $contract->contract_file;
            }
        }

        return response()->json([
            'success' => true,
            'data' => $contracts
        ]);
    }

    /**
     * 获取合同详情
     */
    public function getContractDetail(Request $request, $id)
    {
        $employee = Employee::find($request->user()->id);

        $contract = EmployeeContract::where('id', $id)
            ->where('employee_id', $employee->id)
            ->with(['creator:id,name'])
            ->first();

        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => '合同不存在或无权查看'
            ], 404);
        }

        // 生成文件访问URL（使用完整的HTTP URL）
        if ($contract->contract_file) {
            // 获取请求的host，这样会自动适应不同的访问地址
            $host = $request->getSchemeAndHttpHost();
            $contract->file_url = $host . '/storage/' . $contract->contract_file;
            
            // 如果是待签署状态，生成PDF预览图片
            if ($contract->status === 'pending_sign') {
                $contract->preview_images = $this->convertPdfToImages($contract->contract_file, $host);
            }
        }

        // 检查是否需要显示须知文件
        $noticeInfo = $this->getContractNoticeInfo($contract, $request);
        
        return response()->json([
            'success' => true,
            'data' => [
                'contract' => $contract,
                'notice_file' => $noticeInfo['notice_file'],
                'notice_files' => $noticeInfo['notice_files'],
                'must_read_notice' => $noticeInfo['must_read_notice']
            ]
        ]);
    }

    /**
     * 获取合同须知信息
     */
    private function getContractNoticeInfo($contract, $request)
    {
        // 只有劳动合同且待签署状态才需要检查须知
        if ($contract->contract_type !== 'labor' || $contract->status !== 'pending_sign') {
            return [
                'notice_file' => null,
                'notice_files' => [],
                'must_read_notice' => false
            ];
        }

        // 获取员工所在的项目
        $employee = Employee::find($contract->employee_id);
        if (!$employee) {
            return [
                'notice_file' => null,
                'notice_files' => [],
                'must_read_notice' => false
            ];
        }

        $projectQuery = $employee->projects();
        $accountSetId = $contract->account_set_id ?: $employee->account_set_id;
        if ($accountSetId) {
            $projectQuery->where('projects.account_set_id', $accountSetId);
        }

        $projects = $projectQuery->get();

        if ($projects->isEmpty()) {
            return [
                'notice_file' => null,
                'notice_files' => [],
                'must_read_notice' => false
            ];
        }

        $host = $request->getSchemeAndHttpHost();

        // 检查项目是否配置了须知文件
        foreach ($projects as $project) {
            $noticeFiles = [];

            // 优先读取多文件配置（contract_notice_files 逗号列表）
            if (!empty($project->contract_notice_files)) {
                $noticeIds = array_values(array_unique(array_filter(array_map('intval', explode(',', $project->contract_notice_files)))));
                if (!empty($noticeIds)) {
                    $filesMap = \App\Models\SharedFile::whereIn('id', $noticeIds)
                        ->where('file_category', 'notice')
                        ->where('account_set_id', $project->account_set_id)
                        ->get()
                        ->keyBy('id');

                    foreach ($noticeIds as $noticeId) {
                        if (!isset($filesMap[$noticeId])) {
                            continue;
                        }
                        $file = $filesMap[$noticeId];
                        if (empty($file->path)) {
                            continue;
                        }
                        $projectNoticePositions = is_array($project->notice_placeholder_positions)
                            ? $project->notice_placeholder_positions
                            : [];
                        $noticeFiles[] = [
                            'id' => $file->id,
                            'name' => $file->original_name ?: '劳动合同须知.pdf',
                            'view_url' => $host . '/storage/' . $file->path,
                            'signature_positions' => $projectNoticePositions[$file->id] ?? []
                        ];
                    }
                }
            }

            // 兼容旧单文件字段
            if (empty($noticeFiles) && $project->contract_notice_file_id) {
                $noticeFile = \App\Models\SharedFile::where('id', $project->contract_notice_file_id)
                    ->where('file_category', 'notice')
                    ->where('account_set_id', $project->account_set_id)
                    ->first();

                if ($noticeFile && $noticeFile->path) {
                    $projectNoticePositions = is_array($project->notice_placeholder_positions)
                        ? $project->notice_placeholder_positions
                        : [];
                    $noticeFiles[] = [
                        'id' => $noticeFile->id,
                        'name' => $noticeFile->original_name ?: '劳动合同须知.pdf',
                        'view_url' => $host . '/storage/' . $noticeFile->path,
                        'signature_positions' => $projectNoticePositions[$noticeFile->id] ?? []
                    ];
                }
            }

            // 兼容旧的文件路径字段
            if (empty($noticeFiles) && $project->labor_contract_notice_file) {
                $noticeFiles[] = [
                    'id' => null,
                    'name' => $project->labor_contract_notice_name ?: '劳动合同须知.pdf',
                    'view_url' => $host . '/storage/' . $project->labor_contract_notice_file
                ];
            }

            if (!empty($noticeFiles)) {
                return [
                    'notice_file' => $noticeFiles[0],
                    'notice_files' => $noticeFiles,
                    'must_read_notice' => true
                ];
            }
        }

        return [
            'notice_file' => null,
            'notice_files' => [],
            'must_read_notice' => false
        ];
    }
    
    /**
     * 将PDF转换为图片（用于小程序预览和点击签名）
     */
    private function convertPdfToImages($pdfPath, $host)
    {
        try {
            $fullPath = storage_path('app/public/' . $pdfPath);
            
            if (!file_exists($fullPath)) {
                \Log::error('PDF文件不存在', ['path' => $fullPath]);
                return [];
            }
            
            // 生成图片缓存目录
            $cacheDir = 'contract_previews/' . pathinfo($pdfPath, PATHINFO_FILENAME);
            $cachePath = storage_path('app/public/' . $cacheDir);
            
            // 如果缓存已存在，直接返回
            if (is_dir($cachePath)) {
                $images = [];
                $files = scandir($cachePath);
                foreach ($files as $file) {
                    if (preg_match('/^page_(\d+)\.png$/', $file)) {
                        $images[] = $host . '/storage/' . $cacheDir . '/' . $file;
                    }
                }
                if (!empty($images)) {
                    sort($images);
                    return $images;
                }
            }
            
            // 创建缓存目录
            if (!is_dir($cachePath)) {
                mkdir($cachePath, 0755, true);
            }
            
            $images = [];
            
            // 使用Imagick转换PDF为图片
            if (extension_loaded('imagick')) {
                $imagick = new \Imagick();
                
                // 在Windows上，手动设置Ghostscript路径
                if (PHP_OS_FAMILY === 'Windows') {
                    $gsPath = 'C:\\Program Files\\gs\\gs10.02.1\\bin\\gswin64c.exe';
                    if (file_exists($gsPath)) {
                        putenv("MAGICK_GHOSTSCRIPT_PATH={$gsPath}");
                        \Log::info('设置Ghostscript路径', ['path' => $gsPath]);
                    }
                }
                
                $imagick->setResolution(150, 150); // 设置分辨率
                $imagick->readImage($fullPath);
                
                $pageCount = $imagick->getNumberImages();
                
                for ($i = 0; $i < $pageCount; $i++) {
                    $imagick->setIteratorIndex($i);
                    $imagick->setImageFormat('png');
                    $imagick->setImageCompressionQuality(85);
                    
                    $imagePath = $cachePath . '/page_' . ($i + 1) . '.png';
                    $imagick->writeImage($imagePath);
                    
                    $images[] = $host . '/storage/' . $cacheDir . '/page_' . ($i + 1) . '.png';
                }
                
                $imagick->clear();
                $imagick->destroy();
            } else {
                // Imagick未安装，返回空数组，小程序将使用原PDF预览
                \Log::warning('Imagick未安装，无法生成PDF预览图片');
                return [];
            }
            
            return $images;
            
        } catch (\Exception $e) {
            \Log::error('PDF转图片失败', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * 员工签署合同（简化版 - 签名位置由后端预设决定）
     * 员工只需签名一次，签名会自动合成到所有预设位置
     */
    public function signContract(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'id_last_4' => 'required|string',
            'signature_image' => 'required|string', // base64签名图片
            // 以下参数为可选（兼容旧版本）
            'signed_pdf' => 'nullable|file|mimes:pdf',
            'notice_signed_pdfs' => 'nullable',
            'notice_signed_file_ids' => 'nullable|array',
            'notice_signed_file_names' => 'nullable|array',
            'sign_x_percent' => 'nullable|numeric|min:0|max:100',
            'sign_y_percent' => 'nullable|numeric|min:0|max:100',
            'page_index' => 'nullable|integer|min:0',
        ], [
            'id_last_4.required' => '请输入身份证后4位',
            'id_last_4.size' => '请输入正确的身份证后4位',
            'signature_image.required' => '请先签名',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $employee = Employee::find($request->user()->id);

        // 验证身份证后4位（兼容空格/全角输入）
        $inputLast4 = preg_replace('/\D/u', '', str_replace('　', '', trim((string) $request->id_last_4)));
        $idNumber = preg_replace('/\D/u', '', (string) $employee->id_number);
        $last4 = substr($idNumber, -4);

        // 兼容身份证末位 X/x：按字符标准化后再比对（不再只保留数字）
        $normalizeLast4 = function (?string $value): string {
            $normalized = str_replace('　', ' ', trim((string) $value));
            if (function_exists('mb_convert_kana')) {
                $normalized = mb_convert_kana($normalized, 'as', 'UTF-8');
            }
            $normalized = preg_replace('/\s+/u', '', $normalized);
            return strtoupper($normalized);
        };
        $inputLast4 = $normalizeLast4($request->id_last_4);
        $idNumber = $normalizeLast4($employee->id_number);
        $last4 = substr($idNumber, -4);

        if ($inputLast4 !== $last4) {
            return response()->json([
                'success' => false,
                'message' => '身份证后4位错误'
            ], 401);
        }

        $contract = EmployeeContract::where('id', $id)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => '合同不存在或无权签署'
            ], 404);
        }

        if ($contract->status !== 'pending_sign') {
            return response()->json([
                'success' => false,
                'message' => '该合同当前状态不允许签署'
            ], 422);
        }

        try {
            \Log::info('收到签署请求', [
                'contract_id' => $id,
                'employee_id' => $employee->id,
                'has_preset_positions' => !empty($contract->signature_positions),
                'has_pdf' => $request->hasFile('signed_pdf'),
                'has_notice_signed_pdfs' => $request->hasFile('notice_signed_pdfs')
            ]);
            
            // 1. 保存签名图片
            $signatureImage = $this->saveSignatureImage($request->signature_image);
            $signedAt = now();
            
            // 2. 根据是否有预设位置决定处理方式
            if ($request->hasFile('signed_pdf')) {
                // 旧版本兼容：前端已经合成好了PDF
                $signedPdf = $request->file('signed_pdf');
                $originalFilename = pathinfo($contract->contract_file, PATHINFO_FILENAME);
                $newPdfPath = 'contracts/signed_' . time() . '_' . $originalFilename . '.pdf';
                $signedPdf->storeAs('', $newPdfPath, 'public');
                
                // 删除原PDF文件
                if ($contract->contract_file) {
                    Storage::disk('public')->delete($contract->contract_file);
                }
                
                $contract->contract_file = $newPdfPath;
                $contract->sign_x_percent = $request->sign_x_percent;
                $contract->sign_y_percent = $request->sign_y_percent;
                $contract->sign_page_index = $request->page_index;
                
                \Log::info('使用前端合成的PDF（旧版本兼容）', ['path' => $newPdfPath]);
            }
            // 新版本：签名位置由后端预设决定，PDF合成在管理员确认时进行
            
            $noticeSignedFiles = [];
            if ($request->hasFile('notice_signed_pdfs')) {
                $noticeSignedPdfs = $request->file('notice_signed_pdfs');
                if (!is_array($noticeSignedPdfs)) {
                    $noticeSignedPdfs = [$noticeSignedPdfs];
                }
                $noticeFileIds = $request->input('notice_signed_file_ids', []);
                $noticeFileNames = $request->input('notice_signed_file_names', []);

                foreach ($noticeSignedPdfs as $index => $noticePdf) {
                    if (!$noticePdf) {
                        continue;
                    }

                    $noticeFileId = $noticeFileIds[$index] ?? null;
                    $noticeSignedPath = 'contracts/notices/signed_notice_' . time() . '_' . uniqid() . '_' . $index . '.pdf';
                    $noticePdf->storeAs('', $noticeSignedPath, 'public');

                    $noticeSignedFiles[] = [
                        'notice_file_id' => $noticeFileId !== null && $noticeFileId !== '' ? (int) $noticeFileId : null,
                        'template_path' => null,
                        'signed_path' => $noticeSignedPath,
                        'name' => $noticeFileNames[$index] ?? ('须知签名副本_' . ($index + 1) . '.pdf'),
                    ];

                    EmployeeContract::create([
                        'employee_id' => $contract->employee_id,
                        'account_set_id' => $contract->account_set_id,
                        'contract_type' => 'other',
                        'contract_file' => $noticeSignedPath,
                        'original_filename' => $noticeFileNames[$index] ?? ('须知签名副本_' . ($index + 1) . '.pdf'),
                        'status' => 'completed',
                        'created_by' => $contract->created_by,
                        'uploaded_at' => now(),
                        'completed_at' => now(),
                        'notes' => '小程序签署时上传的须知签名副本',
                    ]);
                }
            }

            // 3. 更新合同记录
            $contract->signature_image = $signatureImage;
            $contract->notice_signed_files = $noticeSignedFiles;
            $contract->status = 'employee_signed'; // 签署完成，等待HR确认
            $contract->employee_signed_at = $signedAt;
            $contract->sign_ip = $request->ip();
            $contract->sign_device = 'WeChat MiniProgram';
            $contract->save();

            \Log::info('员工签署成功', [
                'contract_id' => $id,
                'employee_id' => $employee->id,
                'has_preset_positions' => !empty($contract->signature_positions)
            ]);

            return response()->json([
                'success' => true,
                'message' => '签署成功，等待HR确认'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('合同签署失败', [
                'contract_id' => $id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '提交失败：' . $e->getMessage()
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
     * 保存签名图片
     */
    private function saveSignatureImage($base64Image)
    {
        // 去掉base64前缀
        $image = preg_replace('/^data:image\/\w+;base64,/', '', $base64Image);
        $image = str_replace(' ', '+', $image);
        $imageData = base64_decode($image);
        
        // 生成文件名
        $filename = uniqid() . '_' . time() . '.png';
        
        // 确保目录存在
        $dir = public_path('uploads/signatures');
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // 保存到 public/uploads/signatures/
        file_put_contents($dir . '/' . $filename, $imageData);
        
        return 'uploads/signatures/' . $filename;
    }
    
    /**
     * 将签名添加到PDF指定位置
     */
    private function addSignatureToPDF($originalPdfPath, $signatureImagePath, $employeeName, $signTime, $signX, $signY, $pageIndex)
    {
        if (extension_loaded('imagick')) {
            return $this->addSignatureWithImagick($originalPdfPath, $signatureImagePath, $employeeName, $signTime, $signX, $signY, $pageIndex);
        } else {
            // 备用方案：使用 Intervention/Image
            return $this->addSignatureWithDompdf($originalPdfPath, $signatureImagePath, $employeeName, $signTime, $signX, $signY, $pageIndex);
        }
    }
    
    /**
     * 使用 Intervention/Image 添加签名（备用方案）
     */
    private function addSignatureWithDompdf($originalPdfPath, $signatureImagePath, $employeeName, $signTime, $signX, $signY, $pageIndex)
    {
        try {
            \Log::info('Imagick未安装，使用备用方案处理签名');
            
            $originalFullPath = storage_path('app/public/' . $originalPdfPath);
            $signatureFullPath = storage_path('app/public/' . $signatureImagePath);
            
            // 使用 Intervention/Image 在 PDF 第一页上添加签名
            // 注意：这只是一个简化方案，只能在第一页添加签名
            $img = \Intervention\Image\Facades\Image::make($originalFullPath);
            $signature = \Intervention\Image\Facades\Image::make($signatureFullPath);
            
            // 调整签名大小
            $signature->resize(600, null, function ($constraint) {
                $constraint->aspectRatio();
            });
            
            // 获取图片尺寸
            $imgWidth = $img->width();
            $imgHeight = $img->height();
            
            // 计算签名位置（右下角）
            $signX = $imgWidth - 320;
            $signY = $imgHeight - 150;
            
            // 添加签名图片
            $img->insert($signature, 'top-left', $signX, $signY);
            
            // 添加签署文字
            $signText = "签署人：{$employeeName}  签署时间：{$signTime}";
            $img->text($signText, $signX, $signY + 120, function($font) {
                $font->file(storage_path('fonts/simhei.ttf'));
                $font->size(12);
                $font->color('#000000');
            });
            
            // 生成新文件名
            $newFilename = 'contracts/signed_' . basename($originalPdfPath);
            $newFullPath = storage_path('app/public/' . $newFilename);
            
            // 保存为PDF（如果原文件是PDF）或图片
            $img->save($newFullPath);
            
            return $newFilename;
            
        } catch (\Exception $e) {
            \Log::error('备用签名方案失败', ['error' => $e->getMessage()]);
            
            // 如果备用方案也失败，至少保存签名，返回原PDF路径
            \Log::warning('所有签名方案失败，签名单独保存', [
                'original_pdf' => $originalPdfPath,
                'signature' => $signatureImagePath
            ]);
            
            return $originalPdfPath;
        }
    }
    
    /**
     * 使用 Imagick 添加签名到PDF指定位置
     */
    private function addSignatureWithImagick($originalPdfPath, $signatureImagePath, $employeeName, $signTime, $signX, $signY, $pageIndex)
    {
        $originalFullPath = storage_path('app/public/' . $originalPdfPath);
        $signatureFullPath = storage_path('app/public/' . $signatureImagePath);
        
        // 读取PDF
        $imagick = new \Imagick();
        $imagick->setResolution(150, 150);
        $imagick->readImage($originalFullPath);
        
        // 获取页面数量
        $pageCount = $imagick->getNumberImages();
        
        // 跳转到指定页（用户点击的页）
        $imagick->setIteratorIndex($pageIndex);
        
        // 读取签名图片
        $signature = new \Imagick($signatureFullPath);
        $signature->scaleImage(400, 0); // 缩放签名图片，宽度200px，高度自适应
        
        // 使用用户点击的坐标（需要根据预览图片分辨率和PDF分辨率进行换算）
        // 预览图片分辨率150dpi，PDF原始分辨率也是150dpi，所以坐标可以直接使用
        // 但为了安全起见，我们获取实际PDF页面尺寸
        $geometry = $imagick->getImageGeometry();
        $pageWidth = $geometry['width'];
        $pageHeight = $geometry['height'];
        
        \Log::info('签名位置', [
            'user_click_x' => $signX,
            'user_click_y' => $signY,
            'page_width' => $pageWidth,
            'page_height' => $pageHeight,
            'page_index' => $pageIndex
        ]);
        
        // 在PDF上添加签名图片（使用用户点击的坐标）
        $imagick->compositeImage($signature, \Imagick::COMPOSITE_OVER, $signX, $signY);
        
        // 在签名下方添加签署文字
        $draw = new \ImagickDraw();
        if (file_exists(storage_path('fonts/simhei.ttf'))) {
            $draw->setFont(storage_path('fonts/simhei.ttf'));
        }
        $draw->setFontSize(10);
        $draw->setFillColor('#000000');
        
        $signText = "{$employeeName} {$signTime}";
        $imagick->annotateImage($draw, $signX, $signY + 80, 0, $signText);
        
        // 生成新PDF路径
        $newFilename = 'contracts/signed_' . basename($originalPdfPath);
        $newFullPath = storage_path('app/public/' . $newFilename);
        
        // 保存新PDF
        $imagick->setImageFormat('pdf');
        $imagick->writeImages($newFullPath, true);
        
        // 清理资源
        $imagick->clear();
        $imagick->destroy();
        $signature->clear();
        $signature->destroy();
        
        return $newFilename;
    }

    /**
     * 员工拒绝合同
     */
    public function rejectContract(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ], [
            'reason.required' => '请输入拒绝原因',
            'reason.max' => '拒绝原因不能超过500字',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $employee = Employee::find($request->user()->id);

        $contract = EmployeeContract::where('id', $id)
            ->where('employee_id', $employee->id)
            ->first();

        if (!$contract) {
            return response()->json([
                'success' => false,
                'message' => '合同不存在或无权操作'
            ], 404);
        }

        if ($contract->status !== 'pending_sign') {
            return response()->json([
                'success' => false,
                'message' => '该合同当前状态不允许拒绝'
            ], 422);
        }

        $contract->status = 'rejected';
        $contract->employee_reject_reason = $request->reason;
        $contract->save();

        return response()->json([
            'success' => true,
            'message' => '已拒绝该合同'
        ]);
    }

    /**
     * 获取员工信息
     */
    public function getMyInfo(Request $request)
    {
        $employee = Employee::find($request->user()->id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'phone' => $employee->phone,
                'id_number' => substr($employee->id_number, 0, 6) . '********' . substr($employee->id_number, -4),
                'gender' => $employee->gender,
                'hire_date' => $employee->hire_date,
                'contract_status' => $employee->contract_status,
                'last_login_at' => $employee->last_login_at,
                'password_changed_at' => $employee->password_changed_at,
            ]
        ]);
    }

    /**
     * 获取我的资料上传列表（小程序端，支持多文件）
     */
    public function getMyDocuments(Request $request)
    {
        try {
            $employee = Employee::with('projects')->find($request->user()->id);

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => '员工信息不存在'
                ], 404);
            }

            // 获取员工所属项目的资料配置
            $projectIds = $employee->projects->pluck('id')->toArray();

            if (empty($projectIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => '您未分配到任何项目'
                ]);
            }

            // 获取所有相关项目的资料配置
            $configs = \App\Models\ProjectDocumentConfig::whereIn('project_id', $projectIds)
                ->with('project:id,name')
                ->orderBy('sort_order', 'asc')
                ->get();

            // 获取员工已上传的资料（按config_id分组，支持多文件）
            $uploadedDocuments = \App\Models\EmployeeDocument::where('employee_id', $employee->id)
                ->orderBy('uploaded_at', 'desc')
                ->get()
                ->groupBy('document_config_id');

            // 合并配置和上传状态
            $host = $request->getSchemeAndHttpHost();
            $result = $configs->map(function ($config) use ($uploadedDocuments, $employee, $host) {
                $uploadedFiles = $uploadedDocuments->get($config->id, collect());
                $fileCount = $uploadedFiles->count();

                return [
                    'config_id' => $config->id,
                    'project_id' => $config->project_id,
                    'project_name' => $config->project->name ?? null,
                    'document_name' => $config->document_name,
                    'document_type' => $config->document_type,
                    'document_type_text' => $config->document_type_text,
                    'is_required' => $config->is_required,
                    'sort_order' => $config->sort_order,
                    'uploaded' => $fileCount > 0,
                    'file_count' => $fileCount,
                    // 保持向后兼容
                    'upload_info' => $fileCount > 0 ? [
                        'id' => $uploadedFiles->first()->id,
                        'file_url' => $host . '/' . $uploadedFiles->first()->file_path,
                        'original_filename' => $uploadedFiles->first()->original_filename,
                        'file_size' => $uploadedFiles->first()->file_size,
                        'file_size_formatted' => $uploadedFiles->first()->file_size_formatted,
                        'uploaded_at' => $uploadedFiles->first()->uploaded_at,
                        'upload_source' => $uploadedFiles->first()->upload_source,
                    ] : null,
                    // 新增：所有文件列表
                    'files' => $uploadedFiles->map(function ($file) use ($host) {
                        return [
                            'id' => $file->id,
                            'file_url' => $host . '/' . $file->file_path,
                            'original_filename' => $file->original_filename,
                            'file_size' => $file->file_size,
                            'file_size_formatted' => $file->file_size_formatted,
                            'uploaded_at' => $file->uploaded_at,
                            'upload_source' => $file->upload_source,
                        ];
                    })->values()->toArray(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            \Log::error('获取员工资料列表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取资料列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 上传资料（小程序端）
     */
    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_config_id' => 'required|integer|exists:project_document_configs,id',
            'file' => 'required|file|max:10240', // 最大10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = Employee::find($request->user()->id);
            $config = \App\Models\ProjectDocumentConfig::findOrFail($request->document_config_id);

            // 验证文件类型
            $file = $request->file('file');
            $mimeType = $file->getMimeType();
            $extension = strtolower($file->getClientOriginalExtension());

            $allowedMimes = [];
            $allowedExtensions = [];
            
            if ($config->document_type === 'image') {
                // 仅图片
                $allowedMimes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            } elseif ($config->document_type === 'pdf') {
                // 仅PDF
                $allowedMimes = ['application/pdf'];
                $allowedExtensions = ['pdf'];
            } elseif ($config->document_type === 'document') {
                // 文档类型（Word/Excel/PDF）
                $allowedMimes = [
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/octet-stream', // 兼容某些情况
                ];
                $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx'];
            } else {
                // all - 所有类型
                $allowedMimes = [
                    'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
                    'application/pdf',
                    'application/msword',
                    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'application/vnd.ms-excel',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'application/octet-stream',
                ];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
            }

            // 同时检查MIME类型和文件扩展名（更宽松的验证）
            $mimeValid = in_array($mimeType, $allowedMimes);
            $extensionValid = in_array($extension, $allowedExtensions);

            if (!$mimeValid && !$extensionValid) {
                \Log::error('文件类型验证失败', [
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'allowed_mimes' => $allowedMimes,
                    'allowed_extensions' => $allowedExtensions
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => "文件类型不符合要求，支持的格式：" . implode(', ', $allowedExtensions)
                ], 422);
            }

            // 存储文件到public目录
            $publicPath = public_path('employee_documents/' . $employee->id);
            if (!is_dir($publicPath)) {
                mkdir($publicPath, 0755, true);
            }
            
            // 在移动文件前获取文件信息
            $originalFilename = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            $extension = $file->getClientOriginalExtension();
            
            $filename = time() . '_' . uniqid() . '.' . $extension;
            $path = 'employee_documents/' . $employee->id . '/' . $filename;
            $file->move($publicPath, $filename);

            // 创建新的资料记录（支持多文件）
            $document = \App\Models\EmployeeDocument::create([
                'employee_id' => $employee->id,
                'document_config_id' => $config->id,
                'project_id' => $config->project_id,
                'document_name' => $config->document_name,
                'file_path' => $path,
                'original_filename' => $originalFilename,
                'file_size' => $fileSize,
                'file_type' => $mimeType,
                'upload_source' => 'miniapp',
                'uploaded_at' => now(),
            ]);

            // 获取该配置下的文件总数
            $fileCount = \App\Models\EmployeeDocument::where('employee_id', $employee->id)
                ->where('document_config_id', $config->id)
                ->count();

            $host = $request->getSchemeAndHttpHost();

            return response()->json([
                'success' => true,
                'message' => '上传成功',
                'data' => [
                    'id' => $document->id,
                    'file_url' => $host . '/' . $document->file_path,
                    'original_filename' => $document->original_filename,
                    'file_size_formatted' => $document->file_size_formatted,
                    'uploaded_at' => $document->uploaded_at,
                    'file_count' => $fileCount,
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('上传员工资料失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除资料（小程序端）
     */
    public function deleteDocument(Request $request, $documentId)
    {
        try {
            $employee = Employee::find($request->user()->id);

            $document = \App\Models\EmployeeDocument::where('employee_id', $employee->id)
                ->where('id', $documentId)
                ->firstOrFail();

            // 删除文件（文件存储在public目录）
            if ($document->file_path) {
                $filePath = public_path($document->file_path);
                if (file_exists($filePath)) {
                    unlink($filePath);
                    \Log::info('删除文件成功', ['file' => $filePath]);
                }
            }

            // 删除记录
            $document->delete();

            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            \Log::error('删除员工资料失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 上传一寸照片
     */
    public function uploadPhoto(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|image|max:5120', // 最大5MB
        ], [
            'photo.required' => '请上传照片',
            'photo.image' => '请上传图片文件',
            'photo.max' => '照片大小不能超过5MB',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $file = $request->file('photo');
            $employee = Employee::find($request->user()->id);
            
            // 生成文件名
            $filename = $employee->id . '_' . time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            
            // 确保目录存在
            $dir = public_path('uploads/photos');
            if (!file_exists($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // 保存到 public/uploads/photos/
            $file->move($dir, $filename);
            
            // 返回完整URL
            $host = request()->getSchemeAndHttpHost();
            $photoUrl = $host . '/uploads/photos/' . $filename;

            return response()->json([
                'success' => true,
                'data' => [
                    'path' => 'uploads/photos/' . $filename,
                    'url' => $photoUrl
                ],
                'message' => '照片上传成功'
            ]);
        } catch (\Exception $e) {
            \Log::error('上传照片失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 上传签名图片
     */
    public function uploadSignature(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'signature' => 'required|string', // base64签名图片
        ], [
            'signature.required' => '请提供签名图片',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // 保存签名图片
            $signaturePath = $this->saveSignatureImage($request->signature);
            
            // 返回完整URL（直接从public目录访问）
            $signatureUrl = asset($signaturePath);

            return response()->json([
                'success' => true,
                'data' => [
                    'path' => $signaturePath,  // 相对路径
                    'url' => $signatureUrl     // 完整URL
                ],
                'message' => '签名上传成功'
            ]);
        } catch (\Exception $e) {
            \Log::error('上传签名失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取我的入职登记表
     */
    public function getMyOnboardingForm(Request $request)
    {
        try {
            $employee = Employee::find($request->user()->id);

            $form = OnboardingForm::where('employee_id', $employee->id)->first();

            if ($form) {
                $formData = $form->toArray();
                $host = request()->getSchemeAndHttpHost();
                
                // 将签名路径转换为完整URL
                if (!empty($formData['signature'])) {
                    if (strpos($formData['signature'], 'http') === 0) {
                        // 已经是完整URL，不处理
                    } elseif (strpos($formData['signature'], 'uploads/') === 0) {
                        $formData['signature'] = $host . '/' . $formData['signature'];
                    } else {
                        $formData['signature'] = $host . '/storage/' . $formData['signature'];
                    }
                }
                
                // 将寸照路径转换为完整URL
                if (!empty($formData['photo'])) {
                    if (strpos($formData['photo'], 'http') === 0) {
                        // 已经是完整URL，不处理
                    } elseif (strpos($formData['photo'], 'uploads/') === 0) {
                        $formData['photo'] = $host . '/' . $formData['photo'];
                    } else {
                        $formData['photo'] = $host . '/storage/' . $formData['photo'];
                    }
                }
                
                $formData['contact_province'] = $employee->contact_province ?? ($formData['contact_province'] ?? '');
                $formData['contact_city'] = $employee->contact_city ?? ($formData['contact_city'] ?? '');
                $formData['contact_district'] = $employee->contact_district ?? ($formData['contact_district'] ?? '');
                $formData['contact_address_detail'] = $employee->residence_address ?: ($formData['contact_address_detail'] ?? ($employee->contact_address ?: ($formData['contact_address'] ?? '')));
                $formData['place_of_origin_province'] = $employee->household_province ?? ($formData['place_of_origin_province'] ?? '');
                $formData['place_of_origin_city'] = $employee->household_city ?? ($formData['place_of_origin_city'] ?? '');
                $formData['place_of_origin_district'] = $employee->household_district ?? ($formData['place_of_origin_district'] ?? '');
                $formData['place_of_origin_detail'] = $employee->household_address ?: ($formData['place_of_origin_detail'] ?? '');

                if (empty($formData['contact_address'])) {
                    $contactAddressParts = array_filter([
                        $formData['contact_province'] ?? '',
                        $formData['contact_city'] ?? '',
                        $formData['contact_district'] ?? '',
                        $formData['contact_address_detail'] ?? '',
                    ], fn($item) => $item !== null && $item !== '');
                    $formData['contact_address'] = implode(' ', $contactAddressParts);
                }

                if (empty($formData['place_of_origin'])) {
                    $placeOfOriginParts = array_filter([
                        $formData['place_of_origin_province'] ?? '',
                        $formData['place_of_origin_city'] ?? '',
                        $formData['place_of_origin_district'] ?? '',
                        $formData['place_of_origin_detail'] ?? '',
                    ], fn($item) => $item !== null && $item !== '');
                    $formData['place_of_origin'] = implode('', $placeOfOriginParts);
                }

                $formData['education_type'] = $this->normalizeEducationTypeValue($formData['education_type'] ?? '');

                return response()->json([
                    'success' => true,
                    'data' => $formData
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $form
            ]);
        } catch (\Exception $e) {
            \Log::error('获取入职登记表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交入职登记表
     */
    public function submitOnboardingForm(Request $request)
    {
        $rawOnboardingEducationType = $request->input('education_type', $request->input('educationType', $request->input('education_nature', $request->input('education_property', ''))));
        $normalizedOnboardingEducationType = $this->normalizeEducationTypeValue($rawOnboardingEducationType);
        $existingOnboardingForm = OnboardingForm::where('employee_id', optional($request->user())->id)->first();
        if ($normalizedOnboardingEducationType === '' && $existingOnboardingForm && !empty($existingOnboardingForm->education_type)) {
            $normalizedOnboardingEducationType = $this->normalizeEducationTypeValue($existingOnboardingForm->education_type);
        }
        $request->merge([
            'education_type' => $normalizedOnboardingEducationType,
        ]);

        $validator = Validator::make($request->all(), [
            'registration_date' => 'required|date',
            'name' => 'required|string|max:50',
            'gender' => 'required|in:male,female',
            'ethnicity' => 'nullable|string|max:50',
            'political_status' => 'nullable|string|max:50',
            'place_of_origin' => 'nullable|string|max:100',
            'place_of_origin_province' => 'nullable|string|max:50',
            'place_of_origin_city' => 'nullable|string|max:50',
            'place_of_origin_district' => 'nullable|string|max:50',
            'place_of_origin_detail' => 'nullable|string|max:200',
            'place_of_origin_region' => 'nullable',
            'birth_date' => 'nullable|date',
            'graduated_school' => 'nullable|string|max:200',
            'graduation_date' => 'nullable|date',
            'education_level' => 'nullable|string|max:50',
            'education_type' => 'required|in:统招,非统招',
            'major' => 'nullable|string|max:100',
            'degree' => 'nullable|string|max:50',
            'technical_title' => 'nullable|string|max:50',
            'health_status' => 'nullable|string|max:50',
            'height' => 'nullable|integer|min:1|max:300',
            'weight' => 'nullable|numeric|min:1|max:500',
            'marital_status' => 'nullable|string|max:20',
            'id_number' => 'required|string|size:18',
            'current_residence' => 'nullable|string|max:200',
            'household_registration' => 'nullable|string|max:200',
            'position' => 'nullable|string|max:100',
            'desired_location' => 'nullable|string|max:100',
            'accept_assignment' => 'nullable|boolean',
            'contact_address' => 'nullable|string|max:200',
            'contact_address_region' => 'nullable',
            'contact_province' => 'nullable|string|max:50',
            'contact_city' => 'nullable|string|max:50',
            'contact_district' => 'nullable|string|max:50',
            'contact_address_detail' => 'nullable|string|max:200',
            'contact_phone' => 'nullable|string|max:20',
            'remarks' => 'nullable|string',
            'education_background' => 'nullable|array',
            'education_background.*.start_date' => 'nullable|string',
            'education_background.*.end_date' => 'nullable|string',
            'education_background.*.school' => 'nullable|string',
            'education_background.*.level' => 'nullable|string',
            'education_background.*.certifier' => 'nullable|string',
            'work_experience' => 'nullable|array',
            'work_experience.*.start_date' => 'nullable|string',
            'work_experience.*.end_date' => 'nullable|string',
            'work_experience.*.employer' => 'nullable|string',
            'work_experience.*.job_content' => 'nullable|string',
            'work_experience.*.certifier' => 'nullable|string',
            'family_info' => 'nullable|array',
            'family_info.*.name' => 'nullable|string',
            'family_info.*.relationship' => 'nullable|string',
            'family_info.*.employer' => 'nullable|string',
            'family_info.*.phone' => 'nullable|string',
            'signature' => 'required|string', // 签名图片路径（通过uploadSignature接口上传后返回）
            'photo' => 'nullable|string',     // 一寸照片路径（通过uploadPhoto接口上传后返回）
        ], [
            'registration_date.required' => '请输入登记日期',
            'name.required' => '请输入姓名',
            'gender.required' => '请选择性别',
            'id_number.required' => '请输入身份证号码',
            'id_number.size' => '身份证号码必须为18位',
            'signature.required' => '请先完成手写签名',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = Employee::find($request->user()->id);

            $contactProvince = trim((string) $request->input('contact_province', ''));
            $contactCity = trim((string) $request->input('contact_city', ''));
            $contactDistrict = trim((string) $request->input('contact_district', ''));
            $contactAddressDetail = trim((string) $request->input('contact_address_detail', ''));
            $contactAddress = trim((string) $request->input('contact_address', ''));
            $contactAddressRegion = $request->input('contact_address_region', []);
            $contactRegionValues = [];
            $placeOfOriginProvince = trim((string) $request->input('place_of_origin_province', ''));
            $placeOfOriginCity = trim((string) $request->input('place_of_origin_city', ''));
            $placeOfOriginDistrict = trim((string) $request->input('place_of_origin_district', ''));
            $placeOfOriginDetail = trim((string) $request->input('place_of_origin_detail', ''));
            $placeOfOrigin = trim((string) $request->input('place_of_origin', ''));
            $placeOfOriginRegion = $request->input('place_of_origin_region', []);
            $placeOfOriginRegionValues = [];

            if (is_string($contactAddressRegion)) {
                $decodedRegion = json_decode($contactAddressRegion, true);
                $contactRegionValues = is_array($decodedRegion)
                    ? array_values($decodedRegion)
                    : preg_split('/[\s,]+/', str_replace("\xEF\xBC\x8C", ',', $contactAddressRegion), -1, PREG_SPLIT_NO_EMPTY);
            } elseif (is_array($contactAddressRegion)) {
                $contactRegionValues = array_values($contactAddressRegion);
            }

            $contactRegionValues = array_values(array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string) $item) : '';
            }, $contactRegionValues), fn($item) => $item !== ''));

            if (is_string($placeOfOriginRegion)) {
                $decodedPlaceRegion = json_decode($placeOfOriginRegion, true);
                $placeOfOriginRegionValues = is_array($decodedPlaceRegion)
                    ? array_values($decodedPlaceRegion)
                    : preg_split('/[\s,]+/', str_replace("\xEF\xBC\x8C", ',', $placeOfOriginRegion), -1, PREG_SPLIT_NO_EMPTY);
            } elseif (is_array($placeOfOriginRegion)) {
                $placeOfOriginRegionValues = array_values($placeOfOriginRegion);
            }

            $placeOfOriginRegionValues = array_values(array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string) $item) : '';
            }, $placeOfOriginRegionValues), fn($item) => $item !== ''));

            if ($contactProvince === '' && isset($contactRegionValues[0])) {
                $contactProvince = $contactRegionValues[0];
            }

            if ($contactCity === '' && isset($contactRegionValues[1])) {
                $contactCity = $contactRegionValues[1];
            }

            if ($contactDistrict === '' && isset($contactRegionValues[2])) {
                $contactDistrict = $contactRegionValues[2];
            }

            if ($placeOfOriginProvince === '' && isset($placeOfOriginRegionValues[0])) {
                $placeOfOriginProvince = $placeOfOriginRegionValues[0];
            }

            if ($placeOfOriginCity === '' && isset($placeOfOriginRegionValues[1])) {
                $placeOfOriginCity = $placeOfOriginRegionValues[1];
            }

            if ($placeOfOriginDistrict === '' && isset($placeOfOriginRegionValues[2])) {
                $placeOfOriginDistrict = $placeOfOriginRegionValues[2];
            }

            if ($contactAddress === '') {
                $contactAddressParts = array_filter([
                    $contactProvince,
                    $contactCity,
                    $contactDistrict,
                    $contactAddressDetail,
                ], fn($item) => $item !== '');
                $contactAddress = implode(' ', $contactAddressParts);
            }

            if ($contactAddressDetail === '') {
                $contactAddressDetail = $contactAddress;
            }

            if ($placeOfOrigin === '') {
                $placeOfOriginParts = array_filter([
                    $placeOfOriginProvince,
                    $placeOfOriginCity,
                    $placeOfOriginDistrict,
                    $placeOfOriginDetail,
                ], fn($item) => $item !== '');
                $placeOfOrigin = implode('', $placeOfOriginParts);
            }

            $hasRegionPayload = $contactProvince !== '' || $contactCity !== '' || $contactDistrict !== '';
            $hasAddressPayload = $request->has('contact_address_detail') || $request->has('contact_address');
            $hasPlaceOfOriginRegionPayload = $placeOfOriginProvince !== '' || $placeOfOriginCity !== '' || $placeOfOriginDistrict !== '';
            $hasPlaceOfOriginDetailPayload = $placeOfOriginDetail !== '' || $request->has('place_of_origin_detail');
            $hasPlaceOfOriginPayload = $placeOfOrigin !== '' || $hasPlaceOfOriginRegionPayload || $hasPlaceOfOriginDetailPayload;

            // 签名已经通过uploadSignature接口上传，这里直接使用路径
            // signature字段现在应该是相对路径，不是base64

            // 创建或更新入职登记表
            $form = OnboardingForm::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'account_set_id' => $employee->account_set_id,
                    'registration_date' => $request->registration_date,
                    'name' => $request->name,
                    'gender' => $request->gender,
                    'ethnicity' => $request->ethnicity,
                    'political_status' => $request->political_status,
                    'place_of_origin' => $hasPlaceOfOriginPayload ? $placeOfOrigin : $request->place_of_origin,
                    'birth_date' => $request->birth_date,
                    'graduated_school' => $request->graduated_school,
                    'graduation_date' => $request->graduation_date,
                    'education_level' => $request->education_level,
                    'education_type' => $request->education_type,
                    'major' => $request->major,
                    'degree' => $request->degree,
                    'technical_title' => $request->technical_title,
                    'health_status' => $request->health_status,
                    'height' => $request->height,
                    'weight' => $request->weight,
                    'marital_status' => $request->marital_status,
                    'id_number' => $request->id_number,
                    'current_residence' => $request->current_residence,
                    'household_registration' => $hasPlaceOfOriginPayload ? $placeOfOrigin : $request->household_registration,
                    'position' => $request->position,
                    'desired_location' => $request->desired_location,
                    'accept_assignment' => $request->accept_assignment,
                    'contact_address' => $contactAddress,
                    'contact_phone' => $request->contact_phone,
                    'remarks' => $request->remarks,
                    'signature' => $request->signature, // 保存签名图片路径（已经通过uploadSignature上传）
                    'photo' => $request->photo,         // 保存一寸照片路径（已经通过uploadPhoto上传）
                    'education_background' => $request->education_background,
                    'work_experience' => $request->work_experience,
                    'family_info' => $request->family_info,
                ]
            );

            // 每次提交都按映射覆盖员工信息（仅覆盖 employees 表已有字段）
            $employeeUpdateData = [
                'name' => $request->name,
                'gender' => $request->gender,
                'id_number' => $this->resolveEmployeeIdNumber($employee, $request->id_number),
                'birth_date' => $request->birth_date,
                'marital_status' => $request->marital_status,
                'position' => $request->position,
                'education' => $request->education_level,
                'education_type' => $request->education_type,
                'phone' => $request->contact_phone,
                'address' => $request->current_residence,
                'household_registration' => $hasPlaceOfOriginPayload ? $placeOfOrigin : $request->household_registration,
                'contact_address' => ($hasAddressPayload || $hasRegionPayload) ? $contactAddress : $employee->contact_address,
                'remarks' => $request->remarks,
                'native_place' => $hasPlaceOfOriginPayload ? $placeOfOrigin : $request->place_of_origin,
                'ethnicity' => $request->ethnicity,
                'political_status' => $request->political_status,
                'health_status' => $request->health_status,
                'height' => $request->height,
                'weight' => $request->weight,
                'graduation_school' => $request->graduated_school,
                'graduation_date' => $request->graduation_date,
                'major' => $request->major,
                'degree' => $request->degree,
                'job_title' => $request->technical_title,
            ];

            if ($hasRegionPayload) {
                $employeeUpdateData = array_merge($employeeUpdateData, [
                    'contact_province' => $contactProvince,
                    'contact_city' => $contactCity,
                    'contact_district' => $contactDistrict,
                    'residence_province' => $contactProvince,
                    'residence_city' => $contactCity,
                    'residence_district' => $contactDistrict,
                ]);
            }

            if ($hasAddressPayload) {
                $employeeUpdateData['residence_address'] = $contactAddressDetail;
            }

            if ($hasPlaceOfOriginRegionPayload) {
                $employeeUpdateData = array_merge($employeeUpdateData, [
                    'household_province' => $placeOfOriginProvince,
                    'household_city' => $placeOfOriginCity,
                    'household_district' => $placeOfOriginDistrict,
                ]);
            }

            if ($hasPlaceOfOriginDetailPayload) {
                $employeeUpdateData['household_address'] = $placeOfOriginDetail;
            }

            $employeeUpdateData = $this->filterEmployeeUpdateData($employeeUpdateData);

            if (!empty($employeeUpdateData)) {
                $employee->update($employeeUpdateData);
                \Log::info('入职登记表数据已覆盖同步到员工信息', [
                    'employee_id' => $employee->id,
                    'updated_fields' => array_keys($employeeUpdateData),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => '提交成功',
                'data' => $form
            ]);
        } catch (\Exception $e) {
            \Log::error('提交入职登记表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '提交失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取我的从业人员登记表
     */
    public function getMyRegistrationForm(Request $request)
    {
        try {
            $employee = Employee::find($request->user()->id);
            $form = \App\Models\EmployeeRegistrationForm::where('employee_id', $employee->id)->first();

            if ($form) {
                $formData = $form->toArray();

                \Log::info('Registration form load education_type debug', [
                    'db_education_type' => $formData['education_type'] ?? 'null',
                ]);

                if (!empty($formData['signature'])) {
                    // 兼容新旧路径格式
                    if (strpos($formData['signature'], 'uploads/') === 0) {
                        $formData['signature'] = asset($formData['signature']);
                    } else {
                        $formData['signature'] = asset('storage/' . $formData['signature']);
                    }
                }

                $formData['native_place_province'] = $employee->household_province ?? ($formData['native_place_province'] ?? '');
                $formData['native_place_city'] = $employee->household_city ?? ($formData['native_place_city'] ?? '');
                $formData['native_place_district'] = $employee->household_district ?? ($formData['native_place_district'] ?? '');
                $formData['native_place_detail'] = $employee->household_address ?? ($formData['native_place_detail'] ?? '');
                $formData['education_type'] = $this->normalizeEducationTypeValue($formData['education_type'] ?? '');

                if (empty($formData['native_place'])) {
                    $nativePlaceParts = array_filter([
                        $formData['native_place_province'] ?? '',
                        $formData['native_place_city'] ?? '',
                        $formData['native_place_district'] ?? '',
                        $formData['native_place_detail'] ?? '',
                    ], fn($item) => $item !== null && $item !== '');
                    $formData['native_place'] = implode('', $nativePlaceParts);
                }

                $form = $formData;
            }

            return response()->json([
                'success' => true,
                'data' => $form
            ]);
        } catch (\Exception $e) {
            \Log::error('获取从业人员登记表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 提交从业人员登记表
     */
    public function submitRegistrationForm(Request $request)
    {
        $rawRegistrationEducationType = $request->input('education_type', $request->input('educationType', $request->input('education_nature', $request->input('education_property', ''))));
        $normalizedRegistrationEducationType = $this->normalizeEducationTypeValue($rawRegistrationEducationType);
        $existingRegistrationForm = \App\Models\EmployeeRegistrationForm::where('employee_id', optional($request->user())->id)->first();
        if ($normalizedRegistrationEducationType === '' && $existingRegistrationForm && !empty($existingRegistrationForm->education_type)) {
            $normalizedRegistrationEducationType = $this->normalizeEducationTypeValue($existingRegistrationForm->education_type);
        }
        $request->merge([
            'education_type' => $normalizedRegistrationEducationType,
        ]);

        $validator = Validator::make($request->all(), [
            'fill_date' => 'required|date',
            'entry_position' => 'required|string|max:100',
            'entry_date' => 'required|date',
            'department' => 'required|string|max:100',
            'job_title' => 'required|string|max:100',
            'housing_fund_account' => 'required|string|max:50',
            'bank_account' => 'required|string|max:50',
            'bank_name' => 'required|string|max:100',
            'name' => 'required|string|max:50',
            'english_name' => 'required|string|max:50',
            'gender' => 'required|in:male,female',
            'height' => 'required|string|max:20',
            'birth_date' => 'required|date',
            'political_status' => 'required|string|max:50',
            'education_level' => 'required|string|max:50',
            'education_type' => 'required|in:统招,非统招',
            'native_place' => 'required|string|max:100',
            'native_place_province' => 'nullable|string|max:50',
            'native_place_city' => 'nullable|string|max:50',
            'native_place_district' => 'nullable|string|max:50',
            'native_place_detail' => 'nullable|string|max:200',
            'native_place_region' => 'nullable',
            'marital_status' => 'required|string|max:20',
            'has_children' => 'required|string|max:20',
            'id_number' => 'required|string|size:18',
            'household_type' => 'required|string|max:20',
            'current_address' => 'required|string|max:200',
            'postal_code' => 'required|string|max:10',
            'household_address' => 'required|string|max:200',
            'contact_phone' => 'required|string|max:20',
            'document_address' => 'required|string|max:200',
            'disability_level' => 'required|string|max:20',
            'language_skills' => 'required|array|min:1',
            'language_skills.*' => 'required|string|max:50',
            'engineering_skills' => 'required|array|min:1',
            'engineering_skills.*' => 'required|string|max:50',
            'professional_title' => 'required|string|max:50',
            'hobbies' => 'required|array|min:1',
            'hobbies.*' => 'required|string|max:50',
            'other_skills' => 'nullable|string',
            'education_history' => 'required|array|min:1',
            'education_history.*.date_range' => 'required|string|max:50',
            'education_history.*.school_major' => 'required|string|max:200',
            'education_history.*.certificate' => 'required|string|max:100',
            'work_history' => 'required|array|min:1',
            'work_history.*.date_range' => 'required|string|max:50',
            'work_history.*.company' => 'required|string|max:200',
            'work_history.*.position' => 'required|string|max:100',
            'work_history.*.salary' => 'required|string|max:50',
            'work_history.*.leave_reason' => 'required|string|max:200',
            'reference_company' => 'required|string|max:100',
            'reference_contact' => 'required|string|max:100',
            'rewards_punishments' => 'required|string',
            'family_members' => 'required|array|min:1',
            'family_members.*.name' => 'required|string|max:50',
            'family_members.*.relation' => 'required|string|max:20',
            'family_members.*.age' => 'required|string|max:10',
            'family_members.*.employer' => 'required|string|max:100',
            'family_members.*.phone' => 'required|string|max:20',
            'emergency_contact1_name' => 'required|string|max:50',
            'emergency_contact1_relation' => 'required|string|max:20',
            'emergency_contact1_phone' => 'required|string|max:20',
            'emergency_contact2_name' => 'required|string|max:50',
            'emergency_contact2_relation' => 'required|string|max:20',
            'emergency_contact2_phone' => 'required|string|max:20',
            'mental_illness' => 'required|string|max:10',
            'mental_illness_detail' => 'nullable|string|required_if:mental_illness,有',
            'other_illness' => 'required|string|max:10',
            'other_illness_detail' => 'nullable|string|required_if:other_illness,有',
            'hospitalized_recently' => 'required|string|max:10',
            'hospitalized_reason' => 'nullable|string|required_if:hospitalized_recently,有',
            'criminal_record' => 'required|string|max:10',
            'criminal_record_time' => 'nullable|string|max:50|required_if:criminal_record,有',
            'employment_documents' => 'required|array|min:1',
            'employment_documents.*' => 'required|string|max:100',
            'remarks' => 'required|string',
            'is_pregnant' => 'required|string|max:10',
            'pregnant_detail' => 'nullable|string|required_if:is_pregnant,有',
            'accept_overtime' => 'required|string|max:10',
            'need_accommodation' => 'required|string|max:10',
            'accommodation_detail' => 'nullable|string|required_if:need_accommodation,有',
            'has_driving_license' => 'required|string|max:10',
            'driving_license_detail' => 'nullable|string|required_if:has_driving_license,有',
            'signature' => 'required|string',
            'signature_date' => 'required|date',
        ], [
            'required' => '请填写:attribute',
            'required_if' => '请填写:attribute',
            'array' => ':attribute格式不正确',
            'min' => ':attribute不能为空',
            'date' => ':attribute格式不正确',
            'size' => ':attribute格式不正确',
            'in' => ':attribute取值无效',
            'max' => ':attribute超出长度限制',
        ], [
            'fill_date' => '填表日期',
            'entry_position' => '入职职位',
            'entry_date' => '入职日期',
            'department' => '部门',
            'job_title' => '职务',
            'housing_fund_account' => '公积金账户',
            'bank_account' => '银行账号',
            'bank_name' => '开户支行名称',
            'name' => '姓名',
            'english_name' => '英文名',
            'gender' => '性别',
            'height' => '身高',
            'birth_date' => '出生日期',
            'political_status' => '政治面貌',
            'education_level' => '文化程度',
            'education_type' => '学历性质',
            'native_place' => '籍贯',
            'marital_status' => '婚姻状况',
            'has_children' => '是否有子女',
            'id_number' => '身份证号码',
            'household_type' => '户口状态',
            'current_address' => '现居住地址',
            'postal_code' => '邮编',
            'household_address' => '户口地址',
            'contact_phone' => '联系电话',
            'document_address' => '文书送达地址',
            'disability_level' => '残疾证等级',
            'language_skills' => '英语水平',
            'engineering_skills' => '工程证书',
            'professional_title' => '职称',
            'hobbies' => '兴趣爱好',
            'other_skills' => '其他技能',
            'education_history' => '教育情况',
            'education_history.*.date_range' => '教育情况-起止时间',
            'education_history.*.school_major' => '教育情况-学校及专业',
            'education_history.*.certificate' => '教育情况-所获证书',
            'work_history' => '工作履历',
            'work_history.*.date_range' => '工作履历-起止时间',
            'work_history.*.company' => '工作履历-公司',
            'work_history.*.position' => '工作履历-职位',
            'work_history.*.salary' => '工作履历-薪资',
            'work_history.*.leave_reason' => '工作履历-离职原因',
            'reference_company' => '前单位名称',
            'reference_contact' => '联系职位/电话',
            'rewards_punishments' => '奖惩情况',
            'family_members' => '家庭成员信息',
            'family_members.*.name' => '家庭成员-姓名',
            'family_members.*.relation' => '家庭成员-关系',
            'family_members.*.age' => '家庭成员-年龄',
            'family_members.*.employer' => '家庭成员-工作单位',
            'family_members.*.phone' => '家庭成员-电话',
            'emergency_contact1_name' => '第一紧急联系人姓名',
            'emergency_contact1_relation' => '第一紧急联系人关系',
            'emergency_contact1_phone' => '第一紧急联系人电话',
            'emergency_contact2_name' => '第二紧急联系人姓名',
            'emergency_contact2_relation' => '第二紧急联系人关系',
            'emergency_contact2_phone' => '第二紧急联系人电话',
            'mental_illness' => '精神病史',
            'mental_illness_detail' => '精神病史详情',
            'other_illness' => '其他疾病',
            'other_illness_detail' => '其他疾病详情',
            'hospitalized_recently' => '近三个月住院记录',
            'hospitalized_reason' => '住院病因',
            'criminal_record' => '违法犯罪记录',
            'criminal_record_time' => '违法犯罪时间',
            'employment_documents' => '就业证件',
            'remarks' => '其他说明',
            'is_pregnant' => '是否怀孕',
            'pregnant_detail' => '怀孕详情',
            'accept_overtime' => '是否接受加班出差',
            'need_accommodation' => '是否需要住宿',
            'accommodation_detail' => '住宿详情',
            'has_driving_license' => '是否有驾照',
            'driving_license_detail' => '驾照详情',
            'signature' => '手写签名',
            'signature_date' => '签名日期',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $employee = Employee::find($request->user()->id);
            $educationType = $this->normalizeEducationTypeValue($request->input('education_type'));

            \Log::info('Registration form education_type debug', [
                'input_education_type' => $request->input('education_type'),
                'normalized_education_type' => $educationType,
            ]);
            $nativePlaceProvince = trim((string) $request->input('native_place_province', ''));
            $nativePlaceCity = trim((string) $request->input('native_place_city', ''));
            $nativePlaceDistrict = trim((string) $request->input('native_place_district', ''));
            $nativePlaceDetail = trim((string) $request->input('native_place_detail', ''));
            $nativePlace = trim((string) $request->input('native_place', ''));
            $nativePlaceRegion = $request->input('native_place_region', []);
            $nativePlaceRegionValues = [];

            if (is_string($nativePlaceRegion)) {
                $decodedNativePlaceRegion = json_decode($nativePlaceRegion, true);
                $nativePlaceRegionValues = is_array($decodedNativePlaceRegion)
                    ? array_values($decodedNativePlaceRegion)
                    : preg_split('/[\s,]+/', str_replace("\xEF\xBC\x8C", ',', $nativePlaceRegion), -1, PREG_SPLIT_NO_EMPTY);
            } elseif (is_array($nativePlaceRegion)) {
                $nativePlaceRegionValues = array_values($nativePlaceRegion);
            }

            $nativePlaceRegionValues = array_values(array_filter(array_map(function ($item) {
                return is_scalar($item) ? trim((string) $item) : '';
            }, $nativePlaceRegionValues), fn($item) => $item !== ''));

            if ($nativePlaceProvince === '' && isset($nativePlaceRegionValues[0])) {
                $nativePlaceProvince = $nativePlaceRegionValues[0];
            }

            if ($nativePlaceCity === '' && isset($nativePlaceRegionValues[1])) {
                $nativePlaceCity = $nativePlaceRegionValues[1];
            }

            if ($nativePlaceDistrict === '' && isset($nativePlaceRegionValues[2])) {
                $nativePlaceDistrict = $nativePlaceRegionValues[2];
            }

            $nativePlaceParts = array_filter([
                $nativePlaceProvince,
                $nativePlaceCity,
                $nativePlaceDistrict,
                $nativePlaceDetail,
            ], fn($item) => $item !== '');

            // If region/detail exists, always rebuild native_place from these pieces.
            if (!empty($nativePlaceParts)) {
                $nativePlace = implode('', $nativePlaceParts);
            }

            if ($nativePlaceDetail === '' && $nativePlace !== '') {
                $nativePlaceDetail = $nativePlace;
            }

            $hasNativePlaceRegionPayload = $nativePlaceProvince !== '' || $nativePlaceCity !== '' || $nativePlaceDistrict !== '';
            $hasNativePlaceDetailPayload = $nativePlaceDetail !== '';
            $hasNativePlacePayload = $nativePlace !== '' || $hasNativePlaceRegionPayload || $hasNativePlaceDetailPayload;

            $form = \App\Models\EmployeeRegistrationForm::updateOrCreate(
                ['employee_id' => $employee->id],
                [
                    'account_set_id' => $employee->account_set_id,
                    'fill_date' => $request->fill_date,
                    'entry_position' => $request->entry_position,
                    'entry_date' => $request->entry_date,
                    'department' => $request->department,
                    'job_title' => $request->job_title,
                    'housing_fund_account' => $request->housing_fund_account,
                    'bank_account' => $request->bank_account,
                    'bank_name' => $request->bank_name,
                    'name' => $request->name,
                    'english_name' => $request->english_name,
                    'gender' => $request->gender,
                    'height' => $request->height,
                    'birth_date' => $request->birth_date,
                    'political_status' => $request->political_status,
                    'education_level' => $request->education_level,
                    'education_type' => $educationType,
                    'native_place' => $hasNativePlacePayload ? $nativePlace : $request->native_place,
                    'marital_status' => $request->marital_status,
                    'has_children' => $request->has_children,
                    'id_number' => $request->id_number,
                    'household_type' => $request->household_type,
                    'current_address' => $request->current_address,
                    'postal_code' => $request->postal_code,
                    'household_address' => $request->household_address,
                    'contact_phone' => $request->contact_phone,
                    'document_address' => $request->document_address,
                    'disability_level' => $request->disability_level,
                    'language_skills' => $request->language_skills,
                    'engineering_skills' => $request->engineering_skills,
                    'professional_title' => $request->professional_title,
                    'hobbies' => $request->hobbies,
                    'other_skills' => $request->other_skills,
                    'education_history' => $request->education_history,
                    'work_history' => $request->work_history,
                    'reference_company' => $request->reference_company,
                    'reference_contact' => $request->reference_contact,
                    'rewards_punishments' => $request->rewards_punishments,
                    'family_members' => $request->family_members,
                    'emergency_contact1_name' => $request->emergency_contact1_name,
                    'emergency_contact1_relation' => $request->emergency_contact1_relation,
                    'emergency_contact1_phone' => $request->emergency_contact1_phone,
                    'emergency_contact2_name' => $request->emergency_contact2_name,
                    'emergency_contact2_relation' => $request->emergency_contact2_relation,
                    'emergency_contact2_phone' => $request->emergency_contact2_phone,
                    'mental_illness' => $request->mental_illness,
                    'mental_illness_detail' => $request->mental_illness_detail,
                    'other_illness' => $request->other_illness,
                    'other_illness_detail' => $request->other_illness_detail,
                    'hospitalized_recently' => $request->hospitalized_recently,
                    'hospitalized_reason' => $request->hospitalized_reason,
                    'criminal_record' => $request->criminal_record,
                    'criminal_record_time' => $request->criminal_record_time,
                    'employment_documents' => $request->employment_documents,
                    'remarks' => $request->remarks,
                    'is_pregnant' => $request->is_pregnant,
                    'pregnant_detail' => $request->pregnant_detail,
                    'accept_overtime' => $request->accept_overtime,
                    'need_accommodation' => $request->need_accommodation,
                    'accommodation_detail' => $request->accommodation_detail,
                    'has_driving_license' => $request->has_driving_license,
                    'driving_license_detail' => $request->driving_license_detail,
                    'signature' => $request->signature,
                    'signature_date' => $request->signature_date,
                ]
            );

            // 每次提交都按映射覆盖员工信息（仅覆盖 employees 表已有字段）
            $normalizedHouseholdType = match ($request->household_type) {
                'urban' => 'non_agricultural',
                'rural' => 'agricultural',
                'non_agricultural', 'agricultural' => $request->household_type,
                default => null,
            };

            $normalizedEntryDate = trim((string) $request->input('entry_date', ''));

            $employeeUpdateData = [
                'name' => $request->name,
                'gender' => $request->gender,
                'id_number' => $this->resolveEmployeeIdNumber($employee, $request->id_number),
                'birth_date' => $request->birth_date,
                'marital_status' => $request->marital_status,
                'position' => $request->entry_position,
                'job_title' => $request->job_title,
                'hire_date' => $normalizedEntryDate !== '' ? $normalizedEntryDate : null,
                'education' => $request->education_level,
                'education_type' => $educationType,
                'phone' => $request->contact_phone,
                'address' => $request->current_address,
                'household_address' => $hasNativePlaceDetailPayload ? $nativePlaceDetail : $request->household_address,
                'household_type' => $normalizedHouseholdType,
                'contact_address' => $hasNativePlaceDetailPayload ? $nativePlaceDetail : $request->document_address,
                'bank_account' => $request->bank_account,
                'bank_branch' => $request->bank_name,
                'emergency_contact' => $request->emergency_contact1_name,
                'emergency_phone' => $request->emergency_contact1_phone,
                'remarks' => $request->remarks,
                'native_place' => $hasNativePlacePayload ? $nativePlace : $request->native_place,
                'household_registration' => $hasNativePlacePayload ? $nativePlace : $request->household_address,
                'political_status' => $request->political_status,
                'height' => $request->height,
            ];

            if ($hasNativePlaceRegionPayload) {
                $employeeUpdateData = array_merge($employeeUpdateData, [
                    'household_province' => $nativePlaceProvince,
                    'household_city' => $nativePlaceCity,
                    'household_district' => $nativePlaceDistrict,
                    'residence_province' => $nativePlaceProvince,
                    'residence_city' => $nativePlaceCity,
                    'residence_district' => $nativePlaceDistrict,
                    'contact_province' => $nativePlaceProvince,
                    'contact_city' => $nativePlaceCity,
                    'contact_district' => $nativePlaceDistrict,
                ]);
            }

            if ($hasNativePlaceDetailPayload) {
                $employeeUpdateData = array_merge($employeeUpdateData, [
                    'household_address' => $nativePlaceDetail,
                    'residence_address' => $nativePlaceDetail,
                    'contact_address' => $nativePlaceDetail,
                ]);
            }

            // The mini-program registration form allows these fields to be omitted,
            // so avoid overwriting required employee columns with null/empty values.
            if ($normalizedEntryDate === '') {
                unset($employeeUpdateData['hire_date']);
            }

            if ($educationType === '') {
                unset($employeeUpdateData['education_type']);
            }

            $employeeUpdateData = $this->filterEmployeeUpdateData($employeeUpdateData);

            if (!empty($employeeUpdateData)) {
                $employee->update($employeeUpdateData);
                \Log::info('从业人员登记表数据已覆盖同步到员工信息', [
                    'employee_id' => $employee->id,
                    'updated_fields' => array_keys($employeeUpdateData),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => '提交成功',
                'data' => $form
            ]);
        } catch (\Exception $e) {
            \Log::error('提交从业人员登记表失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '提交失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取我的离职证明列表
     */
    public function getMyResignationCertificates(Request $request)
    {
        $employee = Employee::find($request->user()->id);
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '员工不存在'
            ], 404);
        }
        
        // 检查员工是否为离职或退休状态
        if (!in_array($employee->contract_status, ['terminated', 'retired'])) {
            return response()->json([
                'success' => false,
                'message' => '只有离职或退休员工才能查看离职证明'
            ], 403);
        }
        
        $certificates = $employee->resignationCertificates()
            ->with('uploader:id,name')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($cert) {
                return [
                    'id' => $cert->id,
                    'file_name' => $cert->file_name,
                    'file_path' => $cert->file_path,
                    'file_type' => $cert->file_type,
                    'file_size' => $cert->file_size,
                    'upload_source' => $cert->upload_source,
                    'uploaded_by_name' => $cert->uploader ? $cert->uploader->name : '未知',
                    'created_at' => $cert->created_at->format('Y-m-d H:i:s'),
                ];
            });
        
        return response()->json([
            'success' => true,
            'data' => $certificates
        ]);
    }
    
    /**
     * 上传离职证明
     */
    public function uploadResignationCertificate(Request $request)
    {
        $employee = Employee::find($request->user()->id);
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '员工不存在'
            ], 404);
        }
        
        // 检查员工是否为离职或退休状态
        if (!in_array($employee->contract_status, ['terminated', 'retired'])) {
            return response()->json([
                'success' => false,
                'message' => '只有离职或退休员工才能上传离职证明'
            ], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:10240', // 最大10MB
        ], [
            'file.required' => '请选择文件',
            'file.mimes' => '只支持 JPG、PNG、PDF 格式',
            'file.max' => '文件大小不能超过 10MB',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 422);
        }
        
        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileSize = $file->getSize();
            $fileType = $file->getMimeType();
            
            // 生成友好的文件名：员工姓名_离职证明_时间戳.扩展名
            $friendlyName = $employee->name . '_离职证明_' . date('YmdHis') . '.' . $extension;
            
            // 存储文件
            $path = $file->store("public/resignation_certificates/{$employee->id}");
            $filePath = str_replace('public/', '', $path);
            
            // 创建记录
            $certificate = $employee->resignationCertificates()->create([
                'file_name' => $friendlyName, // 使用友好的文件名
                'file_path' => $filePath,
                'file_type' => $fileType,
                'file_size' => $fileSize,
                'uploaded_by' => null, // 员工自己上传，不是管理员上传
                'upload_source' => 'miniprogram',
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '上传成功',
                'data' => [
                    'id' => $certificate->id,
                    'file_name' => $certificate->file_name,
                    'file_path' => $certificate->file_path,
                    'file_type' => $certificate->file_type,
                    'file_size' => $certificate->file_size,
                    'upload_source' => $certificate->upload_source,
                    'created_at' => $certificate->created_at->format('Y-m-d H:i:s'),
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('上传离职证明失败', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '上传失败：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 删除离职证明
     */
    public function deleteResignationCertificate(Request $request, $id)
    {
        $employee = Employee::find($request->user()->id);
        
        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => '员工不存在'
            ], 404);
        }
        
        // 查找离职证明
        $certificate = $employee->resignationCertificates()->find($id);
        
        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => '离职证明不存在或无权删除'
            ], 404);
        }
        
        try {
            // 删除文件
            if (Storage::exists('public/' . $certificate->file_path)) {
                Storage::delete('public/' . $certificate->file_path);
            }
            
            // 删除记录
            $certificate->delete();
            
            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
        } catch (\Exception $e) {
            \Log::error('删除离职证明失败', [
                'certificate_id' => $id,
                'employee_id' => $employee->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }
}
