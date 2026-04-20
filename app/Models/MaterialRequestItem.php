<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class MaterialRequestItem extends Model
{
    use Auditable;

    protected $auditName = '资料申请项';
    
    protected $auditableFields = [
        'status' => '状态',
        'returned_at' => '归还时间'
    ];

    protected $table = 'material_request_items';

    protected $fillable = [
        'material_request_id',
        'material_asset_id',
        'status',
        'returned_at',
    ];

    protected $casts = [
        'returned_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->material ? $this->material->name : "ID:{$this->id}";
    }

    public function request()
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

    public function material()
    {
        return $this->belongsTo(MaterialAsset::class, 'material_asset_id');
    }
}

