<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
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

        // Load the same relation set as UnifiedAuthController::login so
        // the refresh response shape matches the login response shape.
        $relations = match (get_class($user)) {
            Customer::class => ['person'],
            Staff::class => ['person', 'managingAdmin'],
            Admin::class => ['person'],
            default => [],
        };

        return response()->json([
            'token' => $token,
            'role' => $this->getCurrentRole($user),
            'user' => $user->load($relations),
        ]);
    }

    private function getCurrentRole($user): string
    {
        return match (get_class($user)) {
            Customer::class => 'customer',
            Staff::class => 'staff',
            Admin::class => 'admin',
            default => 'unknown',
        };
    }
}