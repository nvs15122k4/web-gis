<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Chưa có CSDL (hoặc CSDL chưa migrate): API trả 503 "đang cập nhật" thay vì lỗi 500,
        // để giao diện vẫn chạy và hiển thị trạng thái rỗng.
        $exceptions->render(function (QueryException|\PDOException $e, Request $request) {
            if ($request->is('api/*')) {
                report($e);

                return response()->json(['message' => 'Dữ liệu đang cập nhật.', 'status' => 'updating'], 503);
            }
        });
    })->create();
