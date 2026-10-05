<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    private const ADMIN_ROLE_ID = 1;

    public function handle(Request $request, Closure $next): Response
    {
        if ((int) $request->user()?->role_id !== self::ADMIN_ROLE_ID) {
            return response()->json([
                'error' => 'Forbidden',
                'statusCode' => 403,
            ], 403);
        }

        return $next($request);
    }
}
