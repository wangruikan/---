<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '通知';
    
    protected $auditableFields = [
        'type' => '类型',
        'title' => '标题',
        'content' => '内容',
        'is_read' => '已读状态'
    ];

    protected $fillable = [
        'user_id',
        'type',
        'title',
        'content',
        'is_read',
        'data',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    /**
     * 获取审计标识符
     */
    public function getAuditIdentifier()
    {
        return $this->title ?: "ID:{$this->id}";
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsRead()
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public static function createForUser($userId, $type, $title, $content, $data = null)
    {
        return static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'data' => $data,
        ]);
    }

    public static function createForAllUsers($type, $title, $content, $data = null)
    {
        $userIds = User::pluck('id');
        
        foreach ($userIds as $userId) {
            static::createForUser($userId, $type, $title, $content, $data);
        }
    }
}
