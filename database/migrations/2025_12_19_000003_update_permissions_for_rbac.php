<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 添加 route 字段到 permissions 表，用于存储接口路由（如果不存在）
        if (!Schema::hasColumn('permissions', 'route')) {
            Schema::table('permissions', function (Blueprint $table) {
                $table->string('route', 200)->nullable()->after('action')->comment('API路由');
                $table->string('method', 10)->nullable()->after('route')->comment('HTTP方法');
            });
        }
        
        // 清空现有权限数据（需要先禁用外键检查）
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('user_permissions')->truncate();
        DB::table('role_permissions')->truncate();
        DB::table('permissions')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        
        // 插入细化到接口的权限
        $this->insertPermissions();
    }

    public function down(): void
    {
        Schema::table('permissions', function (Blueprint $table) {
            $table->dropColumn(['route', 'method']);
        });
    }
    
    private function insertPermissions()
    {
        $permissions = $this->getPermissionsList();
        
        foreach ($permissions as $permission) {
            DB::table('permissions')->insert([
                'module' => $permission['module'],
                'action' => $permission['action'],
                'route' => $permission['route'] ?? null,
                'method' => $permission['method'] ?? null,
                'name' => $permission['name'],
                'description' => $permission['description'] ?? null,
                'sort_order' => $permission['sort_order'] ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function getPermissionsList()
    {
        return [
            // ========== 用户管理 ==========
            ['module' => 'users', 'action' => 'view', 'route' => '/users', 'method' => 'GET', 'name' => '用户管理-查看列表', 'sort_order' => 100],
            ['module' => 'users', 'action' => 'create', 'route' => '/users', 'method' => 'POST', 'name' => '用户管理-创建', 'sort_order' => 101],
            ['module' => 'users', 'action' => 'update', 'route' => '/users/{id}', 'method' => 'PUT', 'name' => '用户管理-编辑', 'sort_order' => 102],
            ['module' => 'users', 'action' => 'delete', 'route' => '/users/{id}', 'method' => 'DELETE', 'name' => '用户管理-删除', 'sort_order' => 103],
            ['module' => 'users', 'action' => 'reset_password', 'route' => '/users/{id}/reset-password', 'method' => 'POST', 'name' => '用户管理-重置密码', 'sort_order' => 104],
            
            // ========== 权限管理 ==========
            ['module' => 'permissions', 'action' => 'view', 'route' => '/permissions', 'method' => 'GET', 'name' => '权限管理-查看', 'sort_order' => 110],
            ['module' => 'permissions', 'action' => 'manage_roles', 'route' => '/permissions/roles', 'method' => 'PUT', 'name' => '权限管理-角色权限配置', 'sort_order' => 111],
            
            // ========== 员工管理 ==========
            ['module' => 'employees', 'action' => 'view', 'route' => '/employees', 'method' => 'GET', 'name' => '人员档案-查看列表', 'sort_order' => 200],
            ['module' => 'employees', 'action' => 'create', 'route' => '/employees', 'method' => 'POST', 'name' => '人员档案-创建', 'sort_order' => 201],
            ['module' => 'employees', 'action' => 'update', 'route' => '/employees/{id}', 'method' => 'PUT', 'name' => '人员档案-编辑', 'sort_order' => 202],
            ['module' => 'employees', 'action' => 'delete', 'route' => '/employees/{id}', 'method' => 'DELETE', 'name' => '人员档案-删除', 'sort_order' => 203],
            ['module' => 'employees', 'action' => 'view_documents', 'route' => '/employees/{id}/documents', 'method' => 'GET', 'name' => '人员档案-查看资料', 'sort_order' => 204],
            ['module' => 'employees', 'action' => 'upload_documents', 'route' => '/employees/{id}/documents/upload', 'method' => 'POST', 'name' => '人员档案-上传资料', 'sort_order' => 205],
            ['module' => 'employees', 'action' => 'export_pdf', 'route' => '/employees/export-*', 'method' => 'POST', 'name' => '人员档案-导出PDF', 'sort_order' => 206],
            ['module' => 'employees', 'action' => 'manage_contracts', 'route' => '/employees/{id}/contracts', 'method' => '*', 'name' => '人员档案-合同管理', 'sort_order' => 207],
            ['module' => 'employees', 'action' => 'enable_large_medical', 'route' => '/employees/{id}/enable-large-medical', 'method' => 'POST', 'name' => '人员档案-开启大额医疗', 'sort_order' => 208],
            
            // ========== 项目管理 ==========
            ['module' => 'projects', 'action' => 'view', 'route' => '/projects', 'method' => 'GET', 'name' => '项目管理-查看列表', 'sort_order' => 300],
            ['module' => 'projects', 'action' => 'create', 'route' => '/projects', 'method' => 'POST', 'name' => '项目管理-创建', 'sort_order' => 301],
            ['module' => 'projects', 'action' => 'update', 'route' => '/projects/{id}', 'method' => 'PUT', 'name' => '项目管理-编辑', 'sort_order' => 302],
            ['module' => 'projects', 'action' => 'delete', 'route' => '/projects/{id}', 'method' => 'DELETE', 'name' => '项目管理-删除', 'sort_order' => 303],
            ['module' => 'projects', 'action' => 'manage_insurance', 'route' => '/projects/{id}/*-regions', 'method' => '*', 'name' => '项目管理-保险配置', 'sort_order' => 304],
            ['module' => 'projects', 'action' => 'manage_templates', 'route' => '/projects/{id}/contract-templates', 'method' => '*', 'name' => '项目管理-合同模板', 'sort_order' => 305],

            // ========== 考勤管理 ==========
            ['module' => 'attendance', 'action' => 'view', 'route' => '/attendance', 'method' => 'GET', 'name' => '考勤管理-查看列表', 'sort_order' => 400],
            ['module' => 'attendance', 'action' => 'create', 'route' => '/attendance', 'method' => 'POST', 'name' => '考勤管理-创建', 'sort_order' => 401],
            ['module' => 'attendance', 'action' => 'update', 'route' => '/attendance/{id}', 'method' => 'PUT', 'name' => '考勤管理-编辑', 'sort_order' => 402],
            ['module' => 'attendance', 'action' => 'delete', 'route' => '/attendance/{id}', 'method' => 'DELETE', 'name' => '考勤管理-删除', 'sort_order' => 403],
            ['module' => 'attendance', 'action' => 'submit', 'route' => '/attendance/{id}/submit', 'method' => 'POST', 'name' => '考勤管理-提交审批', 'sort_order' => 404],
            ['module' => 'attendance', 'action' => 'export', 'route' => '/attendance/{id}/export', 'method' => 'GET', 'name' => '考勤管理-导出', 'sort_order' => 405],
            
            // ========== 工资管理 ==========
            ['module' => 'salaries', 'action' => 'view', 'route' => '/salaries', 'method' => 'GET', 'name' => '工资管理-查看列表', 'sort_order' => 500],
            ['module' => 'salaries', 'action' => 'generate', 'route' => '/salaries/generate', 'method' => 'POST', 'name' => '工资管理-生成工资表', 'sort_order' => 501],
            ['module' => 'salaries', 'action' => 'submit', 'route' => '/salaries/submit', 'method' => 'POST', 'name' => '工资管理-提交审批', 'sort_order' => 502],
            ['module' => 'salaries', 'action' => 'approve', 'route' => '/salaries/approve', 'method' => 'POST', 'name' => '工资管理-审批', 'sort_order' => 503],
            ['module' => 'salaries', 'action' => 'pay', 'route' => '/salaries/pay', 'method' => 'POST', 'name' => '工资管理-发放', 'sort_order' => 504],
            ['module' => 'salaries', 'action' => 'delete', 'route' => '/salaries', 'method' => 'DELETE', 'name' => '工资管理-删除', 'sort_order' => 505],
            ['module' => 'salaries', 'action' => 'import', 'route' => '/salaries/import-*', 'method' => 'POST', 'name' => '工资管理-导入', 'sort_order' => 506],
            ['module' => 'salaries', 'action' => 'payment_request', 'route' => '/salaries/create-payment-request', 'method' => 'POST', 'name' => '工资管理-发起付款', 'sort_order' => 507],
            
            // ========== 保险管理 ==========
            ['module' => 'insurance', 'action' => 'view', 'route' => '/insurance', 'method' => 'GET', 'name' => '保险管理-查看', 'sort_order' => 600],
            ['module' => 'insurance', 'action' => 'create', 'route' => '/insurance', 'method' => 'POST', 'name' => '保险管理-创建', 'sort_order' => 601],
            ['module' => 'insurance', 'action' => 'update', 'route' => '/insurance/{id}', 'method' => 'PUT', 'name' => '保险管理-编辑', 'sort_order' => 602],
            ['module' => 'insurance', 'action' => 'delete', 'route' => '/insurance/{id}', 'method' => 'DELETE', 'name' => '保险管理-删除', 'sort_order' => 603],
            
            // ========== 参保增减管理 ==========
            ['module' => 'insurance_change', 'action' => 'view', 'route' => '/insurance-changes', 'method' => 'GET', 'name' => '参保增减-查看列表', 'sort_order' => 610],
            ['module' => 'insurance_change', 'action' => 'view_details', 'route' => '/insurance-changes/details', 'method' => 'GET', 'name' => '参保增减-查看明细', 'sort_order' => 611],
            ['module' => 'insurance_change', 'action' => 'upload', 'route' => '/insurance-changes/{id}/attachments', 'method' => 'POST', 'name' => '参保增减-上传附件', 'sort_order' => 612],
            ['module' => 'insurance_change', 'action' => 'confirm', 'route' => '/insurance-changes/{id}/confirm', 'method' => 'POST', 'name' => '参保增减-确认处理', 'sort_order' => 613],
            ['module' => 'insurance_change', 'action' => 'toggle_large_medical', 'route' => '/insurance-changes/{id}/toggle-large-medical', 'method' => 'PUT', 'name' => '参保增减-切换大额', 'sort_order' => 614],
            ['module' => 'insurance_change', 'action' => 'export', 'route' => '/insurance-changes/export', 'method' => 'GET', 'name' => '参保增减-导出', 'sort_order' => 615],

            // ========== 社保管理 ==========
            ['module' => 'social_security', 'action' => 'view', 'route' => '/social-security', 'method' => 'GET', 'name' => '社保管理-查看', 'sort_order' => 620],
            ['module' => 'social_security', 'action' => 'create', 'route' => '/social-security', 'method' => 'POST', 'name' => '社保管理-创建', 'sort_order' => 621],
            ['module' => 'social_security', 'action' => 'update', 'route' => '/social-security/{id}', 'method' => 'PUT', 'name' => '社保管理-编辑', 'sort_order' => 622],
            ['module' => 'social_security', 'action' => 'delete', 'route' => '/social-security/{id}', 'method' => 'DELETE', 'name' => '社保管理-删除', 'sort_order' => 623],
            
            // ========== 公积金管理 ==========
            ['module' => 'housing_fund', 'action' => 'view', 'route' => '/housing-fund*', 'method' => 'GET', 'name' => '公积金管理-查看', 'sort_order' => 630],
            ['module' => 'housing_fund', 'action' => 'create', 'route' => '/housing-fund*', 'method' => 'POST', 'name' => '公积金管理-创建', 'sort_order' => 631],
            ['module' => 'housing_fund', 'action' => 'update', 'route' => '/housing-fund*', 'method' => 'PUT', 'name' => '公积金管理-编辑', 'sort_order' => 632],
            ['module' => 'housing_fund', 'action' => 'delete', 'route' => '/housing-fund*', 'method' => 'DELETE', 'name' => '公积金管理-删除', 'sort_order' => 633],
            
            // ========== 医保管理 ==========
            ['module' => 'medical_insurance', 'action' => 'view', 'route' => '/medical-insurance', 'method' => 'GET', 'name' => '医保管理-查看', 'sort_order' => 640],
            ['module' => 'medical_insurance', 'action' => 'create', 'route' => '/medical-insurance', 'method' => 'POST', 'name' => '医保管理-创建', 'sort_order' => 641],
            ['module' => 'medical_insurance', 'action' => 'update', 'route' => '/medical-insurance/{id}', 'method' => 'PUT', 'name' => '医保管理-编辑', 'sort_order' => 642],
            ['module' => 'medical_insurance', 'action' => 'delete', 'route' => '/medical-insurance/{id}', 'method' => 'DELETE', 'name' => '医保管理-删除', 'sort_order' => 643],
            
            // ========== 大额医疗保险管理 ==========
            ['module' => 'large_medical', 'action' => 'view', 'route' => '/large-medical-insurance', 'method' => 'GET', 'name' => '大额医疗-查看', 'sort_order' => 650],
            ['module' => 'large_medical', 'action' => 'create', 'route' => '/large-medical-insurance', 'method' => 'POST', 'name' => '大额医疗-创建', 'sort_order' => 651],
            ['module' => 'large_medical', 'action' => 'update', 'route' => '/large-medical-insurance/{id}', 'method' => 'PUT', 'name' => '大额医疗-编辑', 'sort_order' => 652],
            ['module' => 'large_medical', 'action' => 'delete', 'route' => '/large-medical-insurance/{id}', 'method' => 'DELETE', 'name' => '大额医疗-删除', 'sort_order' => 653],
            
            // ========== 其他保险管理 ==========
            ['module' => 'other_insurance', 'action' => 'view', 'route' => '/other-insurance/*', 'method' => 'GET', 'name' => '其他保险-查看', 'sort_order' => 660],
            ['module' => 'other_insurance', 'action' => 'create', 'route' => '/other-insurance/*', 'method' => 'POST', 'name' => '其他保险-创建', 'sort_order' => 661],
            ['module' => 'other_insurance', 'action' => 'update', 'route' => '/other-insurance/*', 'method' => 'PUT', 'name' => '其他保险-编辑', 'sort_order' => 662],
            ['module' => 'other_insurance', 'action' => 'delete', 'route' => '/other-insurance/*', 'method' => 'DELETE', 'name' => '其他保险-删除', 'sort_order' => 663],

            // ========== 审批管理 ==========
            ['module' => 'approvals', 'action' => 'view', 'route' => '/approvals', 'method' => 'GET', 'name' => '审批管理-查看', 'sort_order' => 700],
            ['module' => 'approvals', 'action' => 'approve', 'route' => '/approvals/records/{id}/approve', 'method' => 'POST', 'name' => '审批管理-审批通过', 'sort_order' => 701],
            ['module' => 'approvals', 'action' => 'reject', 'route' => '/approvals/records/{id}/reject', 'method' => 'POST', 'name' => '审批管理-驳回', 'sort_order' => 702],
            ['module' => 'approvals', 'action' => 'return', 'route' => '/approvals/records/{id}/return', 'method' => 'POST', 'name' => '审批管理-退回', 'sort_order' => 703],
            
            // ========== 付款管理 ==========
            ['module' => 'payments', 'action' => 'view', 'route' => '/payments', 'method' => 'GET', 'name' => '付款管理-查看', 'sort_order' => 800],
            ['module' => 'payments', 'action' => 'create', 'route' => '/payments', 'method' => 'POST', 'name' => '付款管理-创建', 'sort_order' => 801],
            ['module' => 'payments', 'action' => 'update', 'route' => '/payments/{id}', 'method' => 'PUT', 'name' => '付款管理-编辑', 'sort_order' => 802],
            ['module' => 'payments', 'action' => 'delete', 'route' => '/payments/{id}', 'method' => 'DELETE', 'name' => '付款管理-删除', 'sort_order' => 803],
            
            // ========== 发票管理 ==========
            ['module' => 'invoices', 'action' => 'view', 'route' => '/invoices', 'method' => 'GET', 'name' => '发票管理-查看', 'sort_order' => 810],
            ['module' => 'invoices', 'action' => 'create', 'route' => '/invoices', 'method' => 'POST', 'name' => '发票管理-创建', 'sort_order' => 811],
            ['module' => 'invoices', 'action' => 'update', 'route' => '/invoices/{id}', 'method' => 'PUT', 'name' => '发票管理-编辑', 'sort_order' => 812],
            ['module' => 'invoices', 'action' => 'delete', 'route' => '/invoices/{id}', 'method' => 'DELETE', 'name' => '发票管理-删除', 'sort_order' => 813],
            
            // ========== 开票申请 ==========
            ['module' => 'invoice_applications', 'action' => 'view', 'route' => '/invoice-applications', 'method' => 'GET', 'name' => '开票申请-查看', 'sort_order' => 820],
            ['module' => 'invoice_applications', 'action' => 'create', 'route' => '/invoice-applications', 'method' => 'POST', 'name' => '开票申请-创建', 'sort_order' => 821],
            ['module' => 'invoice_applications', 'action' => 'update', 'route' => '/invoice-applications/{id}', 'method' => 'PUT', 'name' => '开票申请-编辑', 'sort_order' => 822],
            ['module' => 'invoice_applications', 'action' => 'delete', 'route' => '/invoice-applications/{id}', 'method' => 'DELETE', 'name' => '开票申请-删除', 'sort_order' => 823],
            ['module' => 'invoice_applications', 'action' => 'submit', 'route' => '/invoice-applications/{id}/submit', 'method' => 'POST', 'name' => '开票申请-提交', 'sort_order' => 824],
            
            // ========== 报销管理 ==========
            ['module' => 'reimbursements', 'action' => 'view', 'route' => '/reimbursements', 'method' => 'GET', 'name' => '报销管理-查看', 'sort_order' => 830],
            ['module' => 'reimbursements', 'action' => 'create', 'route' => '/reimbursements', 'method' => 'POST', 'name' => '报销管理-创建', 'sort_order' => 831],
            ['module' => 'reimbursements', 'action' => 'update', 'route' => '/reimbursements/{id}', 'method' => 'PUT', 'name' => '报销管理-编辑', 'sort_order' => 832],
            ['module' => 'reimbursements', 'action' => 'delete', 'route' => '/reimbursements/{id}', 'method' => 'DELETE', 'name' => '报销管理-删除', 'sort_order' => 833],
            ['module' => 'reimbursements', 'action' => 'submit', 'route' => '/reimbursements/{id}/submit', 'method' => 'POST', 'name' => '报销管理-提交', 'sort_order' => 834],

            // ========== 招聘管理 ==========
            ['module' => 'recruitment', 'action' => 'view', 'route' => '/recruitment', 'method' => 'GET', 'name' => '招聘管理-查看', 'sort_order' => 900],
            ['module' => 'recruitment', 'action' => 'create', 'route' => '/recruitment', 'method' => 'POST', 'name' => '招聘管理-创建', 'sort_order' => 901],
            ['module' => 'recruitment', 'action' => 'update', 'route' => '/recruitment/{id}', 'method' => 'PUT', 'name' => '招聘管理-编辑', 'sort_order' => 902],
            ['module' => 'recruitment', 'action' => 'delete', 'route' => '/recruitment/{id}', 'method' => 'DELETE', 'name' => '招聘管理-删除', 'sort_order' => 903],
            
            // ========== 招聘需求 ==========
            ['module' => 'recruitment_demand', 'action' => 'view', 'route' => '/recruitment-demands', 'method' => 'GET', 'name' => '招聘需求-查看', 'sort_order' => 910],
            ['module' => 'recruitment_demand', 'action' => 'create', 'route' => '/recruitment-demands', 'method' => 'POST', 'name' => '招聘需求-创建', 'sort_order' => 911],
            ['module' => 'recruitment_demand', 'action' => 'update', 'route' => '/recruitment-demands/{id}', 'method' => 'PUT', 'name' => '招聘需求-编辑', 'sort_order' => 912],
            ['module' => 'recruitment_demand', 'action' => 'delete', 'route' => '/recruitment-demands/{id}', 'method' => 'DELETE', 'name' => '招聘需求-删除', 'sort_order' => 913],
            
            // ========== 共享文件 ==========
            ['module' => 'shared_files', 'action' => 'view', 'route' => '/shared-files', 'method' => 'GET', 'name' => '共享文件-查看', 'sort_order' => 920],
            ['module' => 'shared_files', 'action' => 'upload', 'route' => '/shared-files', 'method' => 'POST', 'name' => '共享文件-上传', 'sort_order' => 921],
            ['module' => 'shared_files', 'action' => 'delete', 'route' => '/shared-files/{id}', 'method' => 'DELETE', 'name' => '共享文件-删除', 'sort_order' => 922],
            
            // ========== 账套管理 ==========
            ['module' => 'account_sets', 'action' => 'view', 'route' => '/account-sets', 'method' => 'GET', 'name' => '账套管理-查看', 'sort_order' => 930],
            ['module' => 'account_sets', 'action' => 'create', 'route' => '/account-sets', 'method' => 'POST', 'name' => '账套管理-创建', 'sort_order' => 931],
            ['module' => 'account_sets', 'action' => 'update', 'route' => '/account-sets/{id}', 'method' => 'PUT', 'name' => '账套管理-编辑', 'sort_order' => 932],
            ['module' => 'account_sets', 'action' => 'delete', 'route' => '/account-sets/{id}', 'method' => 'DELETE', 'name' => '账套管理-删除', 'sort_order' => 933],
            
            // ========== 基数调整 ==========
            ['module' => 'base_adjustment', 'action' => 'view', 'route' => '/base-adjustments', 'method' => 'GET', 'name' => '基数调整-查看', 'sort_order' => 940],
            ['module' => 'base_adjustment', 'action' => 'create', 'route' => '/base-adjustments', 'method' => 'POST', 'name' => '基数调整-创建', 'sort_order' => 941],
            ['module' => 'base_adjustment', 'action' => 'update', 'route' => '/base-adjustments/{id}', 'method' => 'PUT', 'name' => '基数调整-编辑', 'sort_order' => 942],
            ['module' => 'base_adjustment', 'action' => 'delete', 'route' => '/base-adjustments/{id}', 'method' => 'DELETE', 'name' => '基数调整-删除', 'sort_order' => 943],
            
            // ========== 出款汇总 ==========
            ['module' => 'payment_summaries', 'action' => 'view', 'route' => '/payment-summaries', 'method' => 'GET', 'name' => '出款汇总-查看', 'sort_order' => 950],
            ['module' => 'payment_summaries', 'action' => 'export', 'route' => '/payment-summaries/export', 'method' => 'GET', 'name' => '出款汇总-导出', 'sort_order' => 951],
            
            // ========== 资料交付 ==========
            ['module' => 'document_delivery', 'action' => 'view', 'route' => '/document-deliveries', 'method' => 'GET', 'name' => '资料交付-查看', 'sort_order' => 960],
            ['module' => 'document_delivery', 'action' => 'create', 'route' => '/document-deliveries', 'method' => 'POST', 'name' => '资料交付-创建', 'sort_order' => 961],
            ['module' => 'document_delivery', 'action' => 'update', 'route' => '/document-deliveries/{id}', 'method' => 'PUT', 'name' => '资料交付-编辑', 'sort_order' => 962],
            
            // ========== 考核管理 ==========
            ['module' => 'assessment', 'action' => 'view', 'route' => '/assessments', 'method' => 'GET', 'name' => '考核管理-查看', 'sort_order' => 970],
            ['module' => 'assessment', 'action' => 'create', 'route' => '/assessments', 'method' => 'POST', 'name' => '考核管理-创建', 'sort_order' => 971],
            ['module' => 'assessment', 'action' => 'update', 'route' => '/assessments/{id}', 'method' => 'PUT', 'name' => '考核管理-编辑', 'sort_order' => 972],
            ['module' => 'assessment', 'action' => 'delete', 'route' => '/assessments/{id}', 'method' => 'DELETE', 'name' => '考核管理-删除', 'sort_order' => 973],
        ];
    }
};
