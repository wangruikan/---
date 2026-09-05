<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class TaxDeclarationConfig extends Model
{
    use HasFactory, Auditable;

    protected $auditName = '税费申报配置';

    protected $fillable = [
        'account_set_id',
        'company_name',
        'tax_category_ids',
        'period_types',
        'declaration_date',
        'created_by',
    ];

    protected $casts = [
        'tax_category_ids' => 'array',
        'period_types' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    protected $auditableFields = [
        'company_name' => '公司名称',
        'period_types' => '申报周期',
        'declaration_date' => '申报月份',
    ];

    /**
     * 获取配置选择的申报周期。
     */
    public function getPeriodTypes(): array
    {
        $periodTypes = is_array($this->period_types) ? $this->period_types : [];
        $periodTypes = array_values(array_unique(array_filter(
            array_map('strval', $periodTypes),
            fn ($type) => in_array($type, ['monthly', 'quarterly', 'yearly'], true)
        )));

        return $periodTypes;
    }

    public function getAuditIdentifier()
    {
        $month = (int) substr((string) $this->declaration_date, 0, 2);
        $labels = [];
        foreach ($this->getPeriodTypes() as $periodType) {
            if ($periodType === 'monthly') {
                $labels[] = '每月';
            } elseif ($periodType === 'quarterly') {
                $labels[] = '每季度';
            } elseif ($periodType === 'yearly') {
                $labels[] = '每年' . $month . '月';
            }
        }

        return $this->company_name . ' - ' . implode('、', $labels);
    }

    /**
     * 关联账套
     */
    public function accountSet()
    {
        return $this->belongsTo(AccountSet::class);
    }

    /**
     * 关联创建人
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * 配置只保存真正的细分税种 ID；选择大类时展开为该大类的全部细分。
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

    private function normalizeTaxCategoryIds($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', $value)));
    }
}
