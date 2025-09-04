<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->only(['email', 'password']);
        if (empty($credentials['email']) || empty($credentials['password'])) {
            return response()->json(['message' => 'Email and password required'], 422);
        }

        try {
            if (!$token = JWTAuth::attempt($credentials)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        } catch (JWTException $e) {
            return response()->json(['message' => 'Could not create token'], 500);
        }

        return $this->respondWithToken($token);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json($user);
    }

    public function logout()
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (JWTException $e) {
            return response()->json(['message' => 'Token invalidation failed'], 400);
        }
        return response()->json(['message' => 'Logged out']);
    }

    public function refresh()
    {
        try {
            $newToken = JWTAuth::refresh(JWTAuth::getToken());
            return $this->respondWithToken($newToken);
        } catch (JWTException $e) {
            return response()->json(['message' => 'Could not refresh token'], 400);
        }
    }

    protected function respondWithToken(string $token)
    {
        return response()->json([
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => \Tymon\JWTAuth\Facades\JWTAuth::factory()->getTTL() * 60,
        ]);
    }
}
