<?php

use App\Exceptions\DomainError;
use App\Http\Middleware\ApiCors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(api: __DIR__.'/../routes/api.php', apiPrefix: '')
    ->withMiddleware(function (Middleware $middleware): void {
        // The contract trims strings itself and distinguishes empty strings from null.
        $middleware->remove([
            TrimStrings::class,
            ConvertEmptyStringsToNull::class,
        ]);
        $middleware->replace(HandleCors::class, ApiCors::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $request, Throwable $e) => true);
        $exceptions->dontReport([DomainError::class]);
        $exceptions->render(function (DomainError $e) {
            return response()->json(['error' => [
                'code' => $e->errorCode, 'message' => $e->getMessage(), 'fields' => $e->fields,
            ]], $e->status);
        });
        $exceptions->render(function (Throwable $e) {
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = match ($status) {
                404 => 'Not found',
                405 => 'Method not allowed',
                413 => 'Request too large',
                503 => 'Service unavailable',
                default => $status >= 500 ? 'Internal server error' : 'Request failed',
            };

            return response()->json(['error' => [
                'code' => $status >= 500 ? 'server_error' : 'http_error',
                'message' => $message, 'fields' => [],
            ]], $status, $e instanceof HttpExceptionInterface ? $e->getHeaders() : []);
        });
    })->create();
