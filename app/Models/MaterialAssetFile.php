<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MaterialAssetFile extends Model
{
    protected $table = 'material_asset_files';

    protected $fillable = [
        'material_asset_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
        'uploaded_by',
    ];

    protected $casts = [
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function asset()
    {
        return $this->belongsTo(MaterialAsset::class, 'material_asset_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

