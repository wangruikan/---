<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SharedFileController;

// 文件查看/预览路由 - 在浏览器中打开
Route::get('/view/shared-files/{id}', [SharedFileController::class, 'view'])
    ->name('shared-files.view')
    ->withoutMiddleware([
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ]);

// 文件下载路由 - 完全不使用任何中间件
Route::get('/download/shared-files/{id}', [SharedFileController::class, 'download'])
    ->name('shared-files.download')
    ->withoutMiddleware([
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ]);

// 所有前端路由都返回Vue.js应用
Route::get('/{any}', function () {
    return file_get_contents(public_path('index.html'));
})->where('any', '.*');
