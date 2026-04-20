<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * 账套数据过滤 Trait
 * 用于统一处理账套数据隔离逻辑
 */
trait HasAccountSet
{
    /**
     * 应用账套过滤到查询
     */
    protected function applyAccountSetFilter(Builder $query, Request $request)
    {
        $currentAccountSetId = $request->input('current_account_set_id');
        
        if ($currentAccountSetId) {
            // 有账套ID，过滤数据
            $query->where('account_set_id', $currentAccountSetId);
        } elseif ($request->user()->role !== 'admin') {
            // 非管理员且没有账套ID，返回空
            $query->whereRaw('1 = 0');
        }
        
        return $query;
    }

    /**
     * 添加账套ID到数据
     */
    protected function addAccountSetId(array $data, Request $request)
    {
        $currentAccountSetId = $request->input('current_account_set_id');
        
        if ($currentAccountSetId) {
            $data['account_set_id'] = $currentAccountSetId;
        }
        
        return $data;
    }
}

