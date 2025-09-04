<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\Guest;

class GuestTokenMiddleware
{
    public function handle($request, Closure $next)
    {
        $token = $request->route('token');

        if (!$token) {
            return response()->json(['error' => 'Token requerido'], 401);
        }

        $guest = Guest::where('token', $token)->first();

        if (!$guest) {
            return response()->json(['error' => 'Token inválido'], 401);
        }

        $request->merge(['guest' => $guest]);

        return $next($request);
    }
}
