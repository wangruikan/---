<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectDeliveryConfig;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class ProjectDeliveryConfigController extends Controller
{
    /**
     * 检查用户是否有权限访问交付配置
     * 已移除审批级别限制，所有登录用户都可以访问
     */
    private function checkPermission(Request $request, $accountSetId)
    {
        $user = $request->user();
        
        // 超级管理员可以访问所有账套
        if ($user->role === 'admin') {
            return true;
        }
        
        // 查询用户是否属于当前账套
        $accountSetUser = \DB::table('account_set_users')
            ->where('account_set_id', $accountSetId)
            ->where('user_id', $user->id)
            ->first();
        
        // 只要用户属于该账套即可访问
        return $accountSetUser !== null;
    }
    
    /**
     * 检查当前用户是否有权限访问交付配置模块
     * 用于前端判断是否显示菜单
     */
    public function checkAccess(Request $request)
    {
        try {
            $accountSetId = (int)$request->input('current_account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => true,
                    'has_access' => false
                ]);
            }
            
            $hasAccess = $this->checkPermission($request, $accountSetId);
            
            return response()->json([
                'success' => true,
                'has_access' => $hasAccess
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'has_access' => false
            ]);
        }
    }
    
    /**
     * 获取项目交付配置列表
     */
    public function index(Request $request)
    {
        try {
            $accountSetId = (int)$request->input('current_account_set_id');
            
            // 添加调试日志
            \Log::info('delivery-configs请求', [
                'user' => $request->user() ? $request->user()->id : null,
                'account_set_id' => $accountSetId,
                'url' => $request->fullUrl()
            ]);
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }
            
            // 权限检查：用户必须属于当前账套
            $hasPermission = $this->checkPermission($request, $accountSetId);
            
            \Log::info('权限检查结果', [
                'user_id' => $request->user() ? $request->user()->id : null,
                'account_set_id' => $accountSetId,
                'has_permission' => $hasPermission
            ]);
            
            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => '您不属于当前账套，无权访问'
                ], 403);
            }

            $projectQuery = Project::query()
                ->where('account_set_id', $accountSetId);

            if ($request->filled('project_id')) {
                $projectQuery->where('id', $request->input('project_id'));
            }

            $projects = $projectQuery
                ->orderBy('created_at', 'desc')
                ->get();

            $configs = ProjectDeliveryConfig::with(['project', 'creator', 'updater'])
                ->where('account_set_id', $accountSetId)
                ->get()
                ->keyBy('project_id');

            $rows = $projects->map(function (Project $project) use ($configs) {
                $config = $configs->get($project->id);

                if ($config) {
                    $data = $config->toArray();
                    $data['config_exists'] = true;
                    return $data;
                }

                return [
                    'id' => null,
                    'account_set_id' => $project->account_set_id,
                    'project_id' => $project->id,
                    'project' => $project,
                    'delivery_cycle' => null,
                    'delivery_method' => null,
                    'delivery_release_month' => 'current',
                    'required_documents' => [],
                    'is_active' => null,
                    'created_by' => null,
                    'updated_by' => null,
                    'creator' => null,
                    'updater' => null,
                    'created_at' => null,
                    'updated_at' => null,
                    'config_exists' => false,
                ];
            });

            if ($request->filled('delivery_cycle')) {
                $rows = $rows->filter(fn ($row) => ($row['delivery_cycle'] ?? null) === $request->input('delivery_cycle'));
            }

            if ($request->filled('delivery_method')) {
                $rows = $rows->filter(fn ($row) => ($row['delivery_method'] ?? null) === $request->input('delivery_method'));
            }

            if ($request->filled('is_active')) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                $rows = $rows->filter(fn ($row) => ($row['config_exists'] ?? false) && ($row['is_active'] ?? null) === $isActive);
            }

            $rows = $rows->sortBy([
                ['config_exists', 'asc'],
                [fn ($row) => $row['project']['name'] ?? '', 'asc'],
            ])->values();

            $perPage = max(1, (int) $request->input('per_page', 15));
            $currentPage = max(1, (int) $request->input('page', 1));
            $total = $rows->count();
            $items = $rows->slice(($currentPage - 1) * $perPage, $perPage)->values();
            $configs = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $perPage,
                $currentPage,
                [
                    'path' => $request->url(),
                    'query' => $request->query(),
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $configs
            ]);

        } catch (\Exception $e) {
            Log::error('获取项目交付配置列表失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取单个配置详情
     */
    public function show($id)
    {
        try {
            $config = ProjectDeliveryConfig::with(['project', 'creator', 'updater'])->find($id);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => '配置不存在'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('获取配置详情失败', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取详情失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建项目交付配置
     */
    public function store(Request $request)
    {
        try {
            $accountSetId = (int)$request->input('current_account_set_id');
            
            // 权限检查
            if (!$this->checkPermission($request, $accountSetId)) {
                return response()->json([
                    'success' => false,
                    'message' => '您不属于当前账套，无权操作'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'project_id' => 'required|exists:projects,id',
                'delivery_cycle' => 'required|in:monthly,quarterly',
                'delivery_method' => 'required|in:express,electronic',
                'delivery_release_month' => 'required|in:current,next',
                'required_documents' => 'nullable|array',
            ], [
                'project_id.required' => '请选择项目',
                'project_id.exists' => '项目不存在',
                'delivery_cycle.required' => '请选择交付周期',
                'delivery_cycle.in' => '交付周期值无效',
                'delivery_method.required' => '请选择交付方式',
                'delivery_method.in' => '交付方式值无效',
                'delivery_release_month.required' => '请选择任务生成月份',
                'delivery_release_month.in' => '任务生成月份值无效',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $accountSetId = (int)$request->input('current_account_set_id');
            
            if (!$accountSetId) {
                return response()->json([
                    'success' => false,
                    'message' => '请先选择账套'
                ], 400);
            }

            // 检查项目是否已配置
            $existing = ProjectDeliveryConfig::where('project_id', $request->project_id)->first();
            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => '该项目已配置过交付要求，请编辑现有配置'
                ], 422);
            }

            $config = ProjectDeliveryConfig::create([
                'account_set_id' => $accountSetId,
                'project_id' => $request->project_id,
                'delivery_cycle' => $request->delivery_cycle,
                'delivery_method' => $request->delivery_method,
                'delivery_release_month' => $request->input('delivery_release_month', 'current'),
                'required_documents' => $request->input('required_documents', []),
                'is_active' => true,
                'created_by' => $request->user()->id,
            ]);

            // 立即生成第一条交付记录
            $deliveryService = app(\App\Services\DocumentDeliveryService::class);
            $displayMonthDate = \Carbon\Carbon::now()->startOfMonth();
            $period = $deliveryService->resolveDeliveryPeriodForDisplayMonth($config->load('project'), $displayMonthDate);
            $createdDelivery = null;

            if ($period) {
                $createdDelivery = $deliveryService->createDeliveryRecord($config, $period, $displayMonthDate->format('Y-m'));

                \Log::info('配置创建并生成首条交付记录', [
                    'config_id' => $config->id,
                    'period' => $period,
                    'display_month' => $displayMonthDate->format('Y-m'),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => $createdDelivery ? '配置创建成功，已生成当前任务' : '配置创建成功',
                'data' => $config->load(['project', 'creator'])
            ]);

        } catch (\Exception $e) {
            Log::error('创建项目交付配置失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '创建失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新项目交付配置
     */
    public function update(Request $request, $id)
    {
        try {
            $accountSetId = (int)$request->input('current_account_set_id');
            
            // 权限检查
            if (!$this->checkPermission($request, $accountSetId)) {
                return response()->json([
                    'success' => false,
                    'message' => '您不属于当前账套，无权操作'
                ], 403);
            }
            
            $validator = Validator::make($request->all(), [
                'delivery_cycle' => 'required|in:monthly,quarterly',
                'delivery_method' => 'required|in:express,electronic',
                'delivery_release_month' => 'required|in:current,next',
                'required_documents' => 'nullable|array',
                'is_active' => 'nullable|boolean',
            ], [
                'delivery_cycle.required' => '请选择交付周期',
                'delivery_cycle.in' => '交付周期值无效',
                'delivery_method.required' => '请选择交付方式',
                'delivery_method.in' => '交付方式值无效',
                'delivery_release_month.required' => '请选择任务生成月份',
                'delivery_release_month.in' => '任务生成月份值无效',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => '验证失败',
                    'errors' => $validator->errors()
                ], 422);
            }

            $config = ProjectDeliveryConfig::find($id);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => '配置不存在'
                ], 404);
            }

            $config->update([
                'delivery_cycle' => $request->delivery_cycle,
                'delivery_method' => $request->delivery_method,
                'delivery_release_month' => $request->input('delivery_release_month', 'current'),
                'required_documents' => $request->input('required_documents', []),
                'is_active' => $request->input('is_active', true),
                'updated_by' => $request->user()->id,
            ]);

            $config->refresh()->load('project');

            $deliveryService = app(\App\Services\DocumentDeliveryService::class);
            $syncedCount = $deliveryService->syncPendingDeliveriesForConfig($config);

            if ($config->is_active) {
                $displayMonthDate = Carbon::now()->startOfMonth();
                $period = $deliveryService->resolveDeliveryPeriodForDisplayMonth($config, $displayMonthDate);

                if ($period) {
                    $deliveryService->createDeliveryRecord($config, $period, $displayMonthDate->format('Y-m'));
                }
            }

            return response()->json([
                'success' => true,
                'message' => $syncedCount > 0
                    ? "配置更新成功，已同步 {$syncedCount} 条待交付任务"
                    : '配置更新成功',
                'data' => $config->load(['project', 'creator', 'updater'])
            ]);

        } catch (\Exception $e) {
            Log::error('更新项目交付配置失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '更新失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除项目交付配置
     */
    public function destroy(Request $request, $id)
    {
        try {
            $config = ProjectDeliveryConfig::find($id);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => '配置不存在'
                ], 404);
            }
            
            // 权限检查
            if (!$this->checkPermission($request, $config->account_set_id)) {
                return response()->json([
                    'success' => false,
                    'message' => '您不属于当前账套，无权操作'
                ], 403);
            }

            // 检查是否有关联的交付记录
            $deliveryCount = \App\Models\DocumentDelivery::where('project_id', $config->project_id)->count();
            if ($deliveryCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => '该项目已有交付记录，无法删除配置。建议禁用配置而不是删除。'
                ], 422);
            }

            $config->delete();

            return response()->json([
                'success' => true,
                'message' => '配置删除成功'
            ]);

        } catch (\Exception $e) {
            Log::error('删除项目交付配置失败', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '删除失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 切换启用/禁用状态
     */
    public function toggleStatus(Request $request, $id)
    {
        try {
            $config = ProjectDeliveryConfig::find($id);

            if (!$config) {
                return response()->json([
                    'success' => false,
                    'message' => '配置不存在'
                ], 404);
            }
            
            // 权限检查
            if (!$this->checkPermission($request, $config->account_set_id)) {
                return response()->json([
                    'success' => false,
                    'message' => '您不属于当前账套，无权操作'
                ], 403);
            }

            $config->update([
                'is_active' => !$config->is_active,
                'updated_by' => $request->user()->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => $config->is_active ? '已启用' : '已禁用',
                'data' => $config
            ]);

        } catch (\Exception $e) {
            Log::error('切换配置状态失败', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '操作失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
