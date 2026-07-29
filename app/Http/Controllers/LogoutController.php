<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Single logout endpoint that detects the role from the authenticated
 * user. Replaces three near-identical {Admin,Customer,Staff}AuthController
 * classes. The token revocation logic is identical across roles; only the
 * route definition names the role (and that still exists for backward
 * compatibility — the controllers are kept as thin shims).
 */
class LogoutController extends Controller
{
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user && $user->currentAccessToken()) {
            $user->currentAccessToken()->delete();
        }

        return response()->json(['message' => 'Çıkış yapıldı']);
    }
}