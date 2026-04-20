<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueryLogMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 开启查询日志
        DB::enableQueryLog();
        
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        // 获取查询日志
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // 如果执行时间超过1秒或查询超过10次，记录日志
        if ($executionTime > 1000 || $queryCount > 10) {
            Log::warning('Slow API Request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => $executionTime . 'ms',
                'query_count' => $queryCount,
                'queries' => $queries
            ]);
        }
        
        // 添加性能头信息
        $response->headers->set('X-Execution-Time', $executionTime . 'ms');
        $response->headers->set('X-Query-Count', $queryCount);
        
        return $response;
    }
}
