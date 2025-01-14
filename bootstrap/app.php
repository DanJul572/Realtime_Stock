<?php

use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->stopIgnoring(AuthenticationException::class);
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            return response()->json([
                'error' => 'Unauthorized',
            ], 401);
        });
        $exceptions->respond(function (Response $response) {
            if ($response->getStatusCode() === 500) {
                return response()->json([
                    'error' => 'Inernal Serrver Error',
                    'statusCode' => $response->getStatusCode(),
                ], 500);
            } else if ($response->getStatusCode() === 404) {
                return response()->json([
                    'error' => 'Not Found',
                    'statusCode' => $response->getStatusCode(),
                ], 404);
            } else if ($response->getStatusCode() === 405) {
                return response()->json([
                    'error' => 'Method Not Allowed',
                    'statusCode' => $response->getStatusCode(),
                ], 405);
            }
            return $response;
        });
    })->create();
