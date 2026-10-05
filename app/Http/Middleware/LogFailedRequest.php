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
 *
 * It runs outside the DatabaseTransaction middleware, so the request's changes
 * are already rolled back when the log is written. If the log cannot be stored
 * in the database (e.g. the database itself is down), it is appended to a
 * daily text file in storage/logs/error-logs instead.
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
        $attributes = $this->attributes($request, $response);

        try {
            ErrorLog::create($attributes);
        } catch (Throwable $exception) {
            self::writeToFile($attributes, $exception);
        }
    }

    private function attributes(Request $request, Response $response): array
    {
        $statusCode = $response->getStatusCode();
        $exception = property_exists($response, 'exception') ? $response->exception : null;
        $body = json_decode((string) $response->getContent(), true);
        $requestBody = $request->isMethod('GET') ? [] : $request->all();

        return [
            'user_id' => $this->userId($request),
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
        ];
    }

    // Looking up the token needs the database; when it is down the log is
    // still written (to the file) without the user.
    private function userId(Request $request): ?int
    {
        try {
            return $request->user('sanctum')?->id;
        } catch (Throwable) {
            return null;
        }
    }

    public static function fallbackPath(): string
    {
        return storage_path('logs/error-logs/' . now()->format('Y-m-d') . '.txt');
    }

    private static function writeToFile(array $attributes, Throwable $reason): void
    {
        $path = self::fallbackPath();
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        $entry = [
            'logged_at' => now()->toDateTimeString(),
            'error_log' => $attributes,
            'database_error' => get_class($reason) . ': ' . $reason->getMessage(),
        ];

        $written = file_put_contents(
            $path,
            json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)
                . PHP_EOL . str_repeat('-', 80) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );

        if ($written === false) {
            throw new \RuntimeException("Could not write the error log to {$path}", 0, $reason);
        }
    }
}
