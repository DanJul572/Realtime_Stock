<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Runs every API request inside one database transaction, so a request that
 * fails halfway (e.g. the product is saved but its audit log is not) leaves
 * no partial changes behind.
 *
 * The transaction is rolled back when the request throws an exception or ends
 * with a 5xx status. Plain 4xx responses returned by a controller (such as a
 * failed login, whose audit entry must be kept) are committed.
 *
 * Registered in the api group, so it runs inside the global LogFailedRequest
 * middleware: the error log is written after the rollback and is not undone.
 */
class DatabaseTransaction
{
    public function handle(Request $request, Closure $next): Response
    {
        $level = DB::transactionLevel();
        DB::beginTransaction();

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->rollBack($level);
            throw $exception;
        }

        if ($this->failed($response)) {
            $this->rollBack($level);
        } else {
            DB::commit();
        }

        return $response;
    }

    // Exceptions thrown by the controller reach this middleware as an already
    // rendered response that still carries the exception.
    private function failed(Response $response): bool
    {
        $exception = property_exists($response, 'exception') ? $response->exception : null;

        return $exception !== null || $response->getStatusCode() >= 500;
    }

    private function rollBack(int $level): void
    {
        try {
            DB::rollBack($level);
        } catch (Throwable $exception) {
            // A lost connection already discards the open transaction.
            report($exception);
        }
    }
}
