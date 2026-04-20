<?php

namespace App\Http\Controllers;

use App\Models\SharedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\ChecksPermission;

class SharedFileController extends Controller
{
    use ChecksPermission;
    // 获取文件列表
    public function index(Request $request)
    {
        if ($response = $this->checkPermission('shared_files.view')) {
            return $response;
        }
        
        $query = SharedFile::query()->with('uploader');

        // 【账套过滤】
        $currentAccountSetId = $request->input('current_account_set_id');
        if ($currentAccountSetId) {
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            $query->whereRaw('1 = 0');
        }

        // 搜索文件名
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->name . '%');
        }

        // 筛选文件类型
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        // 【新增】筛选文件分类（共享文件/须知文件）
        if ($request->filled('file_category')) {
            $query->where('file_category', $request->file_category);
        }

        $files = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        // 转换数据格式
        $files->getCollection()->transform(function ($file) {
            return [
                'id' => $file->id,
                'name' => $file->name,
                'original_name' => $file->original_name,
                'type' => $file->type,
                'size' => $file->size,
                'path' => $file->path,
                'uploader_id' => $file->uploader_id,
                'uploader_name' => $file->uploader ? $file->uploader->name : '未知',
                'description' => $file->description,
                'file_category' => $file->file_category ?? 'shared',  // 【新增】
                'created_at' => $file->created_at,
                'updated_at' => $file->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $files->items(),
            'total' => $files->total(),
            'current_page' => $files->currentPage(),
            'per_page' => $files->perPage(),
            'last_page' => $files->lastPage()
        ]);
    }

    // 上传文件
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'description' => 'nullable|string',
            'file_category' => 'nullable|in:shared,notice',  // 【新增】文件分类验证
            'folder_path' => 'nullable|string',  // 【新增】文件夹路径
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // 使用最原始的PHP文件上传方式，完全不依赖Laravel的Storage
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                return response()->json([
                    'success' => false,
                    'message' => '没有上传文件或上传失败'
                ], 400);
            }

            // 使用最原始的PHP文件上传方式，完全不使用Laravel的文件处理
            $uploadedFile = $_FILES['file'];
            
            // 获取文件信息
            $originalName = $uploadedFile['name'];
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $size = $uploadedFile['size'];
            $tmpName = $uploadedFile['tmp_name'];
            
            // 【新增】如果有文件夹路径，使用它作为文件名
            $fileNameToSave = $request->input('folder_path', $originalName);
            
            // 基本文件验证 - 使用纯PHP方式
            if ($size > 10 * 1024 * 1024) { // 10MB限制
                return response()->json([
                    'success' => false,
                    'message' => '文件大小不能超过10MB'
                ], 400);
            }
            
            if (empty($extension)) {
                return response()->json([
                    'success' => false,
                    'message' => '文件必须有扩展名'
                ], 400);
            }
            
            // 生成唯一文件名
            $fileName = uniqid() . '_' . time() . '.' . $extension;
            
            // 创建存储目录 - 改为public/storage/shared_files
            $uploadDir = public_path('storage/shared_files/');
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // 移动文件到存储目录 - 使用最原始的move_uploaded_file
            $filePath = $uploadDir . $fileName;
            if (!move_uploaded_file($tmpName, $filePath)) {
                throw new \Exception('文件移动失败');
            }
            
            // 判断文件类型
            $type = $this->getFileTypeByExtension($extension);

            // 【账套关联】
            $currentAccountSetId = $request->input('current_account_set_id');
            
            // 创建文件记录
            $sharedFile = SharedFile::create([
                'name' => $fileNameToSave,  // 【修改】使用包含路径的文件名
                'original_name' => $originalName,
                'path' => 'shared_files/' . $fileName,
                'type' => $type,
                'size' => $size,
                'uploader_id' => $request->user()->id,
                'description' => $request->description,
                'account_set_id' => $currentAccountSetId,
                'file_category' => $request->input('file_category', 'shared'),  // 【新增】默认为共享文件
            ]);

            return response()->json([
                'success' => true,
                'message' => '文件上传成功',
                'data' => $sharedFile->load('uploader')
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '文件上传失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 获取单个文件信息
    public function show($id)
    {
        $file = SharedFile::with('uploader')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $file->id,
                'name' => $file->name,
                'original_name' => $file->original_name,
                'type' => $file->type,
                'size' => $file->size,
                'path' => $file->path,
                'uploader_id' => $file->uploader_id,
                'uploader_name' => $file->uploader ? $file->uploader->name : '未知',
                'description' => $file->description,
                'created_at' => $file->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $file->updated_at->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    // 更新文件信息
    public function update(Request $request, $id)
    {
        $file = SharedFile::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        $file->update($request->only(['name', 'description']));

        return response()->json([
            'success' => true,
            'message' => '文件信息更新成功',
            'data' => $file->fresh()->load('uploader')
        ]);
    }

    // 删除文件（逻辑删除，保留物理文件）
    public function destroy($id)
    {
        $file = SharedFile::findOrFail($id);

        try {
            // 只做逻辑删除，不删除物理文件
            // 物理文件保留，以便后续恢复或审计
            $file->delete();

            return response()->json([
                'success' => true,
                'message' => '文件删除成功'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '文件删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    // 查看/预览文件（在浏览器中打开）
    public function view($id)
    {
        $file = SharedFile::findOrFail($id);

        // 使用纯PHP方式，改为public/storage路径
        $filePath = public_path('storage/' . $file->path);

        if (!file_exists($filePath)) {
            abort(404, '文件不存在');
        }

        // 获取文件信息
        $fileSize = filesize($filePath);
        $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
        $mimeType = $this->getMimeTypeByExtension($extension);
        
        // 设置响应头 - 与下载不同，这里使用 inline 让浏览器直接显示
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: inline; filename="' . $file->original_name . '"');  // inline 而不是 attachment
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: public, max-age=3600');  // 允许缓存1小时
        header('Pragma: public');
        
        // 清除所有输出缓冲
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // 直接读取并输出文件
        readfile($filePath);
        exit;
    }

    // 下载文件
    public function download($id)
    {
        $file = SharedFile::findOrFail($id);

        // 使用纯PHP方式，改为public/storage路径
        $filePath = public_path('storage/' . $file->path);

        if (!file_exists($filePath)) {
            abort(404, '文件不存在');
        }

        // 获取文件信息
        $fileSize = filesize($filePath);
        $extension = strtolower(pathinfo($file->original_name, PATHINFO_EXTENSION));
        $mimeType = $this->getMimeTypeByExtension($extension);
        
        // 设置响应头
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $file->original_name . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // 清除所有输出缓冲
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // 直接读取并输出文件
        readfile($filePath);
        exit;
    }

    // 根据文件扩展名判断文件类型 - 避免使用fileinfo扩展
    private function getFileTypeByExtension($extension)
    {
        $extension = strtolower($extension);
        
        // 文档类型
        $documentTypes = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'rtf', 'odt', 'ods', 'odp'];
        if (in_array($extension, $documentTypes)) {
            return 'document';
        }

        // 图片类型
        $imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'ico', 'tiff', 'tif'];
        if (in_array($extension, $imageTypes)) {
            return 'image';
        }

        // 视频类型
        $videoTypes = ['mp4', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mkv', 'm4v', '3gp'];
        if (in_array($extension, $videoTypes)) {
            return 'video';
        }

        // 音频类型
        $audioTypes = ['mp3', 'wav', 'flac', 'aac', 'ogg', 'wma', 'm4a'];
        if (in_array($extension, $audioTypes)) {
            return 'audio';
        }

        // 压缩文件类型
        $archiveTypes = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'];
        if (in_array($extension, $archiveTypes)) {
            return 'archive';
        }

        // 代码文件类型
        $codeTypes = ['js', 'css', 'html', 'htm', 'php', 'py', 'java', 'cpp', 'c', 'json', 'xml', 'sql'];
        if (in_array($extension, $codeTypes)) {
            return 'code';
        }

        return 'other';
    }

    // 根据文件扩展名获取MIME类型 - 避免使用fileinfo扩展
    private function getMimeTypeByExtension($extension)
    {
        $extension = strtolower($extension);
        
        $mimeTypes = [
            // 文档类型
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            
            // 图片类型
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            
            // 视频类型
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime',
            'wmv' => 'video/x-ms-wmv',
            'flv' => 'video/x-flv',
            'webm' => 'video/webm',
            'mkv' => 'video/x-matroska',
            
            // 音频类型
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'flac' => 'audio/flac',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'wma' => 'audio/x-ms-wma',
            
            // 压缩文件类型
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            '7z' => 'application/x-7z-compressed',
            'tar' => 'application/x-tar',
            'gz' => 'application/gzip',
            
            // 代码文件类型
            'js' => 'application/javascript',
            'css' => 'text/css',
            'html' => 'text/html',
            'htm' => 'text/html',
            'php' => 'text/x-php',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'sql' => 'application/sql',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}

