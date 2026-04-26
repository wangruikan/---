<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeRegistrationForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'account_set_id',
        // 头部信息
        'fill_date',
        'entry_position',
        'entry_date',
        'department',
        'job_title',
        'housing_fund_account',
        'bank_account',
        'bank_name',
        // 一、个人资料
        'name',
        'english_name',
        'gender',
        'height',
        'birth_date',
        'political_status',
        'education_level',
        'education_type',
        'native_place',
        'marital_status',
        'has_children',
        'id_number',
        'household_type',
        'current_address',
        'postal_code',
        'household_address',
        'contact_phone',
        'document_address',
        'disability_level',
        // 二、个人技能
        'language_skills',
        'engineering_skills',
        'professional_title',
        'hobbies',
        'other_skills',
        // 三、教育情况
        'education_history',
        // 四、工作履历
        'work_history',
        'reference_company',
        'reference_contact',
        // 五、奖罚情况
        'rewards_punishments',
        // 六、家庭情况
        'family_members',
        // 七、紧急联系方式
        'emergency_contact1_name',
        'emergency_contact1_relation',
        'emergency_contact1_phone',
        'emergency_contact2_name',
        'emergency_contact2_relation',
        'emergency_contact2_phone',
        // 八、其他情况
        'mental_illness',
        'mental_illness_detail',
        'other_illness',
        'other_illness_detail',
        'hospitalized_recently',
        'hospitalized_reason',
        'criminal_record',
        'criminal_record_time',
        'employment_documents',
        // 九、其他需要说明的情况
        'remarks',
        // 十、其他需要核实的情况
        'is_pregnant',
        'pregnant_detail',
        'accept_overtime',
        'need_accommodation',
        'accommodation_detail',
        'has_driving_license',
        'driving_license_detail',
        // 签名
        'signature',
        'signature_date',
    ];

    protected $casts = [
        'fill_date' => 'date',
        'entry_date' => 'date',
        'birth_date' => 'date',
        'signature_date' => 'date',
        'language_skills' => 'array',
        'engineering_skills' => 'array',
        'hobbies' => 'array',
        'education_history' => 'array',
        'work_history' => 'array',
        'family_members' => 'array',
        'employment_documents' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 关联员工
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * 获取性别文本
     */
    public function getGenderTextAttribute()
    {
        return $this->gender === 'male' ? '男' : ($this->gender === 'female' ? '女' : $this->gender);
    }

    /**
     * 获取婚姻状况文本
     */
    public function getMaritalStatusTextAttribute()
    {
        $map = [
            'single' => '未婚',
            'married' => '已婚',
            'divorced' => '离婚',
        ];
        return $map[$this->marital_status] ?? $this->marital_status;
    }

    /**
     * 获取户口状态文本
     */
    public function getHouseholdTypeTextAttribute()
    {
        $map = [
            'urban' => '城镇',
            'rural' => '非城镇',
        ];
        return $map[$this->household_type] ?? $this->household_type;
    }
}
