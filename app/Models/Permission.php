<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'module',
        'action',
        'name',
        'description',
        'sort_order',
    ];

    /**
     * 获取拥有此权限的用户
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions')
            ->withTimestamps();
    }

    /**
     * 获取权限标识（如：employees.view）
     */
    public function getKeyAttribute()
    {
        return $this->module . '.' . $this->action;
    }

    /**
     * 按模块分组获取所有权限
     */
    public static function getGroupedPermissions()
    {
        $permissions = self::orderBy('sort_order')->get();
        
        $grouped = [];
        foreach ($permissions as $permission) {
            if (!isset($grouped[$permission->module])) {
                $grouped[$permission->module] = [
                    'module' => $permission->module,
                    'module_name' => self::getModuleName($permission->module),
                    'permissions' => []
                ];
            }
            $grouped[$permission->module]['permissions'][] = $permission;
        }
        
        return array_values($grouped);
    }

    /**
     * 获取模块中文名称
     */
    public static function getModuleName($module)
    {
        $names = [
            'employees' => '人员档案',
            'personnel_changes' => '人员汇总申请',
            'projects' => '项目管理',
            'bid_projects' => '投标项目',
            'attendance' => '考勤管理',
            'assessment' => '考核管理',
            'salaries' => '薪金计算',
            'social_security' => '社保管理',
            'housing_fund' => '公积金管理',
            'approvals' => '审批管理',
            'payment_applications' => '付款申请',
            'invoice_applications' => '发票申请',
            'reimbursement' => '报销管理',
            'travel' => '差旅申请',
            'recruitment' => '招聘管理',
            'shared_files' => '共享文件',
            'contracts' => '合同管理',
            'salary_summaries' => '工资汇总',
            'account_sets' => '账套管理',
            'basis' => '基础数据',
            'dashboard' => '仪表盘',
            'insurance_change' => '保险变更',
            'invoice_projects' => '发票项目',
            'other_insurance' => '其他保险',
            'payment_summaries' => '付款汇总',
            'permissions' => '权限管理',
            'settings' => '系统设置',
            'special_deductions' => '专项扣除',
            'users' => '用户管理',
        ];
        
        return $names[$module] ?? $module;
    }

    /**
     * 获取操作中文名称
     */
    public static function getActionName($action)
    {
        $names = [
            'view' => '查看',
            'create' => '新增',
            'edit' => '编辑',
            'delete' => '删除',
            'approve' => '审批',
            'export' => '导出',
        ];
        
        return $names[$action] ?? $action;
    }
}
