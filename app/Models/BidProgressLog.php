<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidProgressLog extends Model
{
    protected $table = 'bid_progress_logs';

    protected $fillable = [
        'bid_project_id',
        'log_type',
        'log_title',
        'log_content',
        'old_status',
        'new_status',
        'log_time',
        'operator_id',
        'operator_name',
    ];

    protected $casts = [
        'log_time' => 'datetime',
    ];

    // 日志类型常量
    const TYPE_STATUS_CHANGE = 'status_change';   // 状态变更
    const TYPE_DOCUMENT_UPLOAD = 'document_upload'; // 文档上传
    const TYPE_PAYMENT = 'payment';               // 款项支付
    const TYPE_MEETING = 'meeting';               // 会议记录
    const TYPE_OTHER = 'other';                   // 其他

    /**
     * 获取日志类型文本
     */
    public static function getTypeText($type)
    {
        $typeMap = [
            self::TYPE_STATUS_CHANGE => '状态变更',
            self::TYPE_DOCUMENT_UPLOAD => '文档上传',
            self::TYPE_PAYMENT => '款项支付',
            self::TYPE_MEETING => '会议记录',
            self::TYPE_OTHER => '其他',
        ];
        return $typeMap[$type] ?? $type;
    }

    /**
     * 关联：投标项目
     */
    public function bidProject()
    {
        return $this->belongsTo(BidProject::class, 'bid_project_id');
    }

    /**
     * 关联：操作人
     */
    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}

