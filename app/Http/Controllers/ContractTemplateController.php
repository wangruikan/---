<?php

namespace App\Http\Controllers;

use App\Models\ContractTemplate;
use App\Models\Project;
use App\Models\SharedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ContractTemplateController extends Controller
{
    /**
     * 获取项目的合同模板列表
     */
    public function index(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        
        // 检查权限
        $this->checkProjectAccess($request, $project);

        $templates = ContractTemplate::where('project_id', $projectId)
            ->with(['sharedFile', 'creator'])
            ->orderBy('contract_type')
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('contract_type');

        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * 添加合同模板
     */
    public function store(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        
        // 检查权限
        $this->checkProjectAccess($request, $project);

        $validator = Validator::make($request->all(), [
            'contract_type' => 'required|in:labor,termination,retirement,other',
            'shared_file_id' => 'required|exists:shared_files,id',
            'is_default' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        // 检查共享文件是否存在
        $sharedFile = SharedFile::findOrFail($request->shared_file_id);

        DB::beginTransaction();
        try {
               // 检查是否要设置为默认模板
               $shouldSetAsDefault = $request->input('is_default', false);
               
               if ($shouldSetAsDefault) {
                   // 如果要设置为默认，先取消同类型其他默认模板
                   ContractTemplate::where('project_id', $projectId)
                       ->where('contract_type', $request->contract_type)
                       ->where('is_default', true)
                       ->update(['is_default' => false]);
               }

               // 创建新模板
               $template = ContractTemplate::create([
                   'project_id' => $projectId,
                   'contract_type' => $request->contract_type,
                   'shared_file_id' => $request->shared_file_id,
                   'is_default' => $shouldSetAsDefault,
                   'created_by' => $request->user()->id,
               ]);

            $template->load(['sharedFile', 'creator']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '合同模板设置成功',
                'data' => $template
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '设置失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 设置默认模板
     */
    public function setDefault(Request $request, $templateId)
    {
        $template = ContractTemplate::findOrFail($templateId);
        
        // 检查权限
        $this->checkProjectAccess($request, $template->project);

        DB::beginTransaction();
        try {
            // 取消同类型的其他默认模板
            ContractTemplate::where('project_id', $template->project_id)
                ->where('contract_type', $template->contract_type)
                ->update(['is_default' => false]);

            // 设置当前模板为默认
            $template->update(['is_default' => true]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => '默认模板设置成功'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => '设置失败：' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除合同模板
     */
    public function destroy(Request $request, $templateId)
    {
        $template = ContractTemplate::findOrFail($templateId);
        
        // 检查权限
        $this->checkProjectAccess($request, $template->project);

        // 检查是否还有同类型的其他模板
        $otherTemplates = ContractTemplate::where('project_id', $template->project_id)
            ->where('contract_type', $template->contract_type)
            ->where('id', '!=', $templateId)
            ->count();

        if ($otherTemplates == 0) {
            return response()->json([
                'success' => false,
                'message' => '不能删除最后一个模板，每种合同类型至少需要保留一个模板'
            ], 400);
        }

        $template->delete();

        return response()->json([
            'success' => true,
            'message' => '合同模板删除成功'
        ]);
    }

    /**
     * 获取项目的默认合同模板
     */
    public function getDefaultTemplates(Request $request, $projectId)
    {
        $project = Project::findOrFail($projectId);
        
        // 检查权限
        $this->checkProjectAccess($request, $project);

        $defaultTemplates = ContractTemplate::where('project_id', $projectId)
            ->where('is_default', true)
            ->with(['sharedFile'])
            ->get()
            ->keyBy('contract_type');

        return response()->json([
            'success' => true,
            'data' => $defaultTemplates
        ]);
    }

    /**
     * 检查项目访问权限
     */
    private function checkProjectAccess(Request $request, Project $project)
    {
        $user = $request->user();
        
        // Admin用户可以访问所有项目
        if ($user->role === 'admin') {
            return;
        }

        // 检查用户是否有权限访问该项目
        $hasAccess = $user->accountSets()
            ->where('account_set_id', $project->account_set_id)
            ->exists();

        if (!$hasAccess) {
            abort(403, '没有权限访问该项目');
        }
    }

    /**
     * 保存占位符位置
     */
    public function savePlaceholderPositions(Request $request)
    {
        // 允许的占位符类型（与 Project::getAvailablePlaceholderFields 保持一致）
        $allowedTypes = array_keys(Project::getAvailablePlaceholderFields());
        
        $validator = Validator::make($request->all(), [
            'template_id' => 'required|integer|exists:contract_templates,id',
            'positions' => 'present|array',
            'positions.*.type' => 'required|string|in:' . implode(',', $allowedTypes),
            'positions.*.x' => 'required|numeric',
            'positions.*.y' => 'required|numeric',
            'positions.*.width' => 'required|numeric',
            'positions.*.height' => 'required|numeric',
            'positions.*.page' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => '验证失败',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $template = ContractTemplate::findOrFail($request->template_id);
            
            // 检查权限
            $this->checkProjectAccess($request, $template->project);

            // 保存占位符位置
            $template->placeholder_positions = $request->positions;
            $template->save();

            return response()->json([
                'success' => true,
                'message' => '占位符位置保存成功'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '保存失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取占位符位置
     */
    public function getPlaceholderPositions(Request $request, $templateId)
    {
        try {
            $template = ContractTemplate::findOrFail($templateId);
            
            // 检查权限
            $this->checkProjectAccess($request, $template->project);

            return response()->json([
                'success' => true,
                'positions' => $template->placeholder_positions ?? []
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => '获取失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
