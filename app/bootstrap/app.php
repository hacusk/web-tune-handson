<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->validateCsrfTokens(except: [
            'initialize',
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, \Throwable $e) => true);
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($e instanceof HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = $e->getMessage();
                if ($message === '') {
                    $message = $status === 404 ? 'Not found' : 'Error';
                }

                return response()->json(['error' => $message], $status);
            }

            report($e);

            return response()->json([
                'error' => 'サーバーエラー。docker compose logs app を確認してください',
            ], 500);
        });
    })->create();
