<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentPayee extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '收款信息配置';

    protected $auditableFields = [
        'payee_name' => '支付对象',
        'bank_name' => '开户行',
        'bank_account' => '账号',
    ];

    protected $fillable = [
        'account_set_id',
        'payee_name',
        'bank_name',
        'bank_account',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'account_set_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function getAuditIdentifier()
    {
        return $this->payee_name;
    }

    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
