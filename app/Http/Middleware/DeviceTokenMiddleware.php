<?php

namespace App\Http\Middleware;

use App\Models\Screen;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        $token = trim(str_starts_with($header, 'Bearer ') ? substr($header, 7) : '');
        if ($token === '') {
            return response()->json(['status' => 'Authorization Token not found'], 401);
        }

        $screen = Screen::query()->where('device_token', hash('sha256', $token))->first();
        if (! $screen) {
            return response()->json(['status' => 'Authorization Token not found'], 401);
        }

        $request->attributes->set('screen', $screen);

        return $next($request);
    }
}
