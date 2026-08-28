<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TaxDeclarationTask extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '税费申报任务';

    protected $fillable = [
        'account_set_id',
        'config_id',
        'declaration_type',
        'company_name',
        'tax_category_ids',
        'completed_tax_category_ids',
        'declaration_date',
        'year',
        'handler_id',
        'handler_name',
        'status',
        'completed_at',
        'completed_by',
    ];

    protected $casts = [
        'tax_category_ids' => 'array',
        'completed_tax_category_ids' => 'array',
        'declaration_date' => 'datetime:Y-m-d',
        'completed_at' => 'datetime:Y-m-d H:i:s',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $auditableFields = [
        'company_name' => '公司名称',
        'declaration_type' => '申报类型',
        'status' => '状态',
    ];

    /**
     * 历史任务没有单独保存类型时，从关联配置读取，保证任务列表仍能显示。
     */
    public function getDeclarationTypeAttribute($value)
    {
        if ($value) {
            return $value;
        }

        $config = $this->relationLoaded('config') ? $this->getRelation('config') : null;
        return $config?->declaration_type ?: $config?->period_type;
    }

    public function getAuditIdentifier()
    {
        return $this->company_name . ' - ' . $this->declaration_date->format('Y-m');
    }

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 关联配置
     */
    public function config()
    {
        return $this->belongsTo(TaxDeclarationConfig::class, 'config_id');
    }

    /**
     * 关联操作员
     */
    public function handler()
    {
        return $this->belongsTo(User::class, 'handler_id');
    }

    /**
     * 关联完成人
     */
    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /**
     * 关联附件
     */
    public function attachments()
    {
        return $this->hasMany(TaxDeclarationAttachment::class, 'task_id');
    }

    /**
     * 获取税种列表
     */
    public function getTaxCategoriesAttribute()
    {
        $categoryIds = $this->getConfiguredTaxCategoryIds();

        if (empty($categoryIds)) {
            return collect();
        }

        $categories = TaxCategory::with('parent')
            ->whereIn('id', $categoryIds)
            ->get()
            ->keyBy('id');

        return collect($categoryIds)
            ->map(fn ($categoryId) => $categories->get($categoryId))
            ->filter()
            ->values();
    }

    /**
     * 获取任务配置的税种 ID，统一转成整数并保持配置顺序。
     */
    public function getConfiguredTaxCategoryIds(): array
    {
        $ids = $this->normalizeTaxCategoryIds($this->tax_category_ids);

        if (empty($ids)) {
            return [];
        }

        $categories = TaxCategory::where('account_set_id', $this->account_set_id)
            ->get(['id', 'parent_id'])
            ->keyBy('id');
        $childrenByParent = $categories->filter(fn ($category) => $category->parent_id !== null)
            ->groupBy('parent_id');

        $expandedIds = [];
        foreach ($ids as $id) {
            $category = $categories->get($id);
            if (!$category) {
                continue;
            }

            $children = $childrenByParent->get($id, collect());
            if ($category->parent_id === null && $children->isNotEmpty()) {
                foreach ($children as $child) {
                    $expandedIds[] = (int) $child->id;
                }
            } else {
                $expandedIds[] = (int) $id;
            }
        }

        return array_values(array_unique($expandedIds));
    }

    /**
     * 获取已经完成申报的税种 ID。
     */
    public function getCompletedTaxCategoryIdsList(): array
    {
        if ($this->completed_tax_category_ids === null && $this->status === 'completed') {
            return $this->getConfiguredTaxCategoryIds();
        }

        return array_values(array_intersect(
            $this->getConfiguredTaxCategoryIds(),
            $this->normalizeTaxCategoryIds($this->completed_tax_category_ids)
        ));
    }

    /**
     * 获取尚未完成申报的税种 ID。
     */
    public function getPendingTaxCategoryIds(): array
    {
        return array_values(array_diff(
            $this->getConfiguredTaxCategoryIds(),
            $this->getCompletedTaxCategoryIdsList()
        ));
    }

    /**
     * 记录本次完成的税种；只有所有配置税种完成后，任务才完成。
     */
    public function markTaxCategoriesCompleted(array $categoryIds, $userId): void
    {
        $configuredIds = $this->getConfiguredTaxCategoryIds();
        $completedIds = array_values(array_unique(array_merge(
            $this->getCompletedTaxCategoryIdsList(),
            $this->normalizeTaxCategoryIds($categoryIds)
        )));
        $completedIds = array_values(array_intersect($configuredIds, $completedIds));
        $isCompleted = !empty($configuredIds) && empty(array_diff($configuredIds, $completedIds));

        $this->update([
            'completed_tax_category_ids' => $completedIds,
            'status' => $isCompleted ? 'completed' : 'pending',
            'completed_at' => $isCompleted ? now() : null,
            'completed_by' => $isCompleted ? $userId : null,
        ]);
    }

    private function normalizeTaxCategoryIds($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $value)));
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        $statuses = [
            'pending' => '待处理',
            'completed' => '已完成',
        ];
        return $statuses[$this->status] ?? $this->status;
    }

    /**
     * 标记为已完成
     */
    public function markAsCompleted($userId)
    {
        $this->update([
            'completed_tax_category_ids' => $this->getConfiguredTaxCategoryIds(),
            'status' => 'completed',
            'completed_at' => now(),
            'completed_by' => $userId,
        ]);
    }
}
