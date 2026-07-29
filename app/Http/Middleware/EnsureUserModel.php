<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserModel
{
    public function handle(Request $request, Closure $next, string $modelClass): mixed
    {
        if (! class_exists($modelClass)) {
            // Misconfiguration on our side — surface as 500 rather than a
            // confusing 403.
            return response()->json(['message' => 'Sunucu yapılandırma hatası'], 500);
        }

        $user = $request->user();

        // Unauthenticated is 401 by convention. The `auth:*` middleware
        // normally rejects this earlier, but be defensive in case it was
        // bypassed.
        if (! $user) {
            return response()->json(['message' => 'Bu işlem için giriş yapmalısınız'], 401);
        }

        // Authenticated but wrong model class — this is a forbidden role.
        if (! ($user instanceof $modelClass)) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok'], 403);
        }

        return $next($request);
    }
}