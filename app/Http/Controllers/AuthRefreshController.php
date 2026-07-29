<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Token refresh: rotates the access token for the current user. The old
 * token is deleted and a new one is issued. This is a small step toward
 * reducing the window for token exfiltration; an httpOnly-cookie /
 * refresh-token design is a separate initiative.
 */
class AuthRefreshController extends Controller
{
    public function refresh(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Yetkilendirme gerekli.'], 401);
        }

        $token = DB::transaction(function () use ($user) {
            if ($user->currentAccessToken()) {
                $user->currentAccessToken()->delete();
            }

            return $user->createToken('auth-token')->plainTextToken;
        });

        return response()->json([
            'token' => $token,
            'role' => $this->getCurrentRole($user),
            'user' => $user,
        ]);
    }

    private function getCurrentRole($user): string
    {
        return match (get_class($user)) {
            \App\Models\Customer::class => 'customer',
            \App\Models\Staff::class => 'staff',
            \App\Models\Admin::class => 'admin',
            default => 'unknown',
        };
    }
}
