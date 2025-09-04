<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth;

class AdminAuth
{
    /**
     * Handle an incoming request.
     * Optionally enforce roles: usage -> middleware('auth:admin,editor')
     */
    public function handle($request, Closure $next, ...$roles)
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            if (!empty($roles)) {
                if (!in_array($user->role, $roles)) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
            }

            // Share user in request if needed
            $request->setUserResolver(fn() => $user);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Unauthorized', 'error' => $e->getMessage()], 401);
        }

        return $next($request);
    }
}
