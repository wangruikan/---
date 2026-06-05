<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserBankStamp extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_set_id',
        'user_id',
        'type',
        'name',
        'company',
        'image_path',
        'original_filename',
        'position_x',
        'position_y',
        'width',
        'height',
    ];

    protected $casts = [
        'position_x' => 'integer',
        'position_y' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
