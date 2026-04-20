<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeRegistrationForm;

class RegistrationFormPdfService
{
    /**
     * 生成单个员工的从业人员登记表PDF
     */
    public function generatePdf($employeeId)
    {
        $employee = Employee::findOrFail($employeeId);
        $form = EmployeeRegistrationForm::where('employee_id', $employeeId)->first();
        
        if (!$form) {
            throw new \Exception('该员工未填写从业人员登记表');
        }
        
        return $this->renderPdf($form);
    }
    
    /**
     * 生成测试PDF（使用示例数据）
     */
    public function generateTestPdf()
    {
        // 创建示例数据对象
        $form = new \stdClass();
        $form->name = '张三';
        $form->english_name = 'Zhang San';
        $form->gender = 'male';
        $form->height = '175';
        $form->birth_date = new \DateTime('1990-05-15');
        $form->political_status = '群众';
        $form->education_level = '本科';
        $form->native_place = '北京';
        $form->marital_status = 'single';
        $form->has_children = '无';
        $form->id_number = '110101199005150015';
        $form->household_type = 'urban';
        $form->current_address = '北京市朝阳区xxx街道xxx号';
        $form->postal_code = '100000';
        $form->household_address = '北京市东城区xxx街道xxx号';
        $form->contact_phone = '13800138000';
        $form->document_address = '北京市朝阳区xxx街道xxx号';
        $form->disability_level = '无';
        
        $form->department = '技术部';
        $form->job_title = '软件工程师';
        $form->fill_date = new \DateTime();
        $form->entry_position = '高级工程师';
        $form->entry_date = new \DateTime('2024-01-01');
        $form->housing_fund_account = '1234567890';
        $form->bank_account = '6222021234567890123';
        $form->bank_name = '中国工商银行北京分行';
        
        $form->language_skills = ['四级', '六级'];
        $form->engineering_skills = ['电工证'];
        $form->professional_title = '中级';
        $form->hobbies = ['唱歌', '球类'];
        $form->other_skills = '熟练使用Office办公软件，具备良好的沟通能力';
        
        $form->education_history = [
            ['date_range' => '2008.09-2012.06', 'school_major' => '北京大学 计算机科学与技术', 'certificate' => '学士学位'],
            ['date_range' => '2012.09-2015.06', 'school_major' => '清华大学 软件工程', 'certificate' => '硕士学位'],
        ];
        
        $form->work_history = [
            ['date_range' => '2015.07-2018.06', 'company' => 'ABC科技有限公司', 'position' => '软件工程师', 'salary' => '15000', 'leave_reason' => '个人发展'],
            ['date_range' => '2018.07-2023.12', 'company' => 'XYZ互联网公司', 'position' => '高级工程师', 'salary' => '25000', 'leave_reason' => '寻求新机会'],
        ];
        $form->reference_company = 'XYZ互联网公司';
        $form->reference_contact = '李经理/13900139000';
        
        $form->rewards_punishments = '2020年获得公司优秀员工称号；2022年获得技术创新奖';
        
        $form->family_members = [
            ['name' => '张父', 'relation' => '父亲', 'age' => '55', 'employer' => '某国企', 'phone' => '13800138001'],
            ['name' => '张母', 'relation' => '母亲', 'age' => '53', 'employer' => '退休', 'phone' => '13800138002'],
        ];
        
        $form->emergency_contact1_name = '张父';
        $form->emergency_contact1_relation = '父亲';
        $form->emergency_contact1_phone = '13800138001';
        $form->emergency_contact2_name = '李四';
        $form->emergency_contact2_relation = '朋友';
        $form->emergency_contact2_phone = '13800138003';
        
        $form->mental_illness = '无';
        $form->mental_illness_detail = '';
        $form->other_illness = '无';
        $form->other_illness_detail = '';
        $form->hospitalized_recently = '无';
        $form->hospitalized_reason = '';
        $form->criminal_record = '无';
        $form->criminal_record_time = '';
        $form->employment_documents = ['离职证明'];
        
        $form->remarks = '本人对职业发展有明确规划，希望在技术领域深耕，期望能够参与更多核心项目的开发工作。';
        
        $form->is_pregnant = '无';
        $form->pregnant_detail = '';
        $form->accept_overtime = '接受';
        $form->need_accommodation = '无';
        $form->accommodation_detail = '';
        $form->has_driving_license = '有';
        $form->driving_license_detail = 'C1';
        
        $form->signature = null;
        $form->signature_date = new \DateTime();
        
        return $this->renderPdf($form, true);
    }
    
    /**
     * 渲染PDF
     */
    private function renderPdf($form, $isTest = false)
    {
        // 准备PDF内容
        $html = view('pdf.registration_form', [
            'form' => $form,
            'isTest' => $isTest
        ])->render();
        
        // 使用mpdf生成PDF（支持中文）
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
            'margin_top' => 10,
            'margin_bottom' => 10,
            'margin_left' => 15,
            'margin_right' => 15,
        ]);
        
        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        
        $mpdf->WriteHTML($html);
        
        // 记录位置信息用于动态计算签名位置
        $signatureTextY = $mpdf->y;
        $pageHeight = $mpdf->h;
        $fromBottom = $pageHeight - $signatureTextY;
        $signatureYFromBottom = $fromBottom * 2.83;
        
        $name = is_object($form) && isset($form->name) ? $form->name : '测试';
        
        return [
            'pdf_content' => $mpdf->Output('', 'S'),
            'filename' => $name . '_从业人员登记表.pdf',
            'signature_position' => [
                'x' => 115,
                'y' => round($signatureYFromBottom),
                'width' => 50,
                'height' => 20,
                'from_bottom' => true,
                'calculated_from_text' => true,
            ]
        ];
    }
    
    /**
     * 批量生成多个员工的从业人员登记表PDF，打包成ZIP
     */
    public function generateMultiplePdfs(array $employeeIds)
    {
        $zip = new \ZipArchive();
        $zipFileName = '从业人员登记表_' . date('YmdHis') . '.zip';
        $zipPath = storage_path('app/temp/' . $zipFileName);
        
        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('无法创建ZIP文件');
        }
        
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($employeeIds as $employeeId) {
            try {
                $pdfData = $this->generatePdf($employeeId);
                $zip->addFromString($pdfData['filename'], $pdfData['pdf_content']);
                $successCount++;
            } catch (\Exception $e) {
                \Log::error("生成从业人员登记表PDF失败: 员工ID {$employeeId}", [
                    'error' => $e->getMessage()
                ]);
                $errorCount++;
            }
        }
        
        $zip->close();
        
        if ($successCount === 0) {
            throw new \Exception('所有员工的PDF生成均失败');
        }
        
        return [
            'path' => $zipPath,
            'success_count' => $successCount,
            'error_count' => $errorCount
        ];
    }
}
