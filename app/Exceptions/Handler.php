<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $exception)
    {
        // 处理未认证异常
        if ($exception instanceof \Illuminate\Auth\AuthenticationException) {
            \Log::warning('Unauthenticated request', [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_id' => optional($request->user())->id,
                'guards' => method_exists($exception, 'guards') ? $exception->guards() : null,
                'authorization_head' => substr($request->header('Authorization', ''), 0, 100),
                'user_agent' => $request->header('User-Agent'),
            ]);

            return response()->json([
                'success' => false,
                'message' => '未授权，请先登录',
                'error' => 'Unauthenticated'
            ], 401);
        }

        // 处理路由未找到异常
        if ($exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
            return response()->json([
                'success' => false,
                'message' => '请求的资源不存在',
                'error' => 'Not Found'
            ], 404);
        }

        return parent::render($request, $exception);
    }
}
