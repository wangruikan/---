<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OnboardingForm extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '入职登记表';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'registration_date' => '登记日期',
        'name' => '姓名',
        'gender' => '性别',
        'ethnicity' => '民族',
        'political_status' => '政治面貌',
        'place_of_origin' => '籍贯',
        'birth_date' => '出生日期',
        'graduated_school' => '毕业学校',
        'graduation_date' => '毕业日期',
        'education_level' => '学历',
        'major' => '专业',
        'degree' => '学位',
        'technical_title' => '技术职称',
        'health_status' => '健康状况',
        'height' => '身高',
        'weight' => '体重',
        'marital_status' => '婚姻状况',
        'id_number' => '身份证号',
        'current_residence' => '现居住地',
        'household_registration' => '户口所在地',
        'position' => '应聘岗位',
        'desired_location' => '期望工作地点',
        'accept_assignment' => '是否接受调配',
        'contact_address' => '联系地址',
        'contact_phone' => '联系电话',
        'remarks' => '备注',
        'signature' => '签名',
        'photo' => '照片',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->name;
    }

    protected $fillable = [
        'employee_id',
        'account_set_id',
        'registration_date',
        'name',
        'gender',
        'ethnicity',
        'political_status',
        'place_of_origin',
        'birth_date',
        'graduated_school',
        'graduation_date',
        'education_level',
        'major',
        'degree',
        'technical_title',
        'health_status',
        'height',
        'weight',
        'marital_status',
        'id_number',
        'current_residence',
        'household_registration',
        'position',
        'desired_location',
        'accept_assignment',
        'contact_address',
        'contact_phone',
        'remarks',
        'signature',  // 签名图片路径
        'photo',      // 一寸照片路径
        'education_background',
        'work_experience',
        'family_info',
    ];

    protected $casts = [
        'registration_date' => 'date',
        'birth_date' => 'date',
        'graduation_date' => 'date',
        'height' => 'integer',
        'weight' => 'decimal:2',
        'accept_assignment' => 'boolean',
        'education_background' => 'array',
        'work_experience' => 'array',
        'family_info' => 'array',
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
        return $this->gender === 'male' ? '男' : '女';
    }

    /**
     * 获取婚姻状况文本
     */
    public function getMaritalStatusTextAttribute()
    {
        $map = [
            'single' => '未婚',
            'married' => '已婚',
            'divorced' => '离异',
            'widowed' => '丧偶',
        ];
        return $map[$this->marital_status] ?? $this->marital_status;
    }
}

