<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReimbursementAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'reimbursement_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    protected $casts = [
        'file_size' => 'integer',
    ];

    /**
     * 关联报销申请
     */
    public function reimbursement()
    {
        return $this->belongsTo(Reimbursement::class);
    }
}

