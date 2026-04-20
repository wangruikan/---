<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeSalary extends Model
{
    use HasFactory;

    protected $fillable = [
        'salary_sheet_id',
        'employee_id',
        'basic_salary',
        'overtime_pay',
        'bonus',
        'deductions',
        'net_salary',
        'notes',
    ];

    protected $casts = [
        'basic_salary' => 'decimal:2',
        'overtime_pay' => 'decimal:2',
        'bonus' => 'decimal:2',
        'deductions' => 'decimal:2',
        'net_salary' => 'decimal:2',
    ];

    /**
     * 关联工资表
     */
    public function salarySheet()
    {
        return $this->belongsTo(SalarySheet::class);
    }

    /**
     * 关联员工
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * 自动计算实发工资
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            $model->net_salary = $model->basic_salary + $model->overtime_pay + $model->bonus - $model->deductions;
        });
    }
}
