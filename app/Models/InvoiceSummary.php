<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InvoiceSummary extends Model
{
    use HasFactory, Auditable;

    /**
     * 审计名称
     */
    protected $auditName = '发票汇总';

    /**
     * 审计字段标签映射
     */
    protected $auditableFields = [
        'period' => '所属期',
        'unit_name' => '单位名称',
        'apply_date' => '申请日期',
        'invoice_method' => '开票方式',
        'invoice_type' => '发票类型',
        'status' => '状态',
        'project_name' => '项目名称',
        'invoice_amount' => '发票金额',
        'deduction_amount' => '扣除额',
        'tax_rate' => '税率',
        'amount_without_tax' => '不含税金额',
        'invoice_tax' => '发票税额',
        'tax_amount' => '税额',
        'invoice_date' => '开票日期',
        'is_completed' => '是否完成',
        'invoicer' => '开票人',
        'invoice_number' => '发票号码',
        'remarks' => '备注',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return "{$this->unit_name} {$this->period}";
    }

    protected $fillable = [
        'invoice_application_id',
        'account_set_id',
        'period',
        'unit_name',
        'apply_date',
        'invoice_method',
        'invoice_type',
        'status',
        'project_name',
        'invoice_amount',
        'deduction_amount',
        'tax_rate',
        'amount_without_tax',
        'invoice_tax',
        'tax_amount',
        'invoice_date',
        'is_completed',
        'invoicer',
        'invoice_number',
        'remarks',
    ];

    protected $casts = [
        'apply_date' => 'datetime:Y-m-d',
        'invoice_date' => 'datetime:Y-m-d',
        'is_completed' => 'boolean',
        'invoice_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'amount_without_tax' => 'decimal:2',
        'invoice_tax' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    // 关联发票申请
    public function invoiceApplication()
    {
        return $this->belongsTo(InvoiceApplication::class);
    }

    /**
     * 从发票申请创建汇总记录
     * 直接使用发票申请中的开票详情字段，不做任何计算
     */
    public static function createFromInvoiceApplication($invoiceApplication)
    {
        // 组合所属期（年-月）
        $period = null;
        if ($invoiceApplication->period_year && $invoiceApplication->period_month) {
            $period = $invoiceApplication->period_year . '-' . str_pad($invoiceApplication->period_month, 2, '0', STR_PAD_LEFT);
        }
        
        return self::create([
            'invoice_application_id' => $invoiceApplication->id,
            'account_set_id' => $invoiceApplication->account_set_id,
            'period' => $period,
            'unit_name' => $invoiceApplication->company_name ?? '',
            'apply_date' => $invoiceApplication->application_date ?? now(),
            'invoice_method' => $invoiceApplication->invoice_method ?? 'none',
            'invoice_type' => $invoiceApplication->invoice_type ?? '',
            'status' => 'pending',
            'project_name' => $invoiceApplication->project_name ?? '',
            'invoice_amount' => $invoiceApplication->invoice_amount ?? 0,
            'deduction_amount' => $invoiceApplication->deduction_amount ?? 0,
            'tax_rate' => $invoiceApplication->tax_rate ?? 0,
            'amount_without_tax' => $invoiceApplication->amount_excluding_tax ?? 0,
            'invoice_tax' => $invoiceApplication->invoice_tax_amount ?? 0,
            'tax_amount' => $invoiceApplication->tax_amount ?? 0,
            'invoice_date' => $invoiceApplication->invoice_date,
            'is_completed' => $invoiceApplication->is_completed ?? false,
            'invoicer' => $invoiceApplication->invoicer,
            'invoice_number' => $invoiceApplication->invoice_number,
            'remarks' => $invoiceApplication->invoice_remark ?? '',
        ]);
    }
}
