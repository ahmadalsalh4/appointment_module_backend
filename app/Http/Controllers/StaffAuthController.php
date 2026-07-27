<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StaffAuthController extends Controller
{
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Çıkış yapıldı']);
    }
}
