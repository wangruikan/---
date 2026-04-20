<?php

namespace App\Http\Controllers;

use App\Models\ReportTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportTemplateController extends Controller
{
    /**
     * 获取模板列表
     */
    public function index(Request $request)
    {
        $query = ReportTemplate::with('creator');
        
        // 按账套筛选
        if ($request->has('account_set_id')) {
            $query->where('account_set_id', $request->account_set_id);
        }
        
        // 按地区类型筛选
        if ($request->has('region_type')) {
            $query->where('region_type', $request->region_type);
        }
        
        // 按地区ID筛选
        if ($request->has('region_id')) {
            $query->where('region_id', $request->region_id);
        }
        
        $templates = $query->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => $templates
        ]);
    }

    /**
     * 创建模板
     */
    public function store(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:100',
                'description' => 'nullable|string',
                'report_title' => 'nullable|string|max:200',
                'region_id' => 'nullable|integer',
                'region_type' => 'nullable|string|max:50',
                'fields' => 'required|array',
                'header_fields' => 'nullable|array',
                'footer_fields' => 'nullable|array',
                'account_set_id' => 'required|integer'
            ]);
            
            // 检查是否已存在相同地区和类型的模板（一个地区只能有一个模板）
            $existingTemplate = ReportTemplate::where('region_id', $request->region_id)
                ->where('region_type', $request->region_type)
                ->where('account_set_id', $request->account_set_id)
                ->first();
            
            if ($existingTemplate) {
                // 更新已有模板
                $existingTemplate->update([
                    'name' => $request->name,
                    'description' => $request->description,
                    'report_title' => $request->report_title,
                    'fields' => $request->fields,
                    'header_fields' => $request->header_fields,
                    'footer_fields' => $request->footer_fields,
                ]);
                
                return response()->json([
                    'success' => true,
                    'message' => '模板更新成功',
                    'data' => $existingTemplate->load('creator')
                ]);
            }
            
            // 创建新模板
            $template = ReportTemplate::create([
                'name' => $request->name,
                'description' => $request->description,
                'report_title' => $request->report_title,
                'region_id' => $request->region_id,
                'region_type' => $request->region_type,
                'fields' => $request->fields,
                'header_fields' => $request->header_fields,
                'footer_fields' => $request->footer_fields,
                'account_set_id' => $request->account_set_id,
                'created_by' => Auth::id()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => '模板创建成功',
                'data' => $template->load('creator')
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '数据验证失败: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            \Log::error('创建报表模板失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '创建模板失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取单个模板
     */
    public function show($id)
    {
        $template = ReportTemplate::with('creator')->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $template
        ]);
    }

    /**
     * 更新模板
     */
    public function update(Request $request, $id)
    {
        $template = ReportTemplate::findOrFail($id);
        
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'report_title' => 'nullable|string|max:200',
            'fields' => 'required|array',
            'header_fields' => 'nullable|array',
            'footer_fields' => 'nullable|array'
        ]);
        
        $template->update([
            'name' => $request->name,
            'description' => $request->description,
            'report_title' => $request->report_title,
            'fields' => $request->fields,
            'header_fields' => $request->header_fields,
            'footer_fields' => $request->footer_fields
        ]);
        
        return response()->json([
            'success' => true,
            'message' => '模板更新成功',
            'data' => $template->load('creator')
        ]);
    }

    /**
     * 删除模板
     */
    public function destroy($id)
    {
        $template = ReportTemplate::findOrFail($id);
        $template->delete();
        
        return response()->json([
            'success' => true,
            'message' => '模板删除成功'
        ]);
    }
    
    /**
     * 复制模板到其他地区
     */
    public function copyToRegions(Request $request, $id)
    {
        try {
            $request->validate([
                'target_region_ids' => 'required|array|min:1',
                'target_region_ids.*' => 'required|integer'
            ]);
            
            $sourceTemplate = ReportTemplate::findOrFail($id);
            $targetRegionIds = $request->target_region_ids;
            $copiedCount = 0;
            $skippedCount = 0;
            
            foreach ($targetRegionIds as $regionId) {
                // 跳过源模板的地区
                if ($regionId == $sourceTemplate->region_id) {
                    $skippedCount++;
                    continue;
                }
                
                // 检查目标地区是否已有模板（一个地区只能有一个模板）
                $existingTemplate = ReportTemplate::where('region_id', $regionId)
                    ->where('region_type', $sourceTemplate->region_type)
                    ->where('account_set_id', $sourceTemplate->account_set_id)
                    ->first();
                
                if ($existingTemplate) {
                    // 更新已有模板
                    $existingTemplate->update([
                        'name' => $sourceTemplate->name,
                        'description' => $sourceTemplate->description,
                        'report_title' => $sourceTemplate->report_title,
                        'fields' => $sourceTemplate->fields,
                        'header_fields' => $sourceTemplate->header_fields,
                        'footer_fields' => $sourceTemplate->footer_fields,
                    ]);
                } else {
                    // 创建新模板
                    ReportTemplate::create([
                        'name' => $sourceTemplate->name,
                        'description' => $sourceTemplate->description,
                        'report_title' => $sourceTemplate->report_title,
                        'region_id' => $regionId,
                        'region_type' => $sourceTemplate->region_type,
                        'fields' => $sourceTemplate->fields,
                        'header_fields' => $sourceTemplate->header_fields,
                        'footer_fields' => $sourceTemplate->footer_fields,
                        'account_set_id' => $sourceTemplate->account_set_id,
                        'created_by' => Auth::id()
                    ]);
                }
                $copiedCount++;
            }
            
            return response()->json([
                'success' => true,
                'message' => "模板已复制到 {$copiedCount} 个地区" . ($skippedCount > 0 ? "，跳过 {$skippedCount} 个（源地区）" : ''),
                'copied_count' => $copiedCount,
                'skipped_count' => $skippedCount
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => '数据验证失败: ' . implode(', ', $e->validator->errors()->all())
            ], 422);
        } catch (\Exception $e) {
            \Log::error('复制模板失败: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => '复制模板失败: ' . $e->getMessage()
            ], 500);
        }
    }
}
