<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * 账套中间件
 * 用于在请求中注入当前用户的账套信息
 */
class AccountSetMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user) {
            return $next($request);
        }
        
        // 系统管理员可以访问所有账套
        if ($user->role === 'admin') {
            // 从请求头或参数中获取账套ID
            $accountSetId = $request->header('X-Account-Set-Id') 
                         ?? $request->input('account_set_id');
            
            if ($accountSetId) {
                $request->merge(['current_account_set_id' => $accountSetId]);
            }
        } else {
            // 非管理员只能访问被分配的账套
            $userAccountSet = \DB::table('account_set_users')
                ->where('user_id', $user->id)
                ->where('is_default', 1)
                ->first();
            
            if ($userAccountSet) {
                $request->merge(['current_account_set_id' => $userAccountSet->account_set_id]);
            }
        }
        
        return $next($request);
    }
}

