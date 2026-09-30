<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required_without:email', 'string'],
            'email' => ['required_without:username', 'string'],
            'password' => ['required', 'string'],
        ]);
        $login = $data['username'] ?? $data['email'];
        $user = User::query()->where('username', $login)->orWhere('email', $login)->first();

        if (! $user || ! $token = auth('api')->attempt(['email' => $user->email, 'password' => $data['password']])) {
            return response()->json(['error' => 'invalid_credentials'], 401);
        }

        return response()->json(['token' => $token]);
    }

    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::parseToken()->refresh();
        } catch (TokenExpiredException) {
            try {
                $token = JWTAuth::refresh();
            } catch (JWTException) {
                return response()->json(['status' => 'Token is Expired'], 401);
            }
        } catch (TokenInvalidException) {
            return response()->json(['status' => 'Token is Invalid'], 401);
        } catch (JWTException) {
            return response()->json(['status' => 'Authorization Token not found'], 401);
        }

        return response()->json(['token' => $token]);
    }

    public function logout(): JsonResponse
    {
        JWTAuth::parseToken()->invalidate(true);

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        return response()->json($user->present());
    }
}
