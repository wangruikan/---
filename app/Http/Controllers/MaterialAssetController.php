<?php

namespace App\Http\Controllers;

use App\Models\MaterialAsset;
use App\Models\MaterialAssetFile;
use App\Traits\ChecksPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class MaterialAssetController extends ApiController
{
    use ChecksPermission;

    private function buildFileUrl(Request $request, string $filePath): string
    {
        $host = $request->getSchemeAndHttpHost();

        if (strpos($filePath, 'http') === 0) {
            return $filePath;
        }
        if (strpos($filePath, 'uploads/') === 0) {
            return $host . '/' . $filePath;
        }
        return $host . '/storage/' . $filePath;
    }

    public function index(Request $request)
    {
        if ($response = $this->checkPermission('material_assets.view')) {
            return $response;
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $query = MaterialAsset::with(['creator:id,name'])
            ->where('account_set_id', $accountSetId);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->input('keyword'));
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', '%' . $keyword . '%');
            });
        }

        $perPage = (int)($request->input('per_page', 20));
        $assets = $query->orderBy('id', 'desc')->paginate($perPage);

        // 兼容前端列表展示：返回 file_url/file_name 为“第一条附件”，并给出附件数量
        $assets->getCollection()->transform(function ($row) use ($request) {
            $row->files_count = MaterialAssetFile::where('material_asset_id', $row->id)->count();

            $firstFile = MaterialAssetFile::where('material_asset_id', $row->id)
                ->orderBy('id', 'desc')
                ->first();

            if ($firstFile && !empty($firstFile->file_path)) {
                $row->file_name = $firstFile->file_name;
                $row->file_url = $this->buildFileUrl($request, $firstFile->file_path);
            }
            return $row;
        });

        return $this->paginated($assets, '获取成功');
    }

    public function show(Request $request, $id)
    {
        if ($response = $this->checkPermission('material_assets.view')) {
            return $response;
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $asset = MaterialAsset::with(['creator:id,name', 'files', 'files.uploader:id,name'])
            ->where('account_set_id', $accountSetId)
            ->findOrFail($id);

        // 给每个附件补 file_url
        $asset->files->transform(function ($f) use ($request) {
            $f->file_url = $this->buildFileUrl($request, $f->file_path);
            return $f;
        });

        return $this->success($asset, '获取成功');
    }

    public function store(Request $request)
    {
        if ($response = $this->checkPermission('material_assets.create')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'file' => 'required|file|max:51200', // 50MB
        ]);

        if ($validator->fails()) {
            return $this->error('验证失败', $validator->errors(), 422);
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = time() . '_' . uniqid() . ($extension ? '.' . $extension : '');
        $path = $file->storeAs('material_assets/' . $accountSetId, $filename, 'public');

        $asset = MaterialAsset::create([
            'account_set_id' => $accountSetId,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'status' => 'archived', // 默认已归档
            'created_by' => optional($request->user())->id,
        ]);

        $fileRow = MaterialAssetFile::create([
            'material_asset_id' => $asset->id,
            'file_path' => $path,
            'file_name' => $originalName,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => optional($request->user())->id,
        ]);

        $asset->file_name = $fileRow->file_name;
        $asset->file_url = $this->buildFileUrl($request, $fileRow->file_path);
        $asset->files_count = 1;

        return $this->success($asset, '上传成功');
    }

    public function update(Request $request, $id)
    {
        if ($response = $this->checkPermission('material_assets.edit')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->error('验证失败', $validator->errors(), 422);
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $asset = MaterialAsset::where('account_set_id', $accountSetId)->findOrFail($id);

        $data = [];
        foreach (['name', 'description'] as $field) {
            if ($request->filled($field)) {
                $data[$field] = $request->input($field);
            }
        }

        if (!empty($data)) {
            $asset->update($data);
        }

        return $this->success($asset, '更新成功');
    }

    /**
     * 为某个资料新增附件（编辑弹窗里使用）
     */
    public function uploadFile(Request $request, $id)
    {
        if ($response = $this->checkPermission('material_assets.edit')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|max:51200',
        ]);

        if ($validator->fails()) {
            return $this->error('验证失败', $validator->errors(), 422);
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $asset = MaterialAsset::where('account_set_id', $accountSetId)->findOrFail($id);
        if ($asset->status !== 'archived') {
            return $this->error('资料处于申请中或使用中，不能新增附件', null, 400);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $extension = $file->getClientOriginalExtension();
        $filename = time() . '_' . uniqid() . ($extension ? '.' . $extension : '');
        $path = $file->storeAs('material_assets/' . $accountSetId, $filename, 'public');

        $row = MaterialAssetFile::create([
            'material_asset_id' => $asset->id,
            'file_path' => $path,
            'file_name' => $originalName,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => optional($request->user())->id,
        ]);

        $row->file_url = $this->buildFileUrl($request, $row->file_path);

        return $this->success($row, '上传成功');
    }

    /**
     * 删除资料附件（编辑弹窗里使用）
     */
    public function deleteFile(Request $request, $id, $fileId)
    {
        if ($response = $this->checkPermission('material_assets.edit')) {
            return $response;
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $asset = MaterialAsset::where('account_set_id', $accountSetId)->findOrFail($id);
        if ($asset->status !== 'archived') {
            return $this->error('资料处于申请中或使用中，不能删除附件', null, 400);
        }

        $fileRow = MaterialAssetFile::where('material_asset_id', $asset->id)->findOrFail($fileId);

        $count = MaterialAssetFile::where('material_asset_id', $asset->id)->count();
        if ($count <= 1) {
            return $this->error('至少保留一个附件，不能删除最后一个文件', null, 400);
        }

        $fullPath = storage_path('app/public/' . $fileRow->file_path);
        if (!empty($fileRow->file_path) && file_exists($fullPath)) {
            @unlink($fullPath);
        }

        $fileRow->delete();

        return $this->success(null, '删除成功');
    }

    public function destroy(Request $request, $id)
    {
        if ($response = $this->checkPermission('material_assets.delete')) {
            return $response;
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $asset = MaterialAsset::where('account_set_id', $accountSetId)->findOrFail($id);

        if ($asset->status !== 'archived') {
            return $this->error('资料处于申请中或使用中，不能删除', null, 400);
        }

        $hasActive = $asset->requestItems()
            ->whereIn('status', ['pending', 'in_use'])
            ->exists();

        if ($hasActive) {
            return $this->error('该资料存在未完成的申请记录，不能删除', null, 400);
        }

        // 删除所有附件文件和记录
        $files = MaterialAssetFile::where('material_asset_id', $asset->id)->get();
        foreach ($files as $f) {
            $fullPath = storage_path('app/public/' . $f->file_path);
            if (!empty($f->file_path) && file_exists($fullPath)) {
                @unlink($fullPath);
            }
            $f->delete();
        }

        $asset->delete();

        return $this->success(null, '删除成功');
    }
}

