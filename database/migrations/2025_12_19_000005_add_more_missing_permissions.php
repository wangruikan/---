<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            // ========== 仪表盘 ==========
            ['module' => 'dashboard', 'action' => 'view', 'name' => '仪表盘-查看', 'sort_order' => 10],
            
            // ========== 依据管理 ==========
            ['module' => 'basis_records', 'action' => 'view', 'name' => '依据管理-查看', 'sort_order' => 415],
            ['module' => 'basis_records', 'action' => 'create', 'name' => '依据管理-创建', 'sort_order' => 416],
            ['module' => 'basis_records', 'action' => 'update', 'name' => '依据管理-编辑', 'sort_order' => 417],
            ['module' => 'basis_records', 'action' => 'delete', 'name' => '依据管理-删除', 'sort_order' => 418],
            
            // ========== 工资审批 ==========
            ['module' => 'salary_approvals', 'action' => 'view', 'name' => '工资审批-查看', 'sort_order' => 540],
            ['module' => 'salary_approvals', 'action' => 'submit', 'name' => '工资审批-提交', 'sort_order' => 541],
            ['module' => 'salary_approvals', 'action' => 'approve', 'name' => '工资审批-审批', 'sort_order' => 542],
            
            // ========== 工资付款申请 ==========
            ['module' => 'salary_payment_requests', 'action' => 'view', 'name' => '工资付款申请-查看', 'sort_order' => 545],
            ['module' => 'salary_payment_requests', 'action' => 'submit', 'name' => '工资付款申请-提交', 'sort_order' => 546],
            
            // ========== 保险付款申请 ==========
            ['module' => 'insurance_payment_requests', 'action' => 'submit', 'name' => '保险付款申请-提交', 'sort_order' => 675],
            
            // ========== 报销付款申请 ==========
            ['module' => 'reimbursement_payment_requests', 'action' => 'view', 'name' => '报销付款申请-查看', 'sort_order' => 840],
            ['module' => 'reimbursement_payment_requests', 'action' => 'submit', 'name' => '报销付款申请-提交', 'sort_order' => 841],
            
            // ========== 公积金地区 ==========
            ['module' => 'housing_fund_regions', 'action' => 'view', 'name' => '公积金地区-查看', 'sort_order' => 634],
            ['module' => 'housing_fund_regions', 'action' => 'create', 'name' => '公积金地区-创建', 'sort_order' => 635],
            ['module' => 'housing_fund_regions', 'action' => 'update', 'name' => '公积金地区-编辑', 'sort_order' => 636],
            ['module' => 'housing_fund_regions', 'action' => 'delete', 'name' => '公积金地区-删除', 'sort_order' => 637],
            
            // ========== 公积金配置 ==========
            ['module' => 'housing_fund_configs', 'action' => 'view', 'name' => '公积金配置-查看', 'sort_order' => 638],
            ['module' => 'housing_fund_configs', 'action' => 'create', 'name' => '公积金配置-创建', 'sort_order' => 639],
            ['module' => 'housing_fund_configs', 'action' => 'update', 'name' => '公积金配置-编辑', 'sort_order' => 640],
            ['module' => 'housing_fund_configs', 'action' => 'delete', 'name' => '公积金配置-删除', 'sort_order' => 641],
            
            // ========== 印章管理 ==========
            ['module' => 'seals', 'action' => 'view', 'name' => '印章管理-查看', 'sort_order' => 983],
            ['module' => 'seals', 'action' => 'create', 'name' => '印章管理-创建', 'sort_order' => 984],
            ['module' => 'seals', 'action' => 'delete', 'name' => '印章管理-删除', 'sort_order' => 985],
            
            // ========== 合同模板 ==========
            ['module' => 'contract_templates', 'action' => 'view', 'name' => '合同模板-查看', 'sort_order' => 320],
            ['module' => 'contract_templates', 'action' => 'create', 'name' => '合同模板-创建', 'sort_order' => 321],
            ['module' => 'contract_templates', 'action' => 'update', 'name' => '合同模板-编辑', 'sort_order' => 322],
            ['module' => 'contract_templates', 'action' => 'delete', 'name' => '合同模板-删除', 'sort_order' => 323],
            
            // ========== 员工合同 ==========
            ['module' => 'employee_contracts', 'action' => 'view', 'name' => '员工合同-查看', 'sort_order' => 215],
            ['module' => 'employee_contracts', 'action' => 'create', 'name' => '员工合同-创建', 'sort_order' => 216],
            ['module' => 'employee_contracts', 'action' => 'delete', 'name' => '员工合同-删除', 'sort_order' => 217],
            ['module' => 'employee_contracts', 'action' => 'sign', 'name' => '员工合同-签署', 'sort_order' => 218],
            
            // ========== 员工资料 ==========
            ['module' => 'employee_documents', 'action' => 'view', 'name' => '员工资料-查看', 'sort_order' => 220],
            ['module' => 'employee_documents', 'action' => 'upload', 'name' => '员工资料-上传', 'sort_order' => 221],
            ['module' => 'employee_documents', 'action' => 'delete', 'name' => '员工资料-删除', 'sort_order' => 222],
            
            // ========== 项目资料配置 ==========
            ['module' => 'project_document_configs', 'action' => 'view', 'name' => '项目资料配置-查看', 'sort_order' => 325],
            ['module' => 'project_document_configs', 'action' => 'create', 'name' => '项目资料配置-创建', 'sort_order' => 326],
            ['module' => 'project_document_configs', 'action' => 'update', 'name' => '项目资料配置-编辑', 'sort_order' => 327],
            ['module' => 'project_document_configs', 'action' => 'delete', 'name' => '项目资料配置-删除', 'sort_order' => 328],
        ];
        
        foreach ($permissions as $permission) {
            // 检查是否已存在
            $exists = DB::table('permissions')
                ->where('module', $permission['module'])
                ->where('action', $permission['action'])
                ->exists();
            
            if (!$exists) {
                DB::table('permissions')->insert([
                    'module' => $permission['module'],
                    'action' => $permission['action'],
                    'name' => $permission['name'],
                    'sort_order' => $permission['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $modules = [
            'dashboard', 'basis_records', 'salary_approvals', 'salary_payment_requests',
            'insurance_payment_requests', 'reimbursement_payment_requests', 
            'housing_fund_regions', 'housing_fund_configs', 'seals',
            'contract_templates', 'employee_contracts', 'employee_documents',
            'project_document_configs'
        ];
        DB::table('permissions')->whereIn('module', $modules)->delete();
    }
};
