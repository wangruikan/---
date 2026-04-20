<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * 检查用户是否有指定权限
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission  权限标识，如 employees.create
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        
        // 未登录
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => '请先登录'
            ], 401);
        }
        
        // super_admin 和 admin 拥有所有权限
        if (in_array($user->role, ['super_admin', 'admin'])) {
            return $next($request);
        }
        
        // 检查用户是否有指定权限
        if (!$user->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => '您没有执行此操作的权限',
                'required_permission' => $permission
            ], 403);
        }
        
        return $next($request);
    }
}
