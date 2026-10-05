<?php

namespace App\Http\Middleware;

use App\Models\ErrorLog;
use App\Support\LogSanitizer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Stores every API request that ends with a non-2xx status in error_logs.
 * Registered as global middleware so unknown routes (404) and failed
 * authentication (401) are recorded too. Exceptions thrown while handling the
 * request are attached to the response by the routing pipeline.
 */
class LogFailedRequest
{
    private const MAX_TRACE_LENGTH = 20000;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->is('api/*') && !$response->isSuccessful()) {
            try {
                $this->record($request, $response);
            } catch (Throwable $exception) {
                // Logging must never change the response the client gets.
                report($exception);
            }
        }

        return $response;
    }

    private function record(Request $request, Response $response): void
    {
        $statusCode = $response->getStatusCode();
        $exception = property_exists($response, 'exception') ? $response->exception : null;
        $body = json_decode((string) $response->getContent(), true);
        $requestBody = $request->isMethod('GET') ? [] : $request->all();

        ErrorLog::create([
            'user_id' => $request->user('sanctum')?->id,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $statusCode,
            'message' => is_array($body) ? ($body['error'] ?? $body['message'] ?? null) : null,
            'request_body' => $requestBody ? LogSanitizer::clean($requestBody) : null,
            'exception_class' => $exception ? get_class($exception) : null,
            'exception_message' => $exception?->getMessage() ?: null,
            'exception_location' => $exception
                ? Str::after($exception->getFile(), base_path() . DIRECTORY_SEPARATOR) . ':' . $exception->getLine()
                : null,
            'trace' => $exception && $statusCode >= 500
                ? Str::limit($exception->getTraceAsString(), self::MAX_TRACE_LENGTH)
                : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
