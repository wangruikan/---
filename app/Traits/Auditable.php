<?php

namespace App\Traits;

use App\Models\OperationLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

trait Auditable
{
    /**
     * 启动审计功能
     */
    public static function bootAuditable()
    {
        // 监听创建事件
        static::created(function ($model) {
            $model->auditCreated();
        });

        // 监听更新事件
        static::updated(function ($model) {
            $model->auditUpdated();
        });

        // 监听删除事件
        static::deleted(function ($model) {
            $model->auditDeleted();
        });
    }

    /**
     * 记录创建操作
     */
    protected function auditCreated()
    {
        $this->createLog('created', $this->getCreatedDescription());
    }

    /**
     * 记录更新操作
     */
    protected function auditUpdated()
    {
        $changes = $this->getChanges();
        
        if (empty($changes)) {
            return;
        }

        // 只记录真正改变的字段（值不同的字段）
        $realChanges = [];
        foreach ($changes as $field => $newValue) {
            if (in_array($field, ['updated_at', 'created_at'])) {
                continue;
            }

            $oldValue = $this->getOriginal($field);
            
            // 对于日期时间字段，需要特殊处理
            if ($this->isDateAttribute($field)) {
                // 标准化日期格式进行比较（只比较日期部分，忽略时间和时区）
                $oldNormalized = $this->normalizeDateValue($oldValue);
                $newNormalized = $this->normalizeDateValue($newValue);
                
                if ($oldNormalized !== $newNormalized) {
                    $realChanges[$field] = $newValue;
                }
            } 
            // 对于数字字段（decimal），需要特殊处理
            elseif ($this->isNumericAttribute($field)) {
                // 转换为浮点数比较
                $oldFloat = $oldValue !== null ? (float)$oldValue : null;
                $newFloat = $newValue !== null ? (float)$newValue : null;
                
                if ($oldFloat != $newFloat) {
                    $realChanges[$field] = $newValue;
                }
            }
            else {
                // 普通字段直接比较
                if ($oldValue != $newValue) {
                    $realChanges[$field] = $newValue;
                }
            }
        }

        // 如果没有真正的改变，不记录
        if (empty($realChanges)) {
            return;
        }

        $description = $this->getUpdatedDescription($realChanges);
        $oldValues = [];
        $newValues = [];

        foreach ($realChanges as $field => $newValue) {
            // 对于日期字段，保存标准化后的值（避免格式差异）
            if ($this->isDateAttribute($field)) {
                $oldValues[$field] = $this->normalizeDateValue($this->getOriginal($field));
                $newValues[$field] = $this->normalizeDateValue($newValue);
            } else {
                $oldValues[$field] = $this->getOriginal($field);
                $newValues[$field] = $newValue;
            }
        }

        $this->createLog('updated', $description, $oldValues, $newValues);
    }

