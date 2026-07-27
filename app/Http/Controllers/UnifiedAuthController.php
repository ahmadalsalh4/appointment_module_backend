<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class UnifiedAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $email = $request->email;
        $password = $request->password;
        $otherRoles = [];

        $customer = Customer::where('email', $email)->first();
        if ($customer && Hash::check($password, $customer->password)) {
            $token = $customer->createToken('auth-token')->plainTextToken;

            if (Staff::where('email', $email)->exists()) {
                $otherRoles[] = 'staff';
            }
            if (Admin::where('email', $email)->exists()) {
                $otherRoles[] = 'admin';
            }

            return response()->json([
                'user' => $customer->load('person'),
                'token' => $token,
                'role' => 'customer',
                'other_roles' => $otherRoles,
            ]);
        }

        $staff = Staff::where('email', $email)->first();
        if ($staff && Hash::check($password, $staff->password)) {
            $token = $staff->createToken('auth-token')->plainTextToken;

            if (Customer::where('email', $email)->exists()) {
                $otherRoles[] = 'customer';
            }
            if (Admin::where('email', $email)->exists()) {
                $otherRoles[] = 'admin';
            }

            return response()->json([
                'user' => $staff->load(['person', 'managingAdmin']),
                'token' => $token,
                'role' => 'staff',
                'other_roles' => $otherRoles,
            ]);
        }

        $admin = Admin::where('email', $email)->first();
        if ($admin && Hash::check($password, $admin->password)) {
            $token = $admin->createToken('auth-token')->plainTextToken;

            if (Customer::where('email', $email)->exists()) {
                $otherRoles[] = 'customer';
            }
            if (Staff::where('email', $email)->exists()) {
                $otherRoles[] = 'staff';
            }

            return response()->json([
                'user' => $admin->load('person'),
                'token' => $token,
                'role' => 'admin',
                'other_roles' => $otherRoles,
            ]);
        }

        throw ValidationException::withMessages([
            'email' => ['Bilgiler hatalı.'],
        ]);
    }

    public function myRoles(Request $request)
    {
        $user = $request->user();
        $email = $user->email;
        $currentRole = $this->getCurrentRole($user);
        $roles = [];

        if ($currentRole !== 'customer' && Customer::where('email', $email)->exists()) {
            $roles[] = 'customer';
        }
        if ($currentRole !== 'staff' && Staff::where('email', $email)->exists()) {
            $roles[] = 'staff';
        }
        if ($currentRole !== 'admin' && Admin::where('email', $email)->exists()) {
            $roles[] = 'admin';
        }

        return response()->json([
            'current_role' => $currentRole,
            'other_roles' => $roles,
        ]);
    }

    public function switchRole(Request $request)
    {
        $request->validate([
            'role' => 'required|in:customer,staff,admin',
        ]);

        $user = $request->user();
        $email = $user->email;
        $targetRole = $request->role;

        $model = match ($targetRole) {
            'customer' => Customer::where('email', $email)->first(),
            'staff' => Staff::where('email', $email)->first(),
            'admin' => Admin::where('email', $email)->first(),
        };

        if (!$model) {
            return response()->json([
                'message' => "Bu email ile {$targetRole} rolünde kayıt bulunamadı.",
            ], 404);
        }

        $user->currentAccessToken()->delete();
        $token = $model->createToken('auth-token')->plainTextToken;

        $relations = match ($targetRole) {
            'customer' => ['person'],
            'staff' => ['person', 'managingAdmin'],
            'admin' => ['person'],
        };

        return response()->json([
            'user' => $model->load($relations),
            'token' => $token,
            'role' => $targetRole,
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
