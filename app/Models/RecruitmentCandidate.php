<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentCandidate extends Model
{
    protected $table = 'recruitment_candidates';

    protected $fillable = [
        'recruitment_id',
        'account_set_id',
        'name',
        'gender',
        'age',
        'phone',
        'email',
        'education',
        'experience',
        'status',
        'interview_date',
        'interview_result',
        'resume_url',
        'notes',
        'created_by'
    ];

    protected $casts = [
        'interview_date' => 'datetime',
        'age' => 'integer'
    ];

    // 关联：招聘需求
    public function recruitment()
    {
        return $this->belongsTo(Recruitment::class, 'recruitment_id');
    }

    // 性别文本
    public function getGenderTextAttribute()
    {
        return $this->gender === 'male' ? '男' : '女';
    }

    // 学历文本
    public function getEducationTextAttribute()
    {
        $educationMap = [
            'high_school' => '高中及以下',
            'college' => '中专/大专',
            'bachelor' => '本科',
            'master' => '硕士',
            'doctor' => '博士'
        ];
        return $educationMap[$this->education] ?? '';
    }

    // 状态文本
    public function getStatusTextAttribute()
    {
        $statusMap = [
            'pending' => '待面试',
            'interviewing' => '面试中',
            'to_be_hired' => '待录用',
            'hired' => '已录用',
            'rejected' => '已拒绝'
        ];
        return $statusMap[$this->status] ?? '未知';
    }
}
