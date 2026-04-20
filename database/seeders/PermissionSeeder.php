<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 先清理掉人员汇总申请模块中不再使用的权限（例如旧的 edit 等）
        Permission::where('module', 'personnel_changes')
            ->whereNotIn('action', ['view', 'create', 'delete'])
            ->delete();

        $permissions = [
            // 人员档案
            ['module' => 'employees', 'action' => 'view', 'name' => '查看', 'sort_order' => 1],
            ['module' => 'employees', 'action' => 'create', 'name' => '新增', 'sort_order' => 2],
            ['module' => 'employees', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 3],
            ['module' => 'employees', 'action' => 'delete', 'name' => '删除', 'sort_order' => 4],
            
            // 人员汇总申请
            ['module' => 'personnel_changes', 'action' => 'view', 'name' => '查看', 'sort_order' => 10],
            ['module' => 'personnel_changes', 'action' => 'create', 'name' => '新增', 'sort_order' => 11],
            ['module' => 'personnel_changes', 'action' => 'delete', 'name' => '删除', 'sort_order' => 12],
            
            // 项目管理
            ['module' => 'projects', 'action' => 'view', 'name' => '查看', 'sort_order' => 20],
            ['module' => 'projects', 'action' => 'create', 'name' => '新增', 'sort_order' => 21],
            ['module' => 'projects', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 22],
            ['module' => 'projects', 'action' => 'delete', 'name' => '删除', 'sort_order' => 23],
            
            // 投标项目
            ['module' => 'bid_projects', 'action' => 'view', 'name' => '查看', 'sort_order' => 30],
            ['module' => 'bid_projects', 'action' => 'create', 'name' => '新增', 'sort_order' => 31],
            ['module' => 'bid_projects', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 32],
            ['module' => 'bid_projects', 'action' => 'delete', 'name' => '删除', 'sort_order' => 33],
            
            // 考勤管理
            ['module' => 'attendance', 'action' => 'view', 'name' => '查看', 'sort_order' => 40],
            ['module' => 'attendance', 'action' => 'create', 'name' => '新增', 'sort_order' => 41],
            ['module' => 'attendance', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 42],
            ['module' => 'attendance', 'action' => 'delete', 'name' => '删除', 'sort_order' => 43],
            
            // 考核管理
            ['module' => 'assessment', 'action' => 'view', 'name' => '查看', 'sort_order' => 50],
            ['module' => 'assessment', 'action' => 'create', 'name' => '新增', 'sort_order' => 51],
            ['module' => 'assessment', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 52],
            ['module' => 'assessment', 'action' => 'delete', 'name' => '删除', 'sort_order' => 53],
            
            // 薪金计算
            ['module' => 'salaries', 'action' => 'view', 'name' => '查看', 'sort_order' => 60],
            ['module' => 'salaries', 'action' => 'create', 'name' => '新增', 'sort_order' => 61],
            ['module' => 'salaries', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 62],
            ['module' => 'salaries', 'action' => 'delete', 'name' => '删除', 'sort_order' => 63],
            
            // 社保管理
            ['module' => 'social_security', 'action' => 'view', 'name' => '查看', 'sort_order' => 70],
            ['module' => 'social_security', 'action' => 'create', 'name' => '新增', 'sort_order' => 71],
            ['module' => 'social_security', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 72],
            ['module' => 'social_security', 'action' => 'delete', 'name' => '删除', 'sort_order' => 73],
            
            // 公积金管理
            ['module' => 'housing_fund', 'action' => 'view', 'name' => '查看', 'sort_order' => 80],
            ['module' => 'housing_fund', 'action' => 'create', 'name' => '新增', 'sort_order' => 81],
            ['module' => 'housing_fund', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 82],
            ['module' => 'housing_fund', 'action' => 'delete', 'name' => '删除', 'sort_order' => 83],
            
            // 审批管理
            ['module' => 'approvals', 'action' => 'view', 'name' => '查看', 'sort_order' => 90],
            ['module' => 'approvals', 'action' => 'approve', 'name' => '审批', 'sort_order' => 91],
            
            // 付款申请
            ['module' => 'payment_applications', 'action' => 'view', 'name' => '查看', 'sort_order' => 100],
            ['module' => 'payment_applications', 'action' => 'create', 'name' => '新增', 'sort_order' => 101],
            ['module' => 'payment_applications', 'action' => 'delete', 'name' => '删除', 'sort_order' => 102],
            
            // 发票申请
            ['module' => 'invoice_applications', 'action' => 'view', 'name' => '查看', 'sort_order' => 110],
            ['module' => 'invoice_applications', 'action' => 'create', 'name' => '新增', 'sort_order' => 111],
            ['module' => 'invoice_applications', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 112],
            ['module' => 'invoice_applications', 'action' => 'delete', 'name' => '删除', 'sort_order' => 113],
            
            // 报销管理
            ['module' => 'reimbursement', 'action' => 'view', 'name' => '查看', 'sort_order' => 120],
            ['module' => 'reimbursement', 'action' => 'create', 'name' => '新增', 'sort_order' => 121],
            ['module' => 'reimbursement', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 122],
            ['module' => 'reimbursement', 'action' => 'delete', 'name' => '删除', 'sort_order' => 123],
            
            // 差旅申请
            ['module' => 'travel', 'action' => 'view', 'name' => '查看', 'sort_order' => 130],
            ['module' => 'travel', 'action' => 'create', 'name' => '新增', 'sort_order' => 131],
            ['module' => 'travel', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 132],
            ['module' => 'travel', 'action' => 'delete', 'name' => '删除', 'sort_order' => 133],
            
            // 招聘管理
            ['module' => 'recruitment', 'action' => 'view', 'name' => '查看', 'sort_order' => 140],
            ['module' => 'recruitment', 'action' => 'create', 'name' => '新增', 'sort_order' => 141],
            ['module' => 'recruitment', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 142],
            ['module' => 'recruitment', 'action' => 'delete', 'name' => '删除', 'sort_order' => 143],
            
            // 共享文件
            ['module' => 'shared_files', 'action' => 'view', 'name' => '查看', 'sort_order' => 150],
            ['module' => 'shared_files', 'action' => 'create', 'name' => '上传', 'sort_order' => 151],
            ['module' => 'shared_files', 'action' => 'delete', 'name' => '删除', 'sort_order' => 152],

            // 资料/物品中心（公章、营业执照等）
            ['module' => 'material_assets', 'action' => 'view', 'name' => '查看', 'sort_order' => 160],
            ['module' => 'material_assets', 'action' => 'create', 'name' => '新增', 'sort_order' => 161],
            ['module' => 'material_assets', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 162],
            ['module' => 'material_assets', 'action' => 'delete', 'name' => '删除', 'sort_order' => 163],

            // 资料/物品申请
            ['module' => 'material_requests', 'action' => 'view', 'name' => '查看', 'sort_order' => 170],
            ['module' => 'material_requests', 'action' => 'create', 'name' => '发起申请', 'sort_order' => 171],
            ['module' => 'material_requests', 'action' => 'return', 'name' => '归还归档', 'sort_order' => 172],

            // 工资汇总
            ['module' => 'salary_summaries', 'action' => 'view', 'name' => '查看', 'sort_order' => 180],
            ['module' => 'salary_summaries', 'action' => 'export', 'name' => '导出', 'sort_order' => 181],

            // 发工资表
            ['module' => 'salary_payment', 'action' => 'view', 'name' => '查看', 'sort_order' => 190],
            ['module' => 'salary_payment', 'action' => 'export', 'name' => '导出', 'sort_order' => 191],
            ['module' => 'salary_payment', 'action' => 'delete', 'name' => '删除', 'sort_order' => 192],

            // 专项扣除管理
            ['module' => 'special_deductions', 'action' => 'view', 'name' => '查看', 'sort_order' => 200],
            ['module' => 'special_deductions', 'action' => 'create', 'name' => '新增', 'sort_order' => 201],
            ['module' => 'special_deductions', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 202],
            ['module' => 'special_deductions', 'action' => 'delete', 'name' => '删除', 'sort_order' => 203],

            // 出款汇总
            ['module' => 'payment_summaries', 'action' => 'view', 'name' => '查看', 'sort_order' => 210],
            ['module' => 'payment_summaries', 'action' => 'export', 'name' => '导出', 'sort_order' => 211],

            // 发票汇总
            ['module' => 'invoice_summaries', 'action' => 'view', 'name' => '查看', 'sort_order' => 220],
            ['module' => 'invoice_summaries', 'action' => 'edit', 'name' => '编辑', 'sort_order' => 221],
            ['module' => 'invoice_summaries', 'action' => 'export', 'name' => '导出', 'sort_order' => 222],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['module' => $permission['module'], 'action' => $permission['action']],
                $permission
            );
        }
    }
}
