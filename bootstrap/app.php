<?php

use App\Support\CanonicalResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $error, Request $request) {
            if (! $request->is('api/v2/summary/current', 'api/v1/health', 'api/v1/version', 'api/v1/forecast/daily')) {
                return null;
            }
            $status = $error instanceof HttpExceptionInterface ? $error->getStatusCode() : 500;
            $code = match ($status) {
                404 => 'not_found', 405 => 'method_not_allowed', 503 => 'service_unavailable', default => 'internal_error'
            };
            $message = $status === 405 ? 'Method not allowed.' : ($status >= 500 ? 'Unable to serve this request.' : 'Request could not be processed.');
            $response = CanonicalResponse::error($request, $code, $message, $status);
            if ($error instanceof HttpExceptionInterface) {
                $response->withHeaders($error->getHeaders());
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
