<?php

namespace App\Http\Middleware;

use Closure;
use Exception;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();
        } catch (TokenExpiredException) {
            return response()->json(['status' => 'Token is Expired'], 403);
        } catch (TokenInvalidException) {
            return response()->json(['status' => 'Token is Invalid'], 401);
        } catch (JWTException) {
            return response()->json(['status' => 'Authorization Token not found'], 401);
        } catch (Exception) {
            return response()->json(['status' => 'Authorization Token not found'], 401);
        }

        if (! $user) {
            return response()->json(['status' => 'Authorization Token not found'], 401);
        }

        return $next($request);
    }
}
