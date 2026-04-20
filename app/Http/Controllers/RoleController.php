<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    /**
     * 获取所有角色列表
     */
    public function index()
    {
        $roles = Role::with('permissions')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'display_name' => $role->display_name,
                    'description' => $role->description,
                    'visible_menus' => $role->visible_menus,
                    'permission_count' => $role->permissions->count(),
                    'permission_ids' => $role->permissions->pluck('id')->toArray(),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * 获取角色详情（包含权限）
     */
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'description' => $role->description,
                'visible_menus' => $role->visible_menus,
                'permissions' => $role->permissions,
                'permission_ids' => $role->permissions->pluck('id')->toArray(),
            ]
        ]);
    }

    /**
     * 更新角色权限
     */
    public function updatePermissions(Request $request, $id)
    {
        $request->validate([
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id'
        ]);

        $permissionIds = $request->input('permission_ids', []);

        $role = Role::findOrFail($id);

        // 超级管理员角色不能修改权限
        if ($role->name === 'super_admin') {
            return response()->json([
                'success' => false,
                'message' => '超级管理员角色拥有所有权限，不能修改'
            ], 400);
        }

        DB::transaction(function () use ($role, $permissionIds) {
            // 同步权限（空数组表示清空该角色全部权限）
            $role->permissions()->sync($permissionIds);
        });

        return response()->json([
            'success' => true,
            'message' => '角色权限更新成功',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'permission_ids' => $role->permissions()->pluck('permissions.id')->toArray(),
            ]
        ]);
    }

    /**
     * 获取所有权限列表（按模块分组）
     */
    public function getPermissions()
    {
        $permissions = Permission::orderBy('sort_order')->get();

        // 按模块分组
        $grouped = $permissions->groupBy('module')->map(function ($items, $module) {
            return [
                'module' => $module,
                'module_name' => $this->getModuleName($module),
                'permissions' => $items->map(function ($p) {
                    return [
                        'id' => $p->id,
                        'module' => $p->module,
                        'action' => $p->action,
                        'name' => $p->name,
                        'description' => $p->description,
                        'route' => $p->route,
                        'method' => $p->method,
                    ];
                })->values()
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $grouped
        ]);
    }

    /**
     * 更新角色的可见菜单
     */
    public function updateVisibleMenus(Request $request, $id)
    {
        $request->validate([
            'visible_menus' => 'nullable|array',
            'visible_menus.*' => 'string'
        ]);

        $role = Role::findOrFail($id);

        // 超级管理员和管理员默认可以看到所有菜单
        if (in_array($role->name, ['super_admin', 'admin'])) {
            $role->visible_menus = null; // null 表示可以看到所有菜单
        } else {
            $role->visible_menus = $request->input('visible_menus');
        }
        
        $role->save();

        return response()->json([
            'success' => true,
            'message' => '菜单显示权限更新成功',
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'display_name' => $role->display_name,
                'visible_menus' => $role->visible_menus,
            ]
        ]);
    }

    /**
     * 获取模块中文名称
     */
    private function getModuleName($module)
    {
        $names = [
            'dashboard' => '仪表盘',
            'users' => '用户管理',
            'permissions' => '权限管理',
            'roles' => '角色管理',
            'employees' => '人员档案',
            'employee_contracts' => '员工合同',
            'employee_documents' => '员工资料',
            'personnel_change' => '人员汇总申请',
            'projects' => '项目管理',
            'project_document_configs' => '项目资料配置',
            'contract_templates' => '合同模板',
            'bid_projects' => '投标项目',
            'attendance' => '考勤管理',
            'attendance_basis' => '考勤依据',
            'basis_records' => '依据管理',
            'salaries' => '工资管理',
            'salary_basis' => '工资依据',
            'salary_summaries' => '工资汇总',
            'salary_payment' => '发工资表',
            'salary_approvals' => '工资审批',
            'salary_payment_requests' => '工资付款申请',
            'special_deductions' => '专项扣除',
            'payroll_remarks' => '备注事项',
            'insurance' => '保险管理',
            'insurance_change' => '参保增减',
            'insurance_payment_requests' => '保险付款申请',
            'process_management' => '汇总申请',
            'social_security' => '社保管理',
            'housing_fund' => '公积金管理',
            'housing_fund_regions' => '公积金地区',
            'housing_fund_configs' => '公积金配置',
            'medical_insurance' => '医保管理',
            'large_medical' => '大额医疗',
            'other_insurance' => '其他保险',
            'approvals' => '审批管理',
            'process_records' => '流程记录',
            'payments' => '付款管理',
            'payment_applications' => '付款申请',
            'payment_summaries' => '出款汇总',
            'invoices' => '发票管理',
            'invoice_projects' => '发票项目配置',
            'invoice_applications' => '开票申请',
            'reimbursements' => '报销管理',
            'reimbursement' => '报销管理',
            'reimbursement_payment_requests' => '报销付款申请',
            'travel_application' => '差旅申请',
            'travel' => '差旅申请',
            'recruitment' => '招聘管理',
            'recruitment_demand' => '招聘需求',
            'shared_files' => '共享文件',
            'account_sets' => '账套管理',
            'base_adjustment' => '基数调整',
            'document_delivery' => '资料交付',
            'delivery_configs' => '交付配置',
            'region_portals' => '地区网页入口',
            'assessment' => '考核管理',
            'signatures' => '签名管理',
            'seals' => '印章管理',
        ];

        return $names[$module] ?? $module;
    }
}
