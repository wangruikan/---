<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class InjectAuthTokenFromHeader
{
    /**
     * Ensure Authorization header is available for Sanctum by copying from
     * a custom header or query parameter when necessary.
     */
    public function handle(Request $request, Closure $next)
    {
        // If Authorization header is already present, do nothing
        if (!$request->bearerToken()) {
            // Try to read token from custom header first
            $token = $request->header('X-Auth-Token');

            // Optional: support token from query string as a fallback
            if (!$token) {
                $token = $request->query('token');
            }

            if ($token) {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
