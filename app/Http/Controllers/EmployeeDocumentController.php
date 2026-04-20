<?php

namespace App\Http\Controllers;

use App\Models\EmployeeDocument;
use App\Models\Employee;
use App\Models\ProjectDocumentConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class EmployeeDocumentController extends Controller
{
    /**
     * 获取员工的资料上传列表（包含配置和上传状态，支持多文件）
     */
    public function index(Request $request, $employeeId)
    {
        try {
            $employee = Employee::with('projects')->findOrFail($employeeId);

            // 获取员工所属项目的资料配置
            $projectIds = $employee->projects->pluck('id')->toArray();

            if (empty($projectIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => '员工未分配到任何项目'
                ]);
            }

            // 获取所有相关项目的资料配置
            $configs = ProjectDocumentConfig::whereIn('project_id', $projectIds)
                ->orderBy('sort_order', 'asc')
                ->get();

            // 获取员工已上传的资料（按config_id分组，支持多文件）
            $uploadedDocuments = EmployeeDocument::where('employee_id', $employeeId)
                ->orderBy('uploaded_at', 'desc')
                ->get()
                ->groupBy('document_config_id');

            // 合并配置和上传状态
            $result = $configs->map(function ($config) use ($uploadedDocuments, $employee) {
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
                    // 保持向后兼容，upload_info 返回第一个文件
                    'upload_info' => $fileCount > 0 ? [
                        'id' => $uploadedFiles->first()->id,
                        'file_url' => $uploadedFiles->first()->file_url,
                        'original_filename' => $uploadedFiles->first()->original_filename,
                        'file_size' => $uploadedFiles->first()->file_size,
                        'file_size_formatted' => $uploadedFiles->first()->file_size_formatted,
                        'uploaded_at' => $uploadedFiles->first()->uploaded_at,
                        'upload_source' => $uploadedFiles->first()->upload_source,
                    ] : null,
                    // 新增：所有文件列表
                    'files' => $uploadedFiles->map(function ($file) {
                        return [
                            'id' => $file->id,
                            'file_url' => $file->file_url,
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
     * 上传员工资料（支持小程序和PC端，支持重新上传）
     */
    public function upload(Request $request, $employeeId)
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
            $employee = Employee::findOrFail($employeeId);
            $config = ProjectDocumentConfig::findOrFail($request->document_config_id);

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
                    'application/octet-stream',
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

            // 同时检查MIME类型和文件扩展名
            $mimeValid = in_array($mimeType, $allowedMimes);
            $extensionValid = in_array($extension, $allowedExtensions);

            if (!$mimeValid && !$extensionValid) {
                \Log::error('文件类型验证失败', [
                    'mime_type' => $mimeType,
                    'extension' => $extension,
                    'allowed_extensions' => $allowedExtensions
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => "文件类型不符合要求，支持的格式：" . implode(', ', $allowedExtensions)
                ], 422);
            }

            // 多文件模式：不再删除旧文件，直接添加新文件
            // 如果需要替换特定文件，使用 destroy 方法先删除

            // 存储文件到public目录
            $publicPath = public_path('employee_documents/' . $employeeId);
            if (!is_dir($publicPath)) {
                mkdir($publicPath, 0755, true);
            }
            
            // 在移动文件前获取文件信息
            $originalFilename = $file->getClientOriginalName();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();
            $extension = $file->getClientOriginalExtension();
            
            $filename = time() . '_' . uniqid() . '.' . $extension;
            $path = 'employee_documents/' . $employeeId . '/' . $filename;
            $file->move($publicPath, $filename);

            // 判断上传来源
            $uploadSource = $request->input('upload_source', 'pc');
            if ($request->header('User-Agent') && str_contains($request->header('User-Agent'), 'miniProgram')) {
                $uploadSource = 'miniapp';
            }

            // 创建新的资料记录（支持多文件）
            $document = EmployeeDocument::create([
                'employee_id' => $employeeId,
                'document_config_id' => $config->id,
                'project_id' => $config->project_id,
                'document_name' => $config->document_name,
                'file_path' => $path,
                'original_filename' => $originalFilename,
                'file_size' => $fileSize,
                'file_type' => $mimeType,
                'upload_source' => $uploadSource,
                'uploaded_at' => now(),
            ]);

            // 获取该配置下的文件总数
            $fileCount = EmployeeDocument::where('employee_id', $employeeId)
                ->where('document_config_id', $config->id)
                ->count();

            \Log::info('文件上传成功', [
                'employee_id' => $employeeId,
                'document_id' => $document->id,
                'file_count' => $fileCount
            ]);

            return response()->json([
                'success' => true,
                'message' => '上传成功',
                'data' => [
                    'id' => $document->id,
                    'file_url' => $document->file_url,
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
     * 删除员工资料
     */
    public function destroy($employeeId, $documentId)
    {
        try {
            $document = EmployeeDocument::where('employee_id', $employeeId)
                ->where('id', $documentId)
                ->firstOrFail();

            // 删除文件
            if ($document->file_path) {
                $filePath = public_path($document->file_path);
                if (file_exists($filePath)) {
                    unlink($filePath);
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
     * 预览员工资料（在浏览器中直接显示）
     */
    public function preview($employeeId, $documentId)
    {
        try {
            $document = EmployeeDocument::where('employee_id', $employeeId)
                ->where('id', $documentId)
                ->firstOrFail();

            $filePath = public_path($document->file_path);
            if (!file_exists($filePath)) {
                \Log::error('预览文件不存在', [
                    'employee_id' => $employeeId,
                    'document_id' => $documentId,
                    'file_path' => $document->file_path,
                    'full_path' => $filePath
                ]);
                return response()->json([
                    'success' => false,
                    'message' => '文件不存在'
                ], 404);
            }
            
            // 根据文件扩展名设置正确的Content-Type
            $contentType = $this->getContentTypeForPreview($document->original_filename);
            
            \Log::info('预览文件信息', [
                'file_path' => $filePath,
                'original_filename' => $document->original_filename,
                'content_type' => $contentType,
                'file_exists' => file_exists($filePath)
            ]);
            
            return response()->file($filePath, [
                'Content-Type' => $contentType,
                'Content-Disposition' => 'inline; filename="' . $document->original_filename . '"'
            ]);
        } catch (\Exception $e) {
            \Log::error('预览员工资料失败: ' . $e->getMessage(), [
                'employee_id' => $employeeId,
                'document_id' => $documentId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => '预览失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 下载员工资料
     */
    public function download($employeeId, $documentId)
    {
        try {
            $document = EmployeeDocument::where('employee_id', $employeeId)
                ->where('id', $documentId)
                ->firstOrFail();

            $filePath = public_path($document->file_path);
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => '文件不存在'
                ], 404);
            }

            $contentType = $document->file_type ?: $this->getContentTypeForPreview($document->original_filename);
            $fileSize = @filesize($filePath) ?: null;

            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
            }

            return response()->stream(function () use ($filePath) {
                $handle = fopen($filePath, 'rb');
                if ($handle) {
                    while (!feof($handle)) {
                        echo fread($handle, 8192);
                        flush();
                    }
                    fclose($handle);
                }
            }, 200, array_filter([
                'Content-Type' => $contentType,
                'Content-Disposition' => 'attachment; filename="' . $document->original_filename . '"',
                'Content-Length' => $fileSize,
                'Cache-Control' => 'private, max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Accept-Ranges' => 'bytes',
                'X-File-Path' => $document->file_path,
                'X-Full-Path' => $filePath,
                'X-File-Size' => $fileSize,
                'X-Content-Type' => $contentType,
            ]));
        } catch (\Exception $e) {
            \Log::error('下载员工资料失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '下载失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取预览用的Content-Type
     */
    private function getContentTypeForPreview($filename)
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // 根据文件扩展名设置Content-Type，确保浏览器能正确显示
        switch ($extension) {
            case 'pdf':
                return 'application/pdf';
            case 'doc':
                return 'application/msword';
            case 'docx':
                return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            case 'xls':
                return 'application/vnd.ms-excel';
            case 'xlsx':
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'gif':
                return 'image/gif';
            case 'webp':
                return 'image/webp';
            case 'bin':
                // .bin文件可能是各种类型，尝试检测实际内容
                return $this->detectBinFileType($filename);
            default:
                // 如果无法识别，尝试检测文件内容
                return $this->detectFileType($filename);
        }
    }

    /**
     * 检测.bin文件的实际类型
     */
    private function detectBinFileType($filename)
    {
        // 根据文件名模式判断可能的类型
        $filename = strtolower($filename);
        
        if (strpos($filename, 'pdf') !== false || strpos($filename, '.pdf') !== false) {
            return 'application/pdf';
        } elseif (strpos($filename, 'doc') !== false || strpos($filename, 'word') !== false) {
            return 'application/msword';
        } elseif (strpos($filename, 'xls') !== false || strpos($filename, 'excel') !== false) {
            return 'application/vnd.ms-excel';
        } elseif (strpos($filename, 'jpg') !== false || strpos($filename, 'jpeg') !== false) {
            return 'image/jpeg';
        } elseif (strpos($filename, 'png') !== false) {
            return 'image/png';
        } else {
            // 默认尝试作为PDF处理（因为很多上传的文件实际上是PDF）
            return 'application/pdf';
        }
    }

    /**
     * 检测文件的实际类型
     */
    private function detectFileType($filename)
    {
        $filePath = public_path($filename);
        
        if (file_exists($filePath)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            
            // 如果检测到的是二进制类型，尝试更精确的检测
            if ($mimeType === 'application/octet-stream') {
                return $this->detectBinFileType($filename);
            }
            
            return $mimeType;
        }
        
        return 'application/octet-stream';
    }
}

