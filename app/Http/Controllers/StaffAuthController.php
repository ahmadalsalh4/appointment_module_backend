<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffAuthController extends Controller
{
    // Staff kayıtları muhtemelen admin tarafından yapılır, ama basit bir register bırakıyorum
    public function login(Request $request)
    {
        $request->validate(['email' => 'required|email', 'password' => 'required']);

        $staff = Staff::where('email', $request->email)->first();

        if (!$staff || !Hash::check($request->password, $staff->password)) {
            throw ValidationException::withMessages(['email' => ['Bilgiler hatalı.']]);
        }

        $token = $staff->createToken('staff-token')->plainTextToken;

        return response()->json([
            'staff' => $staff->load(['person', 'managingAdmin']),
            'token' => $token,
            // is_admin kaldırıldı — admin artık ayrı bir login akışı (AdminAuthController)
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Çıkış yapıldı']);
    }
}
