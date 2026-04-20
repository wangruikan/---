<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryPaymentRecord extends Model
{
    protected $fillable = [
        'salary_id',
        'employee_id',
        'project_id',
        'month',
        'bank_account',
        'bank_account_holder',
        'amount',
        'bank_name',
        'bank_province',
        'remittance_remark',
        'account_set_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // 关系
    public function salary()
    {
        return $this->belongsTo(Salary::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
