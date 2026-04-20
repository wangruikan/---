<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollRemark extends Model
{
    use HasFactory;

    protected $table = 'payroll_remarks';

    protected $fillable = [
        'account_set_id',
        'project_name',
        'year',
        'month',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
    ];

    /**
     * 关联创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * 关联更新人
     */
    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * 获取期间文本
     */
    public function getPeriodTextAttribute()
    {
        return $this->year . '-' . str_pad($this->month, 2, '0', STR_PAD_LEFT);
    }
}

