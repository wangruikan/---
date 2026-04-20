<?php

namespace App\Http\Controllers;

use App\Models\ResignationCertificate;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ResignationCertificateController extends Controller
{
    /**
     * 获取员工的离职证明列表
     */
    public function index(Request $request, $employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        
        $certificates = ResignationCertificate::where('employee_id', $employeeId)
            ->with('uploader')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $certificates
        ]);
    }
    
    /**
     * 上传离职证明
     */
    public function upload(Request $request, $employeeId)
    {
        $validator = \Validator::make($request->all(), [
            'file' => 'required|file|max:10240', // 最大10MB
            'remark' => 'nullable|string|max:500'
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
            
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileName = time() . '_' . uniqid() . '.' . $extension;
            
            // 存储文件
            $path = $file->storeAs('resignation_certificates/' . $employeeId, $fileName, 'public');
            
            // 创建记录
            $certificate = ResignationCertificate::create([
                'employee_id' => $employeeId,
                'file_name' => $originalName,
                'file_path' => $path,
                'file_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id ?? null,
                'upload_source' => $request->input('source', 'pc'),
                'remark' => $request->input('remark'),
            ]);
            
            Log::info('离职证明上传成功', [
                'employee_id' => $employeeId,
                'employee_name' => $employee->name,
                'file_name' => $originalName,
                'uploaded_by' => $request->user()->id ?? 'miniprogram'
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '上传成功',
                'data' => $certificate
            ]);
            
        } catch (\Exception $e) {
            Log::error('离职证明上传失败', [
                'employee_id' => $employeeId,
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
    public function destroy($id)
    {
        try {
            $certificate = ResignationCertificate::findOrFail($id);
            
            // 删除文件
            if (Storage::disk('public')->exists($certificate->file_path)) {
                Storage::disk('public')->delete($certificate->file_path);
            }
            
            $certificate->delete();
            
            return response()->json([
                'success' => true,
                'message' => '删除成功'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '删除失败：' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 下载离职证明
     */
    public function download(Request $request, $id)
    {
        try {
            $certificate = ResignationCertificate::findOrFail($id);
            
            $filePath = storage_path('app/public/' . $certificate->file_path);
            
            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => '文件不存在'
                ], 404);
            }
            
            $contentType = $certificate->file_type ?: 'application/octet-stream';
            $fileSize = @filesize($filePath) ?: null;
            
            // 清除输出缓冲
            if (function_exists('ob_get_level')) {
                while (ob_get_level() > 0) {
                    @ob_end_clean();
                }
            }
            
            // 使用 stream 响应，和员工资料下载完全一致
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
                'Content-Disposition' => 'attachment; filename="' . $certificate->file_name . '"',
                'Content-Length' => $fileSize,
                'Cache-Control' => 'private, max-age=0, no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Accept-Ranges' => 'bytes',
            ]));
            
        } catch (\Exception $e) {
            \Log::error('下载离职证明失败', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '下载失败：' . $e->getMessage()
            ], 500);
        }
    }
}
