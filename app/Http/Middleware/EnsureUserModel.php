<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureUserModel
{
    public function handle(Request $request, Closure $next, string $modelClass): mixed
    {
        if (! class_exists($modelClass)) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok'], 403);
        }

        if (! $request->user() || ! ($request->user() instanceof $modelClass)) {
            return response()->json(['message' => 'Bu işlem için yetkiniz yok'], 403);
        }

        return $next($request);
    }
}
