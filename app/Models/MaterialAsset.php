<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialAsset extends Model
{
    protected $table = 'material_assets';

    protected $fillable = [
        'account_set_id',
        'name',
        'description',
        'status',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function requestItems()
    {
        return $this->hasMany(MaterialRequestItem::class, 'material_asset_id');
    }

    public function files()
    {
        return $this->hasMany(MaterialAssetFile::class, 'material_asset_id')->orderBy('id', 'desc');
    }
}

