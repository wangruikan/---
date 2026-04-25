<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentAppeal extends Model
{
    protected $table = 'assessment_appeals';

    protected $fillable = [
        'assessment_record_id',
        'account_set_id',
        'appellant_id',
        'appellant_name',
        'description',
        'images',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_remark'
    ];

    protected $casts = [
        'images' => 'array',
        'reviewed_at' => 'datetime'
    ];

    protected $appends = [
        'status_text',
        'image_urls'
    ];

    public function assessmentRecord()
    {
        return $this->belongsTo(AssessmentRecord::class, 'assessment_record_id');
    }

    public function getStatusTextAttribute()
    {
        $statusMap = [
            'pending' => '待处理',
            'approved' => '已通过',
            'rejected' => '已驳回'
        ];

        return $statusMap[$this->status] ?? '未知';
    }

    public function getImageUrlsAttribute()
    {
        $images = $this->images ?: [];

        return array_map(function ($path) {
            return '/storage/' . ltrim($path, '/');
        }, $images);
    }
}
