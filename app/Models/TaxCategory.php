<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TaxCategory extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '税种类目';

    protected $fillable = [
        'account_set_id',
        'name',
        'parent_id',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $auditableFields = [
        'name' => '税种名称',
        'parent_id' => '所属税种大类',
    ];

    public function getAuditIdentifier()
    {
        return $this->name;
    }

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 所属税种大类。
     */
    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * 大类下的细分税种。
     */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * 关联创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
