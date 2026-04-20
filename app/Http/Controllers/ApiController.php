<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    /**
     * 成功响应
     */
    protected function success($data = null, string $message = '操作成功', int $code = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'code' => $code
        ], $code);
    }

    /**
     * 错误响应
     */
    protected function error(string $message = '操作失败', $data = null, int $code = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'code' => $code
        ], $code);
    }

    /**
     * 分页响应
     */
    protected function paginated($data, string $message = '获取成功'): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data->items(),
            'pagination' => [
                'current_page' => $data->currentPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
                'last_page' => $data->lastPage(),
                'has_more' => $data->hasMorePages()
            ]
        ]);
    }
}
