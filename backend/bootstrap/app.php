<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $exception) => $request->is('api/*') || $request->expectsJson());

        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $errors = (object) [];
            $headers = [];
            if ($exception instanceof ValidationException) {
                $status = 422;
                $message = '輸入資料有誤。';
                $errors = $exception->errors();
            } elseif ($exception instanceof PostTooLargeException) {
                $status = 422;
                $message = '上傳內容超過伺服器限制。';
                $errors = ['photo' => ['照片不得超過 20 MiB；伺服器也必須允許此上傳大小。']];
            } elseif ($exception instanceof HttpExceptionInterface) {
                $status = $exception->getStatusCode();
                $headers = $exception->getHeaders();
                $message = $status === 404 ? '找不到指定資源。' : '無法處理此請求。';
            } else {
                $status = 500;
                $message = '伺服器暫時無法處理請求，請稍後再試。';
            }

            return response()->json(['data' => null, 'message' => $message, 'errors' => $errors], $status, $headers);
        });
    })->create();
