<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InsuranceSurrenderRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'policy_id',
        'employee_id',
        'project_id',
        'insurance_change_id',
        'status', // pending_business | business_done | finance_done
        'surrender_amount',
        'initiated_by',
        'business_submitted_at',
        'finance_submitted_at',
        'remarks',
    ];

    protected $casts = [
        'surrender_amount' => 'decimal:2',
        'business_submitted_at' => 'datetime',
        'finance_submitted_at' => 'datetime',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function policy()
    {
        return $this->belongsTo(OtherInsurancePolicy::class, 'policy_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function insuranceChange()
    {
        return $this->belongsTo(InsuranceChange::class, 'insurance_change_id');
    }

    public function initiator()
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function attachments()
    {
        return $this->hasMany(InsuranceSurrenderAttachment::class, 'surrender_request_id');
    }
}

