<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\DocumentDelivery;
use App\Models\ApprovalInstance;
use App\Models\ApprovalRecord;
use Carbon\Carbon;

class CreateTestDataForAssessment extends Command
{
    protected $signature = 'test:create-assessment-data';
    protected $description = '创建测试数据来验证月末员工资料检查功能';

    public function handle()
    {
        $this->info('开始创建测试数据...');
        
        // 1. 创建测试员工
        $this->info('创建测试员工...');
        
        $employees = [
            [
                'name' => '测试员工张三',
                'hire_date' => '2024-11-15',
                'position' => '软件工程师',
                'employee_number' => 'TEST001',
                'phone' => '13800138001',
                'email' => 'zhangsan@test.com',
                'id_number' => '110101199001011001'
            ],
            [
                'name' => '测试员工李四',
                'hire_date' => '2024-11-20',
                'position' => '产品经理',
                'employee_number' => 'TEST002',
                'phone' => '13800138002',
                'email' => 'lisi@test.com',
                'id_number' => '110101199002022002'
            ],
            [
                'name' => '测试员工王五',
                'hire_date' => '2024-11-25',
                'position' => '设计师',
                'employee_number' => 'TEST003',
                'phone' => '13800138003',
                'email' => 'wangwu@test.com',
                'id_number' => '110101199003033003'
            ]
        ];
        
        $createdEmployees = [];
        foreach ($employees as $empData) {
            // 检查是否已存在
            $existing = Employee::where('employee_number', $empData['employee_number'])->first();
            if ($existing) {
                $this->warn("员工 {$empData['name']} 已存在，跳过创建");
                $createdEmployees[] = $existing;
                continue;
            }
            
            $employee = new Employee();
            $employee->account_set_id = 1;
            $employee->name = $empData['name'];
            $employee->hire_date = $empData['hire_date'];
            $employee->position = $empData['position'];
            $employee->employee_number = $empData['employee_number'];
            $employee->phone = $empData['phone'];
            $employee->email = $empData['email'];
            $employee->id_number = $empData['id_number'];
            $employee->birth_date = '1990-01-01';
            $employee->gender = 'male';
            $employee->nationality = '汉族';
            $employee->marital_status = 'single';
            $employee->education = 'bachelor';
            $employee->address = '测试地址';
            $employee->contract_start_date = $empData['hire_date'];
            $employee->contract_end_date = '2025-11-15';
            $employee->contract_status = 'active';
            $employee->save();
            
            $createdEmployees[] = $employee;
            $this->info("✓ 创建员工: {$employee->name} (入职日期: {$employee->hire_date})");
        }
        
        // 2. 创建资料记录
        $this->info('创建资料记录...');
        
        // 张三：只上传了身份证和简历（缺失4种资料）
        $zhangsan = $createdEmployees[0];
        $this->createDocumentDelivery($zhangsan, 'id_card', '张三身份证');
        $this->createDocumentDelivery($zhangsan, 'resume', '张三简历');
        $this->info("✓ 张三：上传了2种资料，缺失4种");
        
        // 李四：上传了所有资料
        $lisi = $createdEmployees[1];
        $allDocTypes = ['id_card', 'diploma', 'resume', 'photo', 'bank_card', 'contract'];
        foreach ($allDocTypes as $type) {
            $this->createDocumentDelivery($lisi, $type, "李四{$type}");
        }
        $this->info("✓ 李四：上传了全部6种资料");
        
        // 王五：没有上传任何资料
        $this->info("✓ 王五：没有上传任何资料，缺失6种");
        
        // 3. 创建审批记录（确保能找到业务人员）
        $this->info('创建审批记录...');
        
        $instance = ApprovalInstance::create([
            'account_set_id' => 1,
            'business_type' => 'test',
            'business_id' => 1,
            'current_step' => 1,
            'total_steps' => 3,
            'status' => 'pending',
            'created_by' => 1
        ]);
        
        ApprovalRecord::create([
            'instance_id' => $instance->id,
            'step_order' => 1,
            'step_name' => '业务审批',
            'approver_id' => 1,
            'approver_name' => '业务经理',
            'status' => 'pending'
        ]);
        
        $this->info("✓ 创建审批记录，业务人员ID: 1");
        
        // 4. 显示测试数据总结
        $this->info('');
        $this->info('=== 测试数据创建完成 ===');
        $this->table(
            ['员工姓名', '入职日期', '预期结果'],
            [
                ['测试员工张三', '2024-11-15', '生成考核记录（缺失4种资料）'],
                ['测试员工李四', '2024-11-20', '不生成考核记录（资料齐全）'],
                ['测试员工王五', '2024-11-25', '生成考核记录（缺失6种资料）']
            ]
        );
        
        $this->info('');
        $this->info('现在可以运行测试命令：');
        $this->line('php artisan assessment:check-new-employee-documents --month=2024-11');
        
        return 0;
    }
    
    private function createDocumentDelivery($employee, $type, $name)
    {
        // 检查是否已存在
        $existing = DocumentDelivery::where('employee_id', $employee->id)
            ->where('document_type', $type)
            ->first();
            
        if ($existing) {
            return $existing;
        }
        
        return DocumentDelivery::create([
            'account_set_id' => $employee->account_set_id,
            'employee_id' => $employee->id,
            'document_type' => $type,
            'document_name' => $name,
            'status' => 'completed'
        ]);
    }
}