    /**
     * 标准化日期值用于比较
     */
    protected function normalizeDateValue($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            // 尝试解析为 Carbon 对象
            if ($value instanceof \Carbon\Carbon) {
                return $value->format('Y-m-d');
            }
            
            // 字符串日期
            $carbon = \Carbon\Carbon::parse($value);
            return $carbon->format('Y-m-d');
        } catch (\Exception $e) {
            // 如果解析失败，返回原值
            return $value;
        }
    }

    /**
     * 检查字段是否是日期属性
     */
    protected function isDateAttribute($key)
    {
        // 检查是否在 $dates 数组中
        if (property_exists($this, 'dates') && in_array($key, $this->dates)) {
            return true;
        }

        // 检查是否在 $casts 中定义为日期类型
        if (isset($this->casts[$key])) {
            $castType = $this->casts[$key];
            // 支持 'date', 'datetime', 'datetime:Y-m-d H:i:s', 'timestamp' 等格式
            if (is_string($castType) && (
                $castType === 'date' || 
                $castType === 'datetime' || 
                $castType === 'timestamp' ||
                strpos($castType, 'datetime:') === 0
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查字段是否是数字属性
     */
    protected function isNumericAttribute($key)
    {
        // 检查是否在 $casts 中定义为数字类型
        if (isset($this->casts[$key])) {
            $castType = $this->casts[$key];
            // 支持 'decimal:2', 'float', 'double', 'integer' 等格式
            if (is_string($castType) && (
                $castType === 'float' || 
                $castType === 'double' || 
                $castType === 'integer' ||
                $castType === 'int' ||
                strpos($castType, 'decimal:') === 0
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * 记录删除操作
     */
    protected function auditDeleted()
    {
        $this->createLog('deleted', $this->getDeletedDescription());
    }

    /**
     * 创建日志记录
     */
    protected function createLog($action, $description, $oldValues = [], $newValues = [])
    {
        if (!Auth::check()) {
            return;
        }

        $user = Auth::user();
        $accountSetId = null;

        // 尝试获取账套ID
        if (method_exists($this, 'getAccountSetId')) {
            $accountSetId = $this->getAccountSetId();
        } elseif (isset($this->account_set_id)) {
            $accountSetId = $this->account_set_id;
        } elseif (Request::has('account_set_id')) {
            $accountSetId = Request::input('account_set_id');
        }

        OperationLog::create([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'account_set_id' => $accountSetId,
            'model_type' => get_class($this),
            'model_id' => $this->id,
            'action' => $action,
            'description' => $description,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]);
    }

    /**
     * 获取创建操作的描述
     */
    protected function getCreatedDescription()
    {
        $modelName = $this->getModelDisplayName();
        $identifier = $this->getIdentifier();
        
        return "创建了 {$modelName}: {$identifier}";
    }

    /**
     * 获取更新操作的描述
     */
    protected function getUpdatedDescription($changes)
    {
        $modelName = $this->getModelDisplayName();
        $identifier = $this->getIdentifier();
        $fieldLabels = $this->getAuditableFieldLabels();
        
        $changedFields = [];
        foreach ($changes as $field => $newValue) {
            if (in_array($field, ['updated_at', 'created_at'])) {
                continue;
            }

            $fieldLabel = $fieldLabels[$field] ?? $field;
            $changedFields[] = $fieldLabel;
        }

        $changesText = implode('、', $changedFields);
        
        return "修改了 {$modelName} {$identifier} 的 {$changesText}";
    }

    /**
     * 获取删除操作的描述
     */
    protected function getDeletedDescription()
    {
        $modelName = $this->getModelDisplayName();
        $identifier = $this->getIdentifier();
        
        return "删除了 {$modelName}: {$identifier}";
    }

    /**
     * 获取模型显示名称
     */
    protected function getModelDisplayName()
    {
        if (property_exists($this, 'auditName')) {
            return $this->auditName;
        }

        $modelNames = [
            'Employee' => '员工',
            'Project' => '项目',
            'Salary' => '工资',
            'Attendance' => '考勤',
            'InsuranceRecord' => '保险记录',
            'HousingFund' => '公积金',
            'Invoice' => '发票',
            'Payment' => '付款',
            'Approval' => '审批',
        ];

        $className = class_basename($this);
        return $modelNames[$className] ?? $className;
    }

    /**
     * 获取标识符(用于描述中显示)
     */
    protected function getIdentifier()
    {
        // 优先使用自定义标识符
        if (method_exists($this, 'getAuditIdentifier')) {
            return $this->getAuditIdentifier();
        }

        // 尝试常见的标识字段
        if (isset($this->name)) {
            return $this->name;
        }

        if (isset($this->title)) {
            return $this->title;
        }

        return "ID:{$this->id}";
    }

    /**
     * 获取可审计字段的标签
     */
    protected function getAuditableFieldLabels()
    {
        if (property_exists($this, 'auditableFields')) {
            return $this->auditableFields;
        }

        return [];
    }

    /**
     * 格式化值用于显示
     */
    protected function formatValue($field, $value)
    {
        if (is_null($value)) {
            return '空';
        }

        if (is_bool($value)) {
            return $value ? '是' : '否';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $value;
    }
}
