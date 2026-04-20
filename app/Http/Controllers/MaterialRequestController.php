<?php

namespace App\Http\Controllers;

use App\Models\MaterialAsset;
use App\Models\MaterialRequest;
use App\Models\MaterialRequestItem;
use App\Services\ApprovalService;
use App\Traits\ChecksPermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MaterialRequestController extends ApiController
{
    use ChecksPermission;

    protected ApprovalService $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    public function index(Request $request)
    {
        if ($response = $this->checkPermission('material_requests.view')) {
            return $response;
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $query = MaterialRequest::with([
            'applicant:id,name',
            'items.material',
            'approvalInstance.attachments',
        ])->where('account_set_id', $accountSetId);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('keyword')) {
            $keyword = trim($request->input('keyword'));
            $query->where(function ($q) use ($keyword) {
                $q->where('reason', 'like', '%' . $keyword . '%');
            });
        }

        $perPage = (int)($request->input('per_page', 20));
        $rows = $query->orderBy('id', 'desc')->paginate($perPage);

        // 计算归还数量
        $rows->getCollection()->transform(function ($row) {
            $total = $row->items ? $row->items->count() : 0;
            $returned = $row->items ? $row->items->where('status', 'returned')->count() : 0;
            $row->total_items = $total;
            $row->returned_items = $returned;
            return $row;
        });

        return $this->paginated($rows, '获取成功');
    }

    public function show(Request $request, $id)
    {
        if ($response = $this->checkPermission('material_requests.view')) {
            return $response;
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $row = MaterialRequest::with([
            'applicant:id,name',
            'items.material',
            'approvalInstance.attachments',
        ])->where('account_set_id', $accountSetId)->findOrFail($id);

        $row->total_items = $row->items->count();
        $row->returned_items = $row->items->where('status', 'returned')->count();

        return $this->success($row, '获取成功');
    }

    /**
     * 发起资料申请：创建申请记录 + 锁定资料为申请中 + 创建审批流程
     */
    public function store(Request $request)
    {
        if ($response = $this->checkPermission('material_requests.create')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
            'expected_return_date' => 'required|date',
            'stamp_method' => 'nullable|in:online,offline',
            'material_ids' => 'required|array|min:1',
            'material_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error('验证失败', $validator->errors(), 422);
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $user = $request->user();
        if (!$user) {
            return $this->error('请先登录', null, 401);
        }

        $materialIds = array_values(array_unique(array_map('intval', $request->input('material_ids', []))));

        DB::beginTransaction();
        try {
            /** @var \Illuminate\Database\Eloquent\Collection<int, MaterialAsset> $assets */
            $assets = MaterialAsset::where('account_set_id', $accountSetId)
                ->whereIn('id', $materialIds)
                ->lockForUpdate()
                ->get();

            if ($assets->count() !== count($materialIds)) {
                throw new \Exception('所选资料不存在或不属于当前账套');
            }

            $unavailable = $assets->filter(fn($a) => $a->status !== 'archived');
            if ($unavailable->isNotEmpty()) {
                $names = $unavailable->pluck('name')->toArray();
                throw new \Exception('以下资料不可申请（申请中/使用中）：' . implode('、', $names));
            }

            $materialRequest = MaterialRequest::create([
                'account_set_id' => $accountSetId,
                'applicant_id' => $user->id,
                'reason' => $request->input('reason'),
                'expected_return_date' => $request->input('expected_return_date'),
                'status' => 'pending', // 审批中（申请中）
            ]);

            foreach ($assets as $asset) {
                MaterialRequestItem::create([
                    'material_request_id' => $materialRequest->id,
                    'material_asset_id' => $asset->id,
                    'status' => 'pending',
                ]);
            }

            // 锁定资料为申请中，防止重复申请
            MaterialAsset::whereIn('id', $materialIds)->update(['status' => 'applying']);

            // 创建审批流程（复用审批管理模块）
            $stampMethod = $request->input('stamp_method', 'online');
            $instance = $this->approvalService->createApprovalInstance(
                $accountSetId,
                'material_request',
                $materialRequest->id,
                $user->id,
                [], // 附件由前端创建后通过 /approvals/{instanceId}/upload-attachment 上传
                false,
                $stampMethod
            );

            // 兜底写入 approval_instance_id（updateBusinessStatus 中也会写）
            if (!$materialRequest->approval_instance_id) {
                $materialRequest->approval_instance_id = $instance->id;
                $materialRequest->save();
            }

            DB::commit();

            $materialRequest->load(['applicant:id,name', 'items.material', 'approvalInstance.attachments']);

            return $this->success($materialRequest, '申请已提交，审批流程已创建');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), null, 400);
        }
    }

    /**
     * 归还资料（可分开归还）
     */
    public function returnMaterials(Request $request, $id)
    {
        if ($response = $this->checkPermission('material_requests.return')) {
            return $response;
        }

        $validator = Validator::make($request->all(), [
            'material_ids' => 'nullable|array',
            'material_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return $this->error('验证失败', $validator->errors(), 422);
        }

        $accountSetId = $request->input('current_account_set_id') ?: $request->header('X-Account-Set-Id');
        if (!$accountSetId) {
            return $this->error('请选择账套', null, 400);
        }

        $materialIds = $request->input('material_ids');
        if (is_array($materialIds)) {
            $materialIds = array_values(array_unique(array_map('intval', $materialIds)));
        } else {
            $materialIds = null;
        }

        DB::beginTransaction();
        try {
            $row = MaterialRequest::where('account_set_id', $accountSetId)
                ->lockForUpdate()
                ->findOrFail($id);

            if ($row->status !== 'in_use') {
                throw new \Exception('当前申请未处于使用中，不能归还');
            }

            $itemsQuery = MaterialRequestItem::where('material_request_id', $row->id)
                ->where('status', 'in_use')
                ->lockForUpdate();

            if ($materialIds) {
                $itemsQuery->whereIn('material_asset_id', $materialIds);
            }

            $items = $itemsQuery->get();
            if ($items->isEmpty()) {
                throw new \Exception('没有可归还的资料');
            }

            $assetIds = $items->pluck('material_asset_id')->toArray();

            MaterialRequestItem::whereIn('id', $items->pluck('id')->toArray())
                ->update([
                    'status' => 'returned',
                    'returned_at' => now(),
                ]);

            // 资料恢复为已归档
            MaterialAsset::whereIn('id', $assetIds)->update(['status' => 'archived']);

            // 如果全部归还，则申请记录也归档
            $remainingInUse = MaterialRequestItem::where('material_request_id', $row->id)
                ->where('status', 'in_use')
                ->count();

            if ($remainingInUse === 0) {
                $row->update([
                    'status' => 'archived',
                    'archived_at' => now(),
                ]);
            }

            DB::commit();

            $row->load(['applicant:id,name', 'items.material', 'approvalInstance.attachments']);
            $row->total_items = $row->items->count();
            $row->returned_items = $row->items->where('status', 'returned')->count();

            return $this->success($row, '归还成功');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error($e->getMessage(), null, 400);
        }
    }
}

