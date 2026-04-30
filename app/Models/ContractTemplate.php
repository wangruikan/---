<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'project_id',
        'contract_type',
        'shared_file_id',
        'is_default',
        'created_by',
        'placeholder_positions',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 合同类型选项
     */
    public static function getContractTypes()
    {
        return [
            'labor' => '劳动合同',
            'termination' => '解除协议合同',
            'retirement' => '退休解除协议合同',
        ];
    }

    /**
     * 获取合同类型名称
     */
    public function getContractTypeNameAttribute()
    {
        return self::getContractTypes()[$this->contract_type] ?? $this->contract_type;
    }

    /**
     * 获取占位符位置（JSON解码）
     */
    public function getPlaceholderPositionsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    /**
     * 设置占位符位置（JSON编码）
     */
    public function setPlaceholderPositionsAttribute($value)
    {
        $this->attributes['placeholder_positions'] = $value ? json_encode($value) : null;
    }

    /**
     * 关联项目
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * 关联共享文件（包含已软删除的文件）
     */
    public function sharedFile(): BelongsTo
    {
        return $this->belongsTo(SharedFile::class)->withTrashed();
    }

    /**
     * 关联创建人
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}