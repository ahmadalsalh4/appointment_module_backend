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
        $request->validate([
            'job_email' => 'required|email',
            'password' => 'required',
        ]);

        $staff = Staff::where('job_email', $request->job_email)->first();

        if (!$staff || !Hash::check($request->password, $staff->password)) {
            throw ValidationException::withMessages([
                'job_email' => ['Bilgiler hatalı.'],
            ]);
        }

        $token = $staff->createToken('staff-token')->plainTextToken;

        return response()->json([
            'staff' => $staff->load(['person', 'adminProfile']),
            'is_admin' => $staff->adminProfile()->exists(),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Çıkış yapıldı']);
    }
}
