<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BidDocument extends Model
{
    protected $table = 'bid_documents';

    protected $fillable = [
        'bid_project_id',
        'document_type',
        'document_name',
        'file_path',
        'file_size',
        'file_type',
        'upload_by',
        'upload_at',
        'version',
        'remarks',
    ];

    protected $casts = [
        'upload_at' => 'datetime',
        'file_size' => 'integer',
    ];

    // 文件类型常量
    const TYPE_BID_INVITATION = 'bid_invitation';       // 招标文件
    const TYPE_BID_DOCUMENT = 'bid_document';           // 投标文件
    const TYPE_TECHNICAL_PROPOSAL = 'technical_proposal'; // 技术方案
    const TYPE_QUOTATION = 'quotation';                 // 报价单
    const TYPE_QUALIFICATION = 'qualification';         // 资质证明
    const TYPE_BOND_RECEIPT = 'bond_receipt';           // 保证金凭证
    const TYPE_CONTRACT = 'contract';                   // 合同文件
    const TYPE_OTHER = 'other';                         // 其他

    /**
     * 获取文件类型文本
     */
    public static function getTypeText($type)
    {
        $typeMap = [
            self::TYPE_BID_INVITATION => '招标文件',
            self::TYPE_BID_DOCUMENT => '投标文件',
            self::TYPE_TECHNICAL_PROPOSAL => '技术方案',
            self::TYPE_QUOTATION => '报价单',
            self::TYPE_QUALIFICATION => '资质证明',
            self::TYPE_BOND_RECEIPT => '保证金凭证',
            self::TYPE_CONTRACT => '合同文件',
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
     * 关联：上传人
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'upload_by');
    }

    /**
     * 格式化文件大小
     */
    public function getFormattedFileSizeAttribute()
    {
        if (!$this->file_size) {
            return '';
        }

        $bytes = $this->file_size;
        if ($bytes < 1024) {
            return $bytes . ' B';
        } elseif ($bytes < 1048576) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return round($bytes / 1048576, 2) . ' MB';
        }
    }
}

