<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            // ========== 人员变动申请 ==========
            ['module' => 'personnel_change', 'action' => 'view', 'name' => '人员汇总申请-查看', 'sort_order' => 210],
            ['module' => 'personnel_change', 'action' => 'create', 'name' => '人员汇总申请-创建', 'sort_order' => 211],
            ['module' => 'personnel_change', 'action' => 'update', 'name' => '人员汇总申请-编辑', 'sort_order' => 212],
            ['module' => 'personnel_change', 'action' => 'delete', 'name' => '人员汇总申请-删除', 'sort_order' => 213],
            ['module' => 'personnel_change', 'action' => 'submit', 'name' => '人员汇总申请-提交', 'sort_order' => 214],
            
            // ========== 投标项目 ==========
            ['module' => 'bid_projects', 'action' => 'view', 'name' => '投标项目-查看', 'sort_order' => 310],
            ['module' => 'bid_projects', 'action' => 'create', 'name' => '投标项目-创建', 'sort_order' => 311],
            ['module' => 'bid_projects', 'action' => 'update', 'name' => '投标项目-编辑', 'sort_order' => 312],
            ['module' => 'bid_projects', 'action' => 'delete', 'name' => '投标项目-删除', 'sort_order' => 313],
            
            // ========== 发票项目配置 ==========
            ['module' => 'invoice_projects', 'action' => 'view', 'name' => '发票项目配置-查看', 'sort_order' => 815],
            ['module' => 'invoice_projects', 'action' => 'create', 'name' => '发票项目配置-创建', 'sort_order' => 816],
            ['module' => 'invoice_projects', 'action' => 'update', 'name' => '发票项目配置-编辑', 'sort_order' => 817],
            ['module' => 'invoice_projects', 'action' => 'delete', 'name' => '发票项目配置-删除', 'sort_order' => 818],
            
            // ========== 付款申请 ==========
            ['module' => 'payment_applications', 'action' => 'view', 'name' => '付款申请-查看', 'sort_order' => 805],
            ['module' => 'payment_applications', 'action' => 'create', 'name' => '付款申请-创建', 'sort_order' => 806],
            ['module' => 'payment_applications', 'action' => 'update', 'name' => '付款申请-编辑', 'sort_order' => 807],
            ['module' => 'payment_applications', 'action' => 'submit', 'name' => '付款申请-提交', 'sort_order' => 808],
            
            // ========== 差旅申请 ==========
            ['module' => 'travel_application', 'action' => 'view', 'name' => '差旅申请-查看', 'sort_order' => 835],
            ['module' => 'travel_application', 'action' => 'create', 'name' => '差旅申请-创建', 'sort_order' => 836],
            ['module' => 'travel_application', 'action' => 'update', 'name' => '差旅申请-编辑', 'sort_order' => 837],
            ['module' => 'travel_application', 'action' => 'delete', 'name' => '差旅申请-删除', 'sort_order' => 838],
            
            // ========== 考勤依据 ==========
            ['module' => 'attendance_basis', 'action' => 'view', 'name' => '考勤依据-查看', 'sort_order' => 410],
            ['module' => 'attendance_basis', 'action' => 'upload', 'name' => '考勤依据-上传', 'sort_order' => 411],
            ['module' => 'attendance_basis', 'action' => 'delete', 'name' => '考勤依据-删除', 'sort_order' => 412],
            
            // ========== 工资依据 ==========
            ['module' => 'salary_basis', 'action' => 'view', 'name' => '工资依据-查看', 'sort_order' => 510],
            ['module' => 'salary_basis', 'action' => 'upload', 'name' => '工资依据-上传', 'sort_order' => 511],
            ['module' => 'salary_basis', 'action' => 'delete', 'name' => '工资依据-删除', 'sort_order' => 512],
            
            // ========== 工资汇总 ==========
            ['module' => 'salary_summaries', 'action' => 'view', 'name' => '工资汇总-查看', 'sort_order' => 520],
            ['module' => 'salary_summaries', 'action' => 'export', 'name' => '工资汇总-导出', 'sort_order' => 521],
            
            // ========== 发工资表 ==========
            ['module' => 'salary_payment', 'action' => 'view', 'name' => '发工资表-查看', 'sort_order' => 525],
            ['module' => 'salary_payment', 'action' => 'create', 'name' => '发工资表-创建', 'sort_order' => 526],
            ['module' => 'salary_payment', 'action' => 'export', 'name' => '发工资表-导出', 'sort_order' => 527],
            
            // ========== 专项扣除 ==========
            ['module' => 'special_deductions', 'action' => 'view', 'name' => '专项扣除-查看', 'sort_order' => 530],
            ['module' => 'special_deductions', 'action' => 'create', 'name' => '专项扣除-创建', 'sort_order' => 531],
            ['module' => 'special_deductions', 'action' => 'update', 'name' => '专项扣除-编辑', 'sort_order' => 532],
            ['module' => 'special_deductions', 'action' => 'delete', 'name' => '专项扣除-删除', 'sort_order' => 533],
            
            // ========== 备注事项 ==========
            ['module' => 'payroll_remarks', 'action' => 'view', 'name' => '备注事项-查看', 'sort_order' => 535],
            ['module' => 'payroll_remarks', 'action' => 'create', 'name' => '备注事项-创建', 'sort_order' => 536],
            ['module' => 'payroll_remarks', 'action' => 'update', 'name' => '备注事项-编辑', 'sort_order' => 537],
            ['module' => 'payroll_remarks', 'action' => 'delete', 'name' => '备注事项-删除', 'sort_order' => 538],
            
            // ========== 汇总申请(保险) ==========
            ['module' => 'process_management', 'action' => 'view', 'name' => '汇总申请-查看', 'sort_order' => 670],
            ['module' => 'process_management', 'action' => 'create', 'name' => '汇总申请-创建', 'sort_order' => 671],
            ['module' => 'process_management', 'action' => 'submit', 'name' => '汇总申请-提交', 'sort_order' => 672],
            
            // ========== 流程记录 ==========
            ['module' => 'process_records', 'action' => 'view', 'name' => '流程记录-查看', 'sort_order' => 705],
            ['module' => 'process_records', 'action' => 'update', 'name' => '流程记录-编辑', 'sort_order' => 706],
            
            // ========== 地区网页入口 ==========
            ['module' => 'region_portals', 'action' => 'view', 'name' => '地区网页入口-查看', 'sort_order' => 965],
            ['module' => 'region_portals', 'action' => 'create', 'name' => '地区网页入口-创建', 'sort_order' => 966],
            ['module' => 'region_portals', 'action' => 'update', 'name' => '地区网页入口-编辑', 'sort_order' => 967],
            ['module' => 'region_portals', 'action' => 'delete', 'name' => '地区网页入口-删除', 'sort_order' => 968],
            
            // ========== 交付配置 ==========
            ['module' => 'delivery_configs', 'action' => 'view', 'name' => '交付配置-查看', 'sort_order' => 962],
            ['module' => 'delivery_configs', 'action' => 'create', 'name' => '交付配置-创建', 'sort_order' => 963],
            ['module' => 'delivery_configs', 'action' => 'update', 'name' => '交付配置-编辑', 'sort_order' => 964],
            
            // ========== 签名印章 ==========
            ['module' => 'signatures', 'action' => 'view', 'name' => '签名印章-查看', 'sort_order' => 980],
            ['module' => 'signatures', 'action' => 'create', 'name' => '签名印章-创建', 'sort_order' => 981],
            ['module' => 'signatures', 'action' => 'delete', 'name' => '签名印章-删除', 'sort_order' => 982],
            
            // ========== 角色管理 ==========
            ['module' => 'roles', 'action' => 'view', 'name' => '角色管理-查看', 'sort_order' => 115],
            ['module' => 'roles', 'action' => 'update', 'name' => '角色管理-编辑权限', 'sort_order' => 116],
        ];
        
        foreach ($permissions as $permission) {
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

    public function down(): void
    {
        $modules = [
            'personnel_change', 'bid_projects', 'invoice_projects', 'payment_applications',
            'travel_application', 'attendance_basis', 'salary_basis', 'salary_summaries',
            'salary_payment', 'special_deductions', 'payroll_remarks', 'process_management',
            'process_records', 'region_portals', 'delivery_configs', 'signatures', 'roles'
        ];
        DB::table('permissions')->whereIn('module', $modules)->delete();
    }
};
