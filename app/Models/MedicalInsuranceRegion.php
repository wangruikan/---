<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MedicalInsuranceRegion extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '医保地区';

    protected $auditableFields = [
        'name' => '地区名称',
        'code' => '医保编号',
        'company' => '单位',
        'min_base_amount' => '最低基数',
        'max_base_amount' => '最高基数',
    ];

    public function getAuditIdentifier()
    {
        return $this->name;
    }

    protected $fillable = [
        'name',
        'code',
        'company',
        'min_base_amount',
        'max_base_amount',
        'account_set_id',
        'created_by'
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
        'min_base_amount' => 'decimal:2',
        'max_base_amount' => 'decimal:2',
    ];

    /**
     * 获取该地区下的所有医保类型
     */
    public function medicalInsuranceTypes()
    {
        return $this->hasMany(MedicalInsuranceType::class, 'region_id');
    }

    /**
     * 获取所属账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class, 'account_set_id');
    }

    /**
     * 获取创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 获取关联的项目
     */
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_medical_insurance', 'region_id', 'project_id')
            ->withTimestamps();
    }
}

